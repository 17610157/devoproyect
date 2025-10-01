<?php

namespace App\Livewire;

use App\Models\Upload;
use Livewire\Component;
use Livewire\WithPagination;

class UploadsTable extends Component
{
    use WithPagination;

    public $search = '';
    protected $paginationTheme = 'bootstrap'; // funciona en v3 también

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function delete($id)
    {
        if ($u = Upload::find($id)) {
            // Si quieres también eliminar el archivo físico:
            // \Illuminate\Support\Facades\Storage::delete($u->file_path);
            $u->delete();
        }
        // No hace falta emitir nada; Livewire re-renderiza al terminar el método.
        // Si forzas: $this->resetPage(); // opcional
    }

    public function render()
    {
        $uploads = Upload::where('file_name', 'like', "%{$this->search}%")
            ->latest()
            ->paginate(10);

        return view('livewire.uploads-table', compact('uploads'));
    }
}
