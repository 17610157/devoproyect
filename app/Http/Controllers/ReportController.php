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

        // 1) Agregado por PRODUCTO + DÍA con reglas de negocio
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
                pd.producto  AS codigo,
                pd.descripcion AS descripcion,
                DATE(pd.fecha_disponibilidad) AS fecha,
                SUM(
                    CASE
                        WHEN DATE(pd.fecha_disponibilidad) <> DATE(pd.cambio_fecha_disponibilidad)
                        THEN
                            CASE
                                WHEN COALESCE(pd.facturado,0) = 0
                                    THEN CAST(COALESCE(pd.solicitado,0) AS DECIMAL(18,4))
                                ELSE GREATEST(
                                    CAST(COALESCE(pd.solicitado,0) AS DECIMAL(18,4))
                                  - CAST(COALESCE(pd.facturado,0)  AS DECIMAL(18,4))
                                , 0)
                            END
                        ELSE 0
                    END
                ) AS qty,
                SUM(
                    CASE
                        WHEN DATE(pd.fecha_disponibilidad) <> DATE(pd.cambio_fecha_disponibilidad)
                        THEN
                            (
                                CASE
                                    WHEN COALESCE(pd.facturado,0) = 0
                                        THEN CAST(COALESCE(pd.solicitado,0) AS DECIMAL(18,4))
                                    ELSE GREATEST(
                                        CAST(COALESCE(pd.solicitado,0) AS DECIMAL(18,4))
                                      - CAST(COALESCE(pd.facturado,0)  AS DECIMAL(18,4))
                                    , 0)
                                END
                            ) * CAST(COALESCE(pd.precio,0) AS DECIMAL(18,4))
                        ELSE 0
                    END
                ) AS importe_dia
            ")
            ->whereNotNull('pd.fecha_disponibilidad')
            ->groupBy('codigo','descripcion','fecha')
            ->orderBy('codigo')
            ->get();

        if ($agg->isEmpty()) {
            $headings = ['CÓDIGO','DESCRIPCIÓN','TOTAL SOLICITADO','PRECIO SUMADO','IMPORTE TOTAL'];
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

        // 3) Precios distintos por PRODUCTO (para "PRECIO SUMADO"), respetando condición de fechas distintas
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
            ->whereRaw('DATE(pd.fecha_disponibilidad) <> DATE(pd.cambio_fecha_disponibilidad)')
            ->selectRaw('pd.producto AS codigo, pd.descripcion AS descripcion, pd.precio AS precio')
            ->groupBy('codigo','descripcion','precio')
            ->get();

        $priceSumByKey = [];
        foreach ($priceRows as $pr) {
            $key = $pr->codigo.'|'.$pr->descripcion;
            $priceSumByKey[$key] = ($priceSumByKey[$key] ?? 0) + (float)$pr->precio; // suma de precios distintos
        }

        // 4) Pivot y armado de filas
        $pivot = [];
        foreach ($agg as $r) {
            $key = $r->codigo.'|'.$r->descripcion;
            if (!isset($pivot[$key])) {
                $pivot[$key] = [
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
            ['CÓDIGO','DESCRIPCIÓN'],
            $dateHeadings,
            ['TOTAL SOLICITADO','PRECIO SUMADO','IMPORTE TOTAL']
        );

        $rows = [];
        $grandQtyFiltrado     = 0.0; // suma TOTAL SOLICITADO (filtro)
        $grandImporteFiltrado = 0.0; // suma IMPORTE TOTAL (filtro)

        foreach ($pivot as $key => $row) {
            $vals  = [];
            $totalQty = 0.0;

            foreach ($dateColumns as $d) {
                $v = (float) ($row['fechas'][$d] ?? 0);
                // Usa $v si prefieres ver 0; '' lo deja vacío cuando es 0
                $vals[] = $v == 0.0 ? '' : $v;
                $totalQty += $v;
            }

            // Omitir filas sin datos útiles (opcional)
            if ($totalQty == 0.0 && $row['total_importe'] == 0.0) {
                continue;
            }

            $precioSumado = $priceSumByKey[$key] ?? 0.0;
            $importeTotal = $row['total_importe'];

            $grandQtyFiltrado     += $totalQty;
            $grandImporteFiltrado += $importeTotal;

            $rows[] = array_merge(
                [$row['codigo'], $row['descripcion']],
                $vals,
                [$totalQty, $precioSumado, $importeTotal]
            );
        }

        // 5) IMPORTE TOTAL (todos los registros) = SUM(processed_data.importe) sin filtros (cast robusto)
        $importeGlobal = (float) DB::table('processed_data')
            ->selectRaw("
                SUM(
                    CAST(
                        REPLACE(REPLACE(REPLACE(TRIM(COALESCE(importe,'0')), ',', ''), '$', ''), ' ', '')
                        AS DECIMAL(18,4)
                    )
                ) AS total
            ")
            ->value('total');

        $ratio = $importeGlobal > 0 ? ($grandImporteFiltrado / $importeGlobal) : 0.0;

        // 6) Filas de resumen al final
        $blankDates = array_fill(0, count($dateColumns), '');

        $rows[] = array_merge(
            ['—', 'SUMA TOTAL SOLICITADO (filtro)'],
            $blankDates,
            [$grandQtyFiltrado, '', '']
        );

        $rows[] = array_merge(
            ['—', 'SUMA IMPORTE TOTAL (filtro)'],
            $blankDates,
            ['', '', $grandImporteFiltrado]
        );

        $rows[] = array_merge(
            ['—', 'IMPORTE TOTAL (todos los registros)'],
            $blankDates,
            ['', '', $importeGlobal]
        );

        $rows[] = array_merge(
            ['—', 'Porcentaje de Efectividad'],
            $blankDates,
            ['', '', $ratio] // se formatea como % en el export
        );

        // 7) Exportar con formatos (cantidades, moneda y %)
        $filename = 'solicitado_por_dia_fechas_distintas_'.now()->format('Ymd_His').'.xlsx';

        $export = new class($headings, $rows, count($dateColumns)) implements
            FromArray, WithHeadings, ShouldAutoSize, WithColumnFormatting, WithEvents
        {
            use Exportable;

            private array $h;
            private array $r;
            private int $dateCount;

            private string $totalQtyCol;   // columna “TOTAL SOLICITADO”
            private string $priceSumCol;   // columna “PRECIO SUMADO”
            private string $importeCol;    // columna “IMPORTE TOTAL”
            private array $dateCols = [];  // columnas dinámicas de fechas (C..?)

            public function __construct(array $headings, array $rows, int $dateCount)
            {
                $this->h = $headings;
                $this->r = $rows;
                $this->dateCount = $dateCount;

                // A=1, B=2; fechas desde C (3) hasta (2 + dateCount)
                $totalQtyIdx = 2 + $dateCount + 1; // TOTAL SOLICITADO
                $priceSumIdx = $totalQtyIdx + 1;   // PRECIO SUMADO
                $importeIdx  = $priceSumIdx + 1;   // IMPORTE TOTAL

                $this->totalQtyCol = Coordinate::stringFromColumnIndex($totalQtyIdx);
                $this->priceSumCol = Coordinate::stringFromColumnIndex($priceSumIdx);
                $this->importeCol  = Coordinate::stringFromColumnIndex($importeIdx);

                // Columnas de fechas
                for ($i = 0; $i < $dateCount; $i++) {
                    $this->dateCols[] = Coordinate::stringFromColumnIndex(3 + $i);
                }
            }

            public function headings(): array { return $this->h; }
            public function array(): array     { return $this->r; }

            public function columnFormats(): array
            {
                $formats = [];

                // Fechas (valores por día): enteros
                foreach ($this->dateCols as $col) {
                    $formats[$col] = '#,##0';
                }

                // Total solicitado: entero
                $formats[$this->totalQtyCol] = '#,##0';

                // Moneda MXN (ajusta si deseas otro símbolo)
                $formats[$this->priceSumCol] = '[$$-es-MX] #,##0.00';
                $formats[$this->importeCol]  = '[$$-es-MX] #,##0.00';

                return $formats;
            }

            public function registerEvents(): array
            {
                return [
                    AfterSheet::class => function(AfterSheet $event) {
                        $sheet = $event->sheet->getDelegate();

                        // Última fila (incluye resumen): header en 1, datos en 2..N
                        $lastRow = 1 + count($this->r);

                        // La celda del “Porcentaje de Efectividad” está en la última fila y en la col de IMPORTE TOTAL
                        $ratioCell = $this->importeCol . $lastRow;

                        // Formato porcentaje con 2 decimales
                        $sheet->getStyle($ratioCell)
                              ->getNumberFormat()
                              ->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);

                        // Encabezado en negritas
                        $lastColIdx = 2 + $this->dateCount + 3; // A..última
                        $lastCol = Coordinate::stringFromColumnIndex($lastColIdx);
                        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
                    }
                ];
            }
        };

        return Excel::download($export, $filename);
    }
}
