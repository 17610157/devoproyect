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

    public function __construct(Upload $upload, int $headingRow)
    {
        $this->upload = $upload;
        $this->headingRow = $headingRow; // fila donde están los encabezados (1-based)
    }

    // Indica dónde inician los encabezados
    public function headingRow(): int
    {
        return $this->headingRow;
    }

    // Y a partir de la siguiente fila arrancan los datos
    public function startRow(): int
    {
        return $this->headingRow + 9;
    }

    public function model(array $row)
    {
        // Con WithHeadingRow, $row llega con claves normalizadas (slug)
        return new ProcessedData([
            'upload_id'   => $this->upload->id,
            'no'          => $row['no'] ?? null,
            'producto'    => $row['producto'] ?? null,
            'descripcion' => $row['descripcion'] ?? null,
            'solicitado'  => (int)($row['solicitado'] ?? 0),
            'facturado'   => (int)($row['facturado'] ?? 0),
            'faltante'    => (int)($row['faltante'] ?? 0),
            'precio'      => (float)($row['precio'] ?? 0),
            'importe'     => (float)($row['importe'] ?? 0),
            'peso'        => (float)($row['peso'] ?? 0),
            'fecha_disponibilidad'        => $this->toDate($row['fecha_de_disponibilidad'] ?? null),
            'cambio_fecha_disponibilidad' => $this->toDate($row['cambio_fecha_de_disponibilidad'] ?? null),
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
