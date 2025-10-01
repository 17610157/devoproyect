@extends('layouts.app')

@section('content')
<div class="container">
    <h4>Errores de carga</h4>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Fila</th>
                <th>Columna</th>
                <th>Error</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
                <tr>
                    <td>{{ $log->row_number }}</td>
                    <td>{{ $log->column_name ?? '-' }}</td>
                    <td>{{ $log->error_message }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">No se registraron errores.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
