<?php
// app/Exports/UploadsSummaryExport.php

namespace App\Exports;

use App\Models\Upload;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UploadsSummaryExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    use Exportable;

    public function __construct(private array $filters = []) {}

    public function query()
    {
        return Upload::query()
            ->when($this->filters['search'] ?? null, fn ($q, $s) => $q->where('file_name', 'like', "%{$s}%"))
            ->when($this->filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($this->filters['date_from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($this->filters['date_to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest();
    }

    public function headings(): array
    {
        return [
            'Archivo', 'Estatus', 'Correctos', 'Errores', 'Total', 'Creado en',
        ];
    }

    public function map($u): array
    {
        return [
            $u->file_name,
            $u->status,
            $u->success_rows,
            $u->error_rows,
            $u->total_rows,
            optional($u->created_at)->format('Y-m-d H:i'),
        ];
    }
}
