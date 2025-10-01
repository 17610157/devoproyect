<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\Upload;
use App\Models\UploadLog;
use App\Models\ProcessedData;
use Illuminate\Support\Facades\Storage;

#[Layout('layouts.admin')]
class UploadDashboard extends Component
{
    use WithPagination, WithFileUploads;

    // -------- PROPIEDADES --------
    public $search = '';
    public $perPage = 10;
    public $dateFrom = null;
    public $dateTo = null;
    public $statusFilter = null;

    /** @var array<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $files = [];

    public ?int $selectedUploadId = null;
    public bool $showDataModal = false;
    public bool $showErrorsModal = false;

    protected $paginationTheme = 'bootstrap';

    // -------- VALIDACIONES --------
    protected function rules(): array
    {
        return [
            'files.*' => 'file|mimes:xlsx,xls,csv|max:51200',
        ];
    }

    // -------- ACCIONES UI --------
    public function updatingSearch(): void
    {
        $this->resetPage('uploadsPage');
    }

    public function uploadFiles(): void
    {
        $this->validate();

        if (!Storage::exists('uploads')) {
            Storage::makeDirectory('uploads');
        }

        $queued = 0;
        $skipped = [];

        foreach ($this->files as $file) {
            $hash = hash_file('sha256', $file->getRealPath());

            // mismo archivo cargado hoy
            $alreadyToday = Upload::where('file_hash', $hash)
                ->whereDate('created_at', now()->toDateString())
                ->exists();
            if ($alreadyToday) {
                $skipped[] = $file->getClientOriginalName().' (ya cargado hoy)';
                continue;
            }

            // duplicado pendiente
            $existsPending = Upload::where('file_hash', $hash)
                ->where('status', 'PENDIENTE')
                ->exists();
            if ($existsPending) {
                $skipped[] = $file->getClientOriginalName().' (ya pendiente)';
                continue;
            }

            $path = $file->store('uploads');

            $upload = Upload::create([
                'user_id'   => auth()->id() ?? 1,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_hash' => $hash,
                'status'    => 'PENDIENTE',
            ]);

            \App\Jobs\ProcessExcelJob::dispatch($upload->id)
                ->onConnection('database')
                ->onQueue('default');

            $queued++;
        }

        $this->reset('files');

        $msg = "Encolados: {$queued}.";
        if ($skipped) {
            $msg .= ' Omitidos: '.implode(', ', $skipped);
        }
        session()->flash('success', $msg);
    }

    public function openDataModal(int $uploadId): void
    {
        $this->selectedUploadId = $uploadId;
        $this->showDataModal = true;
        $this->dispatch('open-modal', id: 'dataModal');
        $this->resetPage('dataPage');
    }

    public function closeDataModal(): void
    {
        $this->showDataModal = false;
        $this->dispatch('close-modal', id: 'dataModal');
    }

    public function openErrorsModal(int $uploadId): void
    {
        $this->selectedUploadId = $uploadId;
        $this->showErrorsModal = true;
        $this->dispatch('open-modal', id: 'errorsModal');
        $this->resetPage('logsPage');
    }

    public function closeErrorsModal(): void
    {
        $this->showErrorsModal = false;
        $this->dispatch('close-modal', id: 'errorsModal');
    }

    public function deleteUpload(int $id): void
    {
        if ($u = Upload::find($id)) {
            Storage::delete($u->file_path);
            $u->delete();
        }
        session()->flash('success', 'Carga eliminada.');
    }

    public function reprocess(int $id): void
    {
        if ($u = Upload::find($id)) {
            $u->update(['status' => 'PENDIENTE']);
            \App\Jobs\ProcessExcelJob::dispatch($u->id)
                ->onConnection('database')
                ->onQueue('default');
            session()->flash('success', 'Reprocesando archivo...');
        }
    }

    // -------- RENDER --------
    public function render()
    {
        $uploads = Upload::where('file_name', 'like', "%{$this->search}%")
            ->when($this->statusFilter, fn($q,$s) => $q->where('status',$s))
            ->when($this->dateFrom, fn($q,$d) => $q->whereDate('created_at','>=',$d))
            ->when($this->dateTo, fn($q,$d) => $q->whereDate('created_at','<=',$d))
            ->latest()
            ->paginate($this->perPage, ['*'], 'uploadsPage');

        $dataRecords = $this->showDataModal && $this->selectedUploadId
            ? ProcessedData::where('upload_id', $this->selectedUploadId)
                ->latest()
                ->paginate(10, ['*'], 'dataPage')
            : null;

        $logs = $this->showErrorsModal && $this->selectedUploadId
            ? UploadLog::where('upload_id', $this->selectedUploadId)
                ->latest()
                ->paginate(10, ['*'], 'logsPage')
            : null;

        return view('livewire.upload-dashboard', [
            'uploads'     => $uploads,
            'dataRecords' => $dataRecords,
            'logs'        => $logs,
        ]);
    }
    public function deleteAbsolutelyAll(): void
{
    foreach (\App\Models\Upload::cursor() as $u) {
        if ($u->file_path) \Storage::delete($u->file_path);
        $u->logs()->delete();
        $u->processedData()->delete();
        $u->delete();
    }
    $this->resetPage('uploadsPage');
    session()->flash('success', "Se eliminó todo el histórico de cargas.");
}

}
