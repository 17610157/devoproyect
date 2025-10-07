<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function negadosPivotExcel(Request $request)
    {
        $dateFrom = $request->query('date_from'); // 'YYYY-MM-DD'
        $dateTo   = $request->query('date_to');   // 'YYYY-MM-DD'
        $search   = $request->query('search');    // opcional

        // ==========================
        // 1) Agregado CUENTA + PRODUCTO + DÍA con 4 reglas
        // ==========================
        $agg = DB::table('processed_data as pd')
            ->when($dateFrom, fn($q,$d) => $q->whereDate('pd.fecha_disponibilidad', '>=', $d))
            ->when($dateTo,   fn($q,$d) => $q->whereDate('pd.fecha_disponibilidad', '<=', $d))
            ->when($search, function ($q, $s) {
                $q->where(function ($qq) use ($s) {
                    $qq->where('pd.producto','like',"%{$s}%")
                       ->orWhere('pd.descripcion','like',"%{$s}%");
                });
            })
            ->selectRaw("
                COALESCE(pd.cuenta, '')       AS cuenta,
                pd.producto                   AS codigo,
                pd.descripcion                AS descripcion,
                DATE(pd.fecha_disponibilidad) AS fecha,
                SUM(
                    CASE
                        WHEN DATE(pd.fecha_disponibilidad) <> DATE(pd.cambio_fecha_disponibilidad)
                             AND COALESCE(pd.solicitado,0) = COALESCE(pd.facturado,0)
                        THEN CAST(COALESCE(pd.solicitado,0) AS DECIMAL(18,4))

                        WHEN DATE(pd.fecha_disponibilidad) <> DATE(pd.cambio_fecha_disponibilidad)
                        THEN GREATEST(
                            CAST(COALESCE(pd.solicitado,0) AS DECIMAL(18,4))
                          - CAST(COALESCE(pd.facturado,0)  AS DECIMAL(18,4))
                        , 0)

                        WHEN LOWER(COALESCE(pd.comentarios,'')) LIKE '%cancelado%'
                          OR LOWER(COALESCE(pd.comentarios,'')) LIKE '%sustituye%'
                        THEN GREATEST(
                            CAST(COALESCE(pd.solicitado,0) AS DECIMAL(18,4))
                          - CAST(COALESCE(pd.facturado,0)  AS DECIMAL(18,4))
                        , 0)

                        WHEN COALESCE(pd.faltante,0) > 0
                          AND DATE(pd.fecha_disponibilidad) = DATE(pd.cambio_fecha_disponibilidad)
                          AND COALESCE(pd.facturado,0) > 0
                        THEN GREATEST(
                            CAST(COALESCE(pd.solicitado,0) AS DECIMAL(18,4))
                          - CAST(COALESCE(pd.facturado,0)  AS DECIMAL(18,4))
                        , 0)

                        ELSE 0
                    END
                ) AS qty,
                SUM(
                    CASE
                        WHEN DATE(pd.fecha_disponibilidad) <> DATE(pd.cambio_fecha_disponibilidad)
                             AND COALESCE(pd.solicitado,0) = COALESCE(pd.facturado,0)
                        THEN CAST(COALESCE(pd.solicitado,0) AS DECIMAL(18,4))
                             * CAST(COALESCE(pd.precio,0) AS DECIMAL(18,4))

                        WHEN DATE(pd.fecha_disponibilidad) <> DATE(pd.cambio_fecha_disponibilidad)
                        THEN GREATEST(
                             CAST(COALESCE(pd.solicitado,0) AS DECIMAL(18,4))
                           - CAST(COALESCE(pd.facturado,0)  AS DECIMAL(18,4))
                        , 0) * CAST(COALESCE(pd.precio,0) AS DECIMAL(18,4))

                        WHEN LOWER(COALESCE(pd.comentarios,'')) LIKE '%cancelado%'
                          OR LOWER(COALESCE(pd.comentarios,'')) LIKE '%sustituye%'
                        THEN GREATEST(
                             CAST(COALESCE(pd.solicitado,0) AS DECIMAL(18,4))
                           - CAST(COALESCE(pd.facturado,0)  AS DECIMAL(18,4))
                        , 0) * CAST(COALESCE(pd.precio,0) AS DECIMAL(18,4))

                        WHEN COALESCE(pd.faltante,0) > 0
                          AND DATE(pd.fecha_disponibilidad) = DATE(pd.cambio_fecha_disponibilidad)
                          AND COALESCE(pd.facturado,0) > 0
                        THEN GREATEST(
                             CAST(COALESCE(pd.solicitado,0) AS DECIMAL(18,4))
                           - CAST(COALESCE(pd.facturado,0)  AS DECIMAL(18,4))
                        , 0) * CAST(COALESCE(pd.precio,0) AS DECIMAL(18,4))

                        ELSE 0
                    END
                ) AS importe_dia
            ")
            ->whereNotNull('pd.fecha_disponibilidad')
            ->groupBy('cuenta','codigo','descripcion','fecha')
            ->orderBy('cuenta')
            ->orderBy('codigo')
            ->get();

        if ($agg->isEmpty()) {
            $headings = ['CUENTA','CÓDIGO','DESCRIPCIÓN','TOTAL SOLICITADO','PRECIO SUMADO','IMPORTE TOTAL'];
            $rows = [];
            return Excel::download(new class($headings,$rows) implements FromArray, WithHeadings, ShouldAutoSize {
                use Exportable;
                public function __construct(private array $h, private array $r) {}
                public function headings(): array { return $this->h; }
                public function array(): array { return $this->r; }
            }, 'reporte_vacio.xlsx');
        }

        // 2) Columnas de fechas dinámicas (asc)
        $dateColumns = $agg->pluck('fecha')->unique()->sort()->values()->all();

        // 3) PRECIO SUMADO (suma de precios distintos por cuenta+producto que cumplen reglas)
        $priceRows = DB::table('processed_data as pd')
            ->when($dateFrom, fn($q,$d) => $q->whereDate('pd.fecha_disponibilidad', '>=', $d))
            ->when($dateTo,   fn($q,$d) => $q->whereDate('pd.fecha_disponibilidad', '<=', $d))
            ->when($search, function ($q, $s) {
                $q->where(function ($qq) use ($s) {
                    $qq->where('pd.producto','like',"%{$s}%")
                       ->orWhere('pd.descripcion','like',"%{$s}%");
                });
            })
            ->whereNotNull('pd.fecha_disponibilidad')
            ->where(function($q){
                $q->whereRaw('DATE(pd.fecha_disponibilidad) <> DATE(pd.cambio_fecha_disponibilidad)')
                  ->orWhereRaw("LOWER(COALESCE(pd.comentarios,'')) LIKE '%cancelado%'")
                  ->orWhereRaw("LOWER(COALESCE(pd.comentarios,'')) LIKE '%sustituye%'")
                  ->orWhere(function($qq){
                      $qq->whereRaw('COALESCE(pd.faltante,0) > 0')
                         ->whereRaw('DATE(pd.fecha_disponibilidad) = DATE(pd.cambio_fecha_disponibilidad)')
                         ->whereRaw('COALESCE(pd.facturado,0) > 0');
                  });
            })
            ->selectRaw("COALESCE(pd.cuenta,'') AS cuenta, pd.producto AS codigo, pd.descripcion AS descripcion, pd.precio AS precio")
            ->groupBy('cuenta','codigo','descripcion','precio')
            ->get();

        $priceSumByKey = [];
        foreach ($priceRows as $pr) {
            $key = $pr->cuenta.'|'.$pr->codigo.'|'.$pr->descripcion;
            $priceSumByKey[$key] = ($priceSumByKey[$key] ?? 0) + (float)$pr->precio;
        }

        // 4) Pivot
        $pivot = [];
        foreach ($agg as $r) {
            $key = $r->cuenta.'|'.$r->codigo.'|'.$r->descripcion;
            if (!isset($pivot[$key])) {
                $pivot[$key] = [
                    'cuenta'        => $r->cuenta,
                    'codigo'        => $r->codigo,
                    'descripcion'   => $r->descripcion,
                    'fechas'        => array_fill_keys($dateColumns, 0.0),
                    'total_importe' => 0.0,
                ];
            }
            $pivot[$key]['fechas'][$r->fecha] = (float) $r->qty;
            $pivot[$key]['total_importe']    += (float) $r->importe_dia;
        }

        $dateHeadings = array_map(fn($d) => Carbon::parse($d)->format('d/m/Y'), $dateColumns);
        $headings = array_merge(
            ['CUENTA','CÓDIGO','DESCRIPCIÓN'],
            $dateHeadings,
            ['TOTAL SOLICITADO','PRECIO SUMADO','IMPORTE TOTAL']
        );

        $rows = [];
        $grandQtyFiltrado     = 0.0;
        $grandImporteFiltrado = 0.0;

        foreach ($pivot as $key => $row) {
            $vals  = [];
            $totalQty = 0.0;

            foreach ($dateColumns as $d) {
                $v = (float) ($row['fechas'][$d] ?? 0);
                $vals[] = $v == 0.0 ? '' : $v;
                $totalQty += $v;
            }

            if ($totalQty == 0.0 && $row['total_importe'] == 0.0) {
                continue;
            }

            $priceKey     = $row['cuenta'].'|'.$row['codigo'].'|'.$row['descripcion'];
            $precioSumado = $priceSumByKey[$priceKey] ?? 0.0;
            $importeTotal = $row['total_importe'];

            $grandQtyFiltrado     += $totalQty;
            $grandImporteFiltrado += $importeTotal;

            $rows[] = array_merge(
                [$row['cuenta'], $row['codigo'], $row['descripcion']],
                $vals,
                [$totalQty, $precioSumado, $importeTotal]
            );
        }

        // ==========================
        // 5) Importe global (todos los registros) = SUM(solicitado * precio) sin filtros  ⬅️  (CAMBIO)
        // ==========================
        $importeGlobal = (float) DB::table('processed_data')
            ->selectRaw("
                SUM(
                    CAST(COALESCE(solicitado, 0) AS DECIMAL(18,4)) *
                    CAST(
                        REPLACE(REPLACE(REPLACE(TRIM(COALESCE(precio, '0')), ',', ''), '$', ''), ' ', '')
                        AS DECIMAL(18,4)
                    )
                ) AS total
            ")
            ->value('total');

        $ratio = $importeGlobal > 0 ? ($grandImporteFiltrado / $importeGlobal) : 0.0;

        // 6) Filas resumen
        $blankDates = array_fill(0, count($dateColumns), '');

        $rows[] = array_merge(
            ['—', '—', 'SUMA IMPORTE TOTAL (filtro)'],
            $blankDates,
            ['', '', $grandImporteFiltrado]
        );

        $rows[] = array_merge(
            ['—', '—', 'IMPORTE TOTAL (todos los registros)'],
            $blankDates,
            ['', '', $importeGlobal]
        );

        $rows[] = array_merge(
            ['—', '—', 'Porcentaje de Efectividad'],
            $blankDates,
            ['', '', $ratio]
        );

        // 7) Export con formatos
        $filename = 'negados_por_cuenta_producto_dia_'.now()->format('Ymd_His').'.xlsx';

        $export = new class($headings, $rows, count($dateColumns)) implements
            FromArray, WithHeadings, ShouldAutoSize, WithColumnFormatting, WithEvents
        {
            use Exportable;

            private array $h;
            private array $r;
            private int $dateCount;

            private string $totalQtyCol;   // TOTAL SOLICITADO
            private string $priceSumCol;   // PRECIO SUMADO
            private string $importeCol;    // IMPORTE TOTAL
            private array  $dateCols = []; // columnas dinámicas de fechas

            public function __construct(array $headings, array $rows, int $dateCount)
            {
                $this->h = $headings;
                $this->r = $rows;
                $this->dateCount = $dateCount;

                // 3 fijas: A=1(B cuenta) B=2(código) C=3(descripción); Fechas D..; Totales al final
                $totalQtyIdx = 3 + $dateCount + 1; // TOTAL SOLICITADO
                $priceSumIdx = $totalQtyIdx + 1;   // PRECIO SUMADO
                $importeIdx  = $priceSumIdx + 1;   // IMPORTE TOTAL

                $this->totalQtyCol = Coordinate::stringFromColumnIndex($totalQtyIdx);
                $this->priceSumCol = Coordinate::stringFromColumnIndex($priceSumIdx);
                $this->importeCol  = Coordinate::stringFromColumnIndex($importeIdx);

                for ($i = 0; $i < $dateCount; $i++) {
                    $this->dateCols[] = Coordinate::stringFromColumnIndex(4 + $i);
                }
            }

            public function headings(): array { return $this->h; }
            public function array(): array     { return $this->r; }

            public function columnFormats(): array
            {
                $formats = [];
                foreach ($this->dateCols as $col) {
                    $formats[$col] = '#,##0';
                }
                $formats[$this->totalQtyCol] = '#,##0';
                $formats[$this->priceSumCol] = '[$$-es-MX] #,##0.00';
                $formats[$this->importeCol]  = '[$$-es-MX] #,##0.00';
                return $formats;
            }

            public function registerEvents(): array
            {
                return [
                    AfterSheet::class => function(AfterSheet $event) {
                        $sheet = $event->sheet->getDelegate();
                        $lastRow = 1 + count($this->r);
                        $ratioCell = $this->importeCol . $lastRow;

                        $sheet->getStyle($ratioCell)
                              ->getNumberFormat()
                              ->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);

                        $lastColIdx = 3 + $this->dateCount + 3;
                        $lastCol = Coordinate::stringFromColumnIndex($lastColIdx);
                        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
                    }
                ];
            }
        };

        return Excel::download($export, $filename);
    }
}
