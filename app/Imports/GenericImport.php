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

    public function __construct(Upload $upload, int $headingRow, ?string $defaultDateYmd = null, ?string $cuenta = null)
    {
        $this->upload        = $upload;
        $this->headingRow    = $headingRow;      // p.ej. 9
        $this->defaultDateYmd= $defaultDateYmd;  // fecha fallback de H3
        $this->cuenta        = $cuenta;          // valor leído de C3
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

    private function toDate($value): ?string
    {
        try {
            if ($value === null || $value === '') return null;
            if (is_numeric($value)) {
                return Carbon::instance(Date::excelToDateTimeObject($value))->format('Y-m-d');
            }
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
