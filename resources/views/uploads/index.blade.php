@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Dashboard de Cargas</h2>
    <a href="{{ route('uploads.create') }}" class="btn btn-primary mb-3">Subir Archivos</a>
    <livewire:uploads-table />
</div>
@endsection
