@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Subir Archivos Excel</h2>
    <a href="{{ route('uploads.index') }}" class="btn btn-secondary mb-3">← Volver al Dashboard</a>

    {{-- Mensajes de éxito --}}
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    {{-- Errores de validación --}}
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('uploads.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="mb-3">
            <label for="files" class="form-label">Seleccionar archivos Excel:</label>
            <input type="file" name="files[]" id="files" class="form-control" accept=".xlsx,.xls,.csv" multiple required>
            <div class="form-text">Solo se permiten archivos .xlsx, .xls o .csv (máx. 50MB cada uno).</div>
        </div>

        {{-- Preview de archivos seleccionados --}}
        <div class="mb-3" id="preview-container" style="display:none;">
            <h5>Archivos seleccionados:</h5>
            <ul class="list-group" id="file-list"></ul>
        </div>

        <button type="submit" class="btn btn-success">Cargar</button>
    </form>
</div>

{{-- Script para mostrar preview de archivos --}}
<script>
document.getElementById('files').addEventListener('change', function (e) {
    const previewContainer = document.getElementById('preview-container');
    const fileList = document.getElementById('file-list');
    fileList.innerHTML = '';

    if (this.files.length > 0) {
        previewContainer.style.display = 'block';
        Array.from(this.files).forEach(file => {
            const li = document.createElement('li');
            li.classList.add('list-group-item');
            li.textContent = `${file.name} (${(file.size / 1024).toFixed(2)} KB)`;
            fileList.appendChild(li);
        });
    } else {
        previewContainer.style.display = 'none';
    }
});
</script>
@endsection
