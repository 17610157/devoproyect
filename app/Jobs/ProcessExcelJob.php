<?php

namespace App\Jobs;

use App\Models\Upload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Carbon\Carbon;

class ProcessExcelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $uploadId;

    public function __construct(int $uploadId)
    {
        $this->uploadId = $uploadId;
    }

    public function handle(): void
    {
        $upload = Upload::find($this->uploadId);
        if (!$upload) return;

        if (!Storage::exists($upload->file_path)) {
            $upload->update(['status' => 'ERROR']);
            \App\Models\UploadLog::create([
                'upload_id'   => $upload->id,
                'row_number'  => 0,
                'error_message' => "El archivo no existe en storage: {$upload->file_path}",
            ]);
            return;
        }

        $upload->update(['status' => 'PROCESANDO']);

        try {
            $path = Storage::path($upload->file_path);

            // 1) Abre con PhpSpreadsheet
            $spreadsheet = IOFactory::load($path);
            $sheet       = $spreadsheet->getActiveSheet();

            // 2) Lee H3 como fallback y normaliza a Y-m-d
            $fallbackYmd = $this->parseToYmd($sheet->getCell('H3')->getValue());

            // 3) Los encabezados están en la fila 9 (1-based)
            $headerRow = 9;

            // 4) Valida encabezados requeridos en fila 9
            //    toArray() devuelve todas las filas; tomamos la 8 (0-based) de PHP
            $rows = $sheet->toArray(null, true, true, false);
            $headLine = $rows[$headerRow - 1] ?? [];
            $missing  = $this->missingRequiredHeaders($headLine);
            if (!empty($missing)) {
                $upload->update(['status' => 'ERROR']);
                \App\Models\UploadLog::create([
                    'upload_id'    => $upload->id,
                    'row_number'   => $headerRow,
                    'error_message'=> 'Encabezados faltantes: ' . implode(', ', $missing),
                ]);
                return;
            }

            // 5) Importa desde esa fila con fecha fallback para fechas vacías
            Excel::import(
                new \App\Imports\GenericImport($upload, $headerRow, $fallbackYmd),
                $path
            );

            // 6) Recalcula métricas y estado final
            $success = \App\Models\ProcessedData::where('upload_id', $upload->id)->count();
            $errors  = \App\Models\UploadLog::where('upload_id', $upload->id)->count();
            $total   = $success + $errors;

            if ($total === 0) {
                \App\Models\UploadLog::create([
                    'upload_id'    => $upload->id,
                    'row_number'   => 0,
                    'error_message'=> 'No se insertó ninguna fila (verifique hoja/encabezados).',
                ]);
            }

            $finalStatus = 'COMPLETADO';
            if ($success > 0 && $errors > 0) $finalStatus = 'CARGADO_CON_ERRORES';
            if ($success === 0 && $errors > 0) $finalStatus = 'ERROR';

            $upload->update([
                'total_rows'   => $total,
                'success_rows' => $success,
                'error_rows'   => $errors,
                'status'       => $finalStatus,
            ]);

        } catch (\Throwable $e) {
            $upload->update(['status' => 'ERROR']);
            \App\Models\UploadLog::create([
                'upload_id'    => $upload->id,
                'row_number'   => 0,
                'error_message'=> $e->getMessage(),
            ]);
        }
    }

    /** ==== Helpers ==== */

    /**
     * Normaliza una posible fecha (serial Excel o string) a 'Y-m-d'.
     * Acepta 'dd/mm/yyyy [hh:mm:ss]' y otros formatos comunes.
     */
    private function parseToYmd($value): ?string
    {
        try {
            if ($value === null || $value === '') return null;

            // Serial de Excel
            if (is_numeric($value)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject($value))->format('Y-m-d');
            }

            // String
            $v = trim((string)$value);

            // dd/mm/yyyy [...]
            if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{2,4}/', $v)) {
                [$datePart] = preg_split('/\s+/', $v);
                [$d, $m, $y] = array_map('intval', explode('/', $datePart));
                if ($y < 100) $y += ($y >= 70 ? 1900 : 2000);
                return Carbon::createFromDate($y, $m, $d)->format('Y-m-d');
            }

            // Genérico: yyyy-mm-dd, mm/dd/yyyy, etc.
            return Carbon::parse($v)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Valida los encabezados mínimos requeridos en la fila de header.
     * Recibe la línea cruda y normaliza para comparar.
     */
    private function missingRequiredHeaders(array $headerLine): array
    {
        $required = [
            'no', 'producto', 'descripcion', 'solicitado', 'facturado', 'faltante',
            'precio', 'importe', 'peso', 'fecha de disponibilidad',
            'cambio fecha de disponibilidad', 'comentarios',
        ];

        $present = array_map([$this, 'norm'], $headerLine);
        $present = array_filter($present);

        $missing = [];
        foreach ($required as $r) {
            if (!in_array($this->norm($r), $present, true)) {
                $missing[] = $r;
            }
        }
        return $missing;
    }

    /** Normaliza textos de encabezado: minúscula, sin acentos, espacios->guión_bajo. */
    private function norm($str): string
    {
        $s = mb_strtolower((string) $str, 'UTF-8');
        $s = preg_replace('/[áàä]/u','a',$s);
        $s = preg_replace('/[éèë]/u','e',$s);
        $s = preg_replace('/[íìï]/u','i',$s);
        $s = preg_replace('/[óòö]/u','o',$s);
        $s = preg_replace('/[úùü]/u','u',$s);
        $s = preg_replace('/[^a-z0-9]+/u',' ', $s);
        $s = trim(preg_replace('/\s+/u',' ', $s));
        return str_replace(' ', '_', $s);
    }
}
