<?php

namespace App\Imports;

use App\Models\ProcessedData;
use App\Models\Upload;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class GenericImport implements ToModel, WithHeadingRow, WithStartRow, SkipsEmptyRows
{
    protected Upload $upload;
    protected int $headingRow;
    protected ?string $defaultDateYmd;
    protected ?string $cuenta;
    protected ?int $sheetMonth;

    public function __construct(Upload $upload, int $headingRow, ?string $defaultDateYmd = null, ?string $cuenta = null, ?int $sheetMonth = null)
    {
        $this->upload         = $upload;
        $this->headingRow     = $headingRow;
        $this->defaultDateYmd = $defaultDateYmd;
        $this->cuenta         = $cuenta;
        $this->sheetMonth     = $sheetMonth ? (int)$sheetMonth : null;
    }

    public function headingRow(): int
    {
        return $this->headingRow;
    }

    public function startRow(): int
    {
        return $this->headingRow + 1;
    }

    public function model(array $row)
    {
        // Fecha de cambio (se mantiene la lógica original)
        $fc = $this->toDate($row['cambio_fecha_de_disponibilidad'] ?? null);

        // Fecha de disponibilidad: SIEMPRE usar la fecha de H3, ignorando la columna
        $fd = $this->defaultDateYmd;

        return new ProcessedData([
            'upload_id'   => $this->upload->id,
            'cuenta'      => $this->cuenta,
            'no'          => $row['no'] ?? null,
            'producto'    => $row['producto'] ?? null,
            'descripcion' => $row['descripcion'] ?? null,
            'solicitado'  => (float)($row['solicitado'] ?? 0),
            'facturado'   => (float)($row['facturado'] ?? 0),
            'faltante'    => (float)($row['faltante'] ?? 0),
            'precio'      => (float)($row['precio'] ?? 0),
            'importe'     => (float)($row['importe'] ?? 0),
            'peso'        => (float)($row['peso'] ?? 0),
            'fecha_disponibilidad'        => $fd, // ← SIEMPRE será H3
            'cambio_fecha_disponibilidad' => $fc,
            'comentarios' => $row['comentarios'] ?? null,
        ]);
    }

    /**
     * (Método toDate se mantiene igual para procesar la fecha de cambio)
     */
    private function toDate($value): ?string
    {
        try {
            if ($value === null || $value === '') return null;

            if (is_numeric($value)) {
                return Carbon::instance(Date::excelToDateTimeObject($value))->format('Y-m-d');
            }

            $value = trim((string)$value);

            if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/', $value, $m)) {
                $part1 = (int)$m[1];
                $part2 = (int)$m[2];
                $part3 = (int)$m[3];

                if (strlen($m[3]) === 2) {
                    $year = $part3 <= 69 ? 2000 + $part3 : 1900 + $part3;
                } else {
                    $year = $part3;
                }

                if ($this->sheetMonth !== null) {
                    if ($part1 === $this->sheetMonth) {
                        $month = $part1;
                        $day   = $part2;
                        return Carbon::create($year, $month, $day)->format('Y-m-d');
                    }
                    if ($part2 === $this->sheetMonth) {
                        $day   = $part1;
                        $month = $part2;
                        return Carbon::create($year, $month, $day)->format('Y-m-d');
                    }
                }

                if ($part1 > 12 && $part2 <= 12) {
                    $day   = $part1;
                    $month = $part2;
                    return Carbon::create($year, $month, $day)->format('Y-m-d');
                }

                if ($part2 > 12 && $part1 <= 12) {
                    $month = $part1;
                    $day   = $part2;
                    return Carbon::create($year, $month, $day)->format('Y-m-d');
                }

                $day   = $part1;
                $month = $part2;
                return Carbon::create($year, $month, $day)->format('Y-m-d');
            }

            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }
}