<?php

namespace App\Imports;

use App\Models\ProcessedData;
use App\Models\Upload;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class GenericImport implements ToModel, WithHeadingRow, WithStartRow, SkipsEmptyRows
{
    protected Upload $upload;

    /** Fila donde están los encabezados (1-based). Fijamos 9 como pediste. */
    protected int $headingRow;

    /**
     * Fecha fallback (H3) ya normalizada a Y-m-d. Se aplicará si
     * ambas fechas vienen vacías.
     */
    protected ?string $fallbackDateYmd;

    /**
     * @param Upload $upload
     * @param int $headingRow            fila de encabezados (por defecto 9)
     * @param string|null $fallbackDateYmd fecha H3 normalizada (Y-m-d) o null
     */
    public function __construct(Upload $upload, int $headingRow = 9, ?string $fallbackDateYmd = null)
    {
        $this->upload           = $upload;
        $this->headingRow       = $headingRow ?: 9;
        $this->fallbackDateYmd  = $fallbackDateYmd;
    }

    /** Indica en qué fila están los encabezados. */
    public function headingRow(): int
    {
        return $this->headingRow; // normalmente 9
    }

    /** Los datos inician 1 fila después del header. */
    public function startRow(): int
    {
        return $this->headingRow + 1; // 10
    }

    public function model(array $row)
    {
        // Con WithHeadingRow, las claves ya vienen "slugificadas"
        // por ejemplo: 'fecha de disponibilidad' => 'fecha_de_disponibilidad'
        $f1 = $this->toYmd($row['fecha_de_disponibilidad'] ?? null);
        $f2 = $this->toYmd($row['cambio_fecha_de_disponibilidad'] ?? null);

        // Si AMBAS fechas vienen vacías, usa H3 (fallback)
        if (!$f1 && !$f2 && $this->fallbackDateYmd) {
            $f1 = $this->fallbackDateYmd;
            $f2 = $this->fallbackDateYmd;
        }

        return new ProcessedData([
            'upload_id'   => $this->upload->id,
            'no'          => $row['no'] ?? null,
            'producto'    => $row['producto'] ?? null,
            'descripcion' => $row['descripcion'] ?? null,
            'solicitado'  => $this->toInt($row['solicitado'] ?? 0),
            'facturado'   => $this->toInt($row['facturado'] ?? 0),
            'faltante'    => $this->toInt($row['faltante'] ?? 0),
            'precio'      => $this->toFloat($row['precio'] ?? 0),
            'importe'     => $this->toFloat($row['importe'] ?? 0),
            'peso'        => $this->toFloat($row['peso'] ?? 0),
            'fecha_disponibilidad'        => $f1,
            'cambio_fecha_disponibilidad' => $f2,
            'comentarios' => $row['comentarios'] ?? null,
        ]);
    }

    /** Normaliza fechas a Y-m-d desde serial de Excel o string. */
    private function toYmd($value): ?string
    {
        try {
            if ($value === null || $value === '') return null;

            // Serial de Excel
            if (is_numeric($value)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject($value))->format('Y-m-d');
            }

            // Limpia y parsea cadenas tipo "01/09/2025 13:11:17"
            $v = trim((string)$value);

            // Si viene como dd/mm/yyyy [...] fuerza ese orden
            if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{2,4}/', $v)) {
                // separa fecha y hora
                [$datePart] = preg_split('/\s+/', $v);
                [$d, $m, $y] = array_map('intval', explode('/', $datePart));
                // normaliza año corto
                if ($y < 100) $y += ($y >= 70 ? 1900 : 2000);
                return Carbon::createFromDate($y, $m, $d)->format('Y-m-d');
            }

            // Parse genérico (acepta yyyy-mm-dd, mm/dd/yyyy, etc.)
            return Carbon::parse($v)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Convierte a entero robusto (elimina comas, espacios). */
    private function toInt($v): int
    {
        if ($v === null || $v === '') return 0;
        $v = str_replace([',', ' '], '', (string)$v);
        return (int) round((float)$v);
    }

    /** Convierte a float robusto (elimina comas, $ y espacios). */
    private function toFloat($v): float
    {
        if ($v === null || $v === '') return 0.0;
        $v = trim((string)$v);
        $v = str_replace(['$', ' ', ','], '', $v);
        return (float) $v;
    }
}
