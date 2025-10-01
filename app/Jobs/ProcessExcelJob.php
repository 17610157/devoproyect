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

class ProcessExcelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $uploadId;

    /**
     * Recibimos sólo el ID del upload (no el modelo).
     */
    public function __construct(int $uploadId)
    {
        $this->uploadId = $uploadId;
    }

    /**
     * Ejecuta el procesamiento del Excel.
     */
    public function handle(): void
{
    $upload = Upload::find($this->uploadId);
    if (!$upload) return;

    if (!Storage::exists($upload->file_path)) {
        $upload->update(['status' => 'ERROR']);
        \App\Models\UploadLog::create([
            'upload_id' => $upload->id,
            'row_number' => 0,
            'error_message' => "El archivo no existe en storage: {$upload->file_path}",
        ]);
        return;
    }

    $upload->update(['status' => 'PROCESANDO']);

    try {
        $path = Storage::path($upload->file_path);

        // 1) Abrimos con PhpSpreadsheet para detectar encabezados
        $spreadsheet = IOFactory::load($path);
        $sheet       = $spreadsheet->getActiveSheet(); // o getSheetByName('HOJA X')
        $rows        = $sheet->toArray(null, true, true, false); // array de filas

        $headerRow = $this->detectHeaderRow($rows);
        if (!$headerRow) {
            $upload->update(['status' => 'ERROR']);
            \App\Models\UploadLog::create([
                'upload_id' => $upload->id,
                'row_number' => 0,
                'error_message' => 'No se encontraron encabezados válidos en el archivo.',
            ]);
            return;
        }

        // 2) Validar que los encabezados requeridos estén presentes
        $headLine = $rows[$headerRow - 1] ?? []; // headerRow es 1-based
        $missing  = $this->missingRequiredHeaders($headLine);
        if (!empty($missing)) {
            $upload->update(['status' => 'ERROR']);
            \App\Models\UploadLog::create([
                'upload_id' => $upload->id,
                'row_number' => $headerRow,
                'error_message' => 'Encabezados faltantes: ' . implode(', ', $missing),
            ]);
            return;
        }

        // 3) Importar iniciando EXACTAMENTE en esa fila de encabezados
        Excel::import(new \App\Imports\GenericImport($upload, $headerRow), $path);

        // 4) Recalcular contadores desde BD
        $success = \App\Models\ProcessedData::where('upload_id', $upload->id)->count();
        $errors  = \App\Models\UploadLog::where('upload_id', $upload->id)->count();
        $total   = $success + $errors;

        if ($total === 0) {
            \App\Models\UploadLog::create([
                'upload_id' => $upload->id,
                'row_number' => 0,
                'error_message' => 'No se insertó ninguna fila (verifique hoja/encabezados).',
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
            'upload_id' => $upload->id,
            'row_number' => 0,
            'error_message' => $e->getMessage(),
        ]);
    }
}

/** ===== Helpers para encabezados ===== */

private function detectHeaderRow(array $rows): ?int
{
    // Mínimos que deben aparecer en la fila de encabezados
    $expected = ['no', 'producto', 'descripcion', 'solicitado', 'facturado'];

    // Escanear primeras N filas (ajusta si tu archivo tiene mucho prefacio)
    $limit = min(count($rows), 50);

    for ($i = 0; $i < $limit; $i++) {
        $cells = array_map([$this, 'norm'], array_filter($rows[$i] ?? []));
        if (empty($cells)) continue;

        $match = 0;
        foreach ($expected as $e) {
            if (in_array($e, $cells, true)) $match++;
        }
        if ($match >= 3) {
            return $i + 1; // 1-based para WithHeadingRow
        }
    }
    return null;
}

private function missingRequiredHeaders(array $headerLine): array
{
    // Requeridos totales para tu caso:
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
    return str_replace(' ', '_', $s); // slug simple: "fecha de disponibilidad" -> "fecha_de_disponibilidad"
}
}
