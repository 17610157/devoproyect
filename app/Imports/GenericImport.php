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
    protected ?int $sheetMonth; // mes esperado en la cédula (1..12) — usado para resolver ambigüedades

    /**
     * @param Upload $upload
     * @param int $headingRow
     * @param string|null $defaultDateYmd
     * @param string|null $cuenta
     * @param int|null $sheetMonth  Mes esperado en la cédula (1..12). Opcional pero recomendable.
     */
    public function __construct(Upload $upload, int $headingRow, ?string $defaultDateYmd = null, ?string $cuenta = null, ?int $sheetMonth = null)
    {
        $this->upload         = $upload;
        $this->headingRow     = $headingRow;      // p.ej. 9
        $this->defaultDateYmd = $defaultDateYmd;  // fecha fallback de H3 (Y-m-d)
        $this->cuenta         = $cuenta;          // valor leído de C3
        $this->sheetMonth     = $sheetMonth ? (int)$sheetMonth : null;
    }

    public function headingRow(): int
    {
        return $this->headingRow; // 9
    }

    public function startRow(): int
    {
        // La fila de datos es la siguiente a la de encabezados
        return $this->headingRow + 1; // 10
    }

    public function model(array $row)
    {
        // Fechas de la fila
        $fd = $this->toDate($row['fecha_de_disponibilidad'] ?? null);
        $fc = $this->toDate($row['cambio_fecha_de_disponibilidad'] ?? null);

        // Si ambas están vacías, usar fallback (H3)
        if (!$fd && !$fc && $this->defaultDateYmd) {
            $fd = $fc = $this->defaultDateYmd;
        }

        return new ProcessedData([
            'upload_id'   => $this->upload->id,
            'cuenta'      => $this->cuenta, // <— NUEVO: se asigna a todas las filas
            'no'          => $row['no'] ?? null,
            'producto'    => $row['producto'] ?? null,
            'descripcion' => $row['descripcion'] ?? null,
            'solicitado'  => (float)($row['solicitado'] ?? 0),
            'facturado'   => (float)($row['facturado'] ?? 0),
            'faltante'    => (float)($row['faltante'] ?? 0),
            'precio'      => (float)($row['precio'] ?? 0),
            'importe'     => (float)($row['importe'] ?? 0),
            'peso'        => (float)($row['peso'] ?? 0),
            'fecha_disponibilidad'        => $fd,
            'cambio_fecha_disponibilidad' => $fc,
            'comentarios' => $row['comentarios'] ?? null,
        ]);
    }

    /**
     * Normaliza diferentes representaciones de fecha a 'Y-m-d' o null.
     *
     * Reglas principales:
     *  - Si $value es numérico (fecha Excel), lo convierte con Date::excelToDateTimeObject.
     *  - Si es cadena en formato dd/mm/aaaa o dd-mm-aaaa o mm-dd-yy etc., intenta detectar formato.
     *  - Si el patrón es ambigüo (x-y-z) y $this->sheetMonth está definido, se usa para decidir:
     *      * si la primera parte coincide con sheetMonth => se interpreta como m-d-y
     *      * si la segunda parte coincide con sheetMonth => se interpreta como d-m-y
     *  - Usa heurísticas: si la primera parte > 12 => es día (d-m-y). Si la segunda parte > 12 => la segunda es día (m-d-y).
     */
    private function toDate($value): ?string
    {
        try {
            if ($value === null || $value === '') return null;

            // Fecha Excel (número)
            if (is_numeric($value)) {
                return Carbon::instance(Date::excelToDateTimeObject($value))->format('Y-m-d');
            }

            $value = trim((string)$value);

            // Detecta patrones tipo 1/2/2024 o 01-02-24
            if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/', $value, $m)) {
                $part1 = (int)$m[1];
                $part2 = (int)$m[2];
                $part3 = (int)$m[3];

                // Normaliza año a 4 dígitos
                if (strlen($m[3]) === 2) {
                    // regla común: 00-69 -> 2000-2069, 70-99 -> 1970-1999
                    $year = $part3 <= 69 ? 2000 + $part3 : 1900 + $part3;
                } else {
                    $year = $part3;
                }

                // Si tenemos sheetMonth, usarla para resolver ambigüedad.
                if ($this->sheetMonth !== null) {
                    if ($part1 === $this->sheetMonth) {
                        // primera parte = month => formato m-d-y
                        $month = $part1;
                        $day   = $part2;
                        return Carbon::create($year, $month, $day)->format('Y-m-d');
                    }
                    if ($part2 === $this->sheetMonth) {
                        // segunda parte = month => formato d-m-y
                        $day   = $part1;
                        $month = $part2;
                        return Carbon::create($year, $month, $day)->format('Y-m-d');
                    }
                }

                // Heurísticas cuando no hay sheetMonth:
                if ($part1 > 12 && $part2 <= 12) {
                    // part1 imposible como mes -> d-m-y
                    $day   = $part1;
                    $month = $part2;
                    return Carbon::create($year, $month, $day)->format('Y-m-d');
                }

                if ($part2 > 12 && $part1 <= 12) {
                    // part2 imposible como mes -> m-d-y
                    $month = $part1;
                    $day   = $part2;
                    return Carbon::create($year, $month, $day)->format('Y-m-d');
                }

                // Ambos <= 12: poco info. Por defecto asumimos d-m-y (día primero),
                // porque muchas cédulas/países usan ese formato; si necesitas preferir m-d-y
                // cambia esta rama. 
                $day   = $part1;
                $month = $part2;
                return Carbon::create($year, $month, $day)->format('Y-m-d');
            }

            // Intentar parseo flexible con Carbon (maneja "2024-03-12", "12 Mar 2024", etc.)
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            // si falla el parseo devolvemos null para que el flujo de import deje fallback o registre el error
            return null;
        }
    }
}
