<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessExcelJob;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    public function index()
    {
        $uploads = Upload::latest()->paginate(10);
        return view('uploads.index', compact('uploads'));
    }

    public function create()
    {
        return view('uploads.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'files'   => 'required',
            'files.*' => 'mimes:xlsx,xls,csv|max:51200', // 50MB por archivo
        ]);

        // user_id válido para la FK
        $userId = auth()->id() ?? User::value('id');
        if (!$userId) {
            $u = User::create([
                'name' => 'Bryan Ossmar',
                'email' => 'admin@admin.com',
                'password' => Hash::make('12345'),
            ]);
            $userId = $u->id;
        }

        $queued   = 0;
        $skipped  = 0;
        $skippedNames = [];

        foreach ($request->file('files', []) as $file) {
            $hash = hash_file('sha256', $file->getRealPath());

// 3.1 Rechazar si ya se subió HOY el mismo archivo (por hash)
$alreadyToday = \App\Models\Upload::where('file_hash', $hash)
    ->whereDate('created_at', now()->toDateString())
    ->exists();

if ($alreadyToday) {
    $skipped++;
    $skippedNames[] = $file->getClientOriginalName() . ' (ya cargado hoy)';
    continue;
}

// 3.2 (opcional) Evitar duplicado pendiente
$existsPending = \App\Models\Upload::where('file_hash', $hash)->where('status', 'PENDIENTE')->exists();
if ($existsPending) {
    $skipped++;
    $skippedNames[] = $file->getClientOriginalName() . ' (ya pendiente)';
    continue;
}
            // Crear carpeta uploads si no existe
            if (!Storage::exists('uploads')) {
                Storage::makeDirectory('uploads');
            }

            // Guardar archivo en storage/app/uploads
            $path = $file->store('uploads');

            // Crear registro en DB
            $upload = Upload::create([
                'user_id'   => $userId,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path, // Ejemplo: "uploads/xxxxx.xlsx"
                'file_hash' => $hash,
                'status'    => 'PENDIENTE',
            ]);

            // Encolar el Job con el ID (no el modelo completo)
            ProcessExcelJob::dispatch($upload->id)
                ->onConnection('database')
                ->onQueue('default');

            $queued++;
        }

        $msg  = "Encolados: {$queued}.";
        if ($skipped > 0) {
            $msg .= " Saltados por duplicado: {$skipped} (" . implode(', ', $skippedNames) . ").";
        }

        return redirect()->route('uploads.index')->with('success', $msg);
    }
    public function showData($id)
{
    $upload = \App\Models\Upload::with('processedData')->findOrFail($id);

    return view('uploads.data', compact('upload'));
}

}
