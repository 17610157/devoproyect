@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Datos procesados del archivo: {{ $upload->file_name }}</h2>
    <a href="{{ route('uploads.index') }}" class="btn btn-secondary mb-3">← Volver</a>

    @if($upload->processedData->count() > 0)
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Producto</th>
                    <th>Descripción</th>
                    <th>Solicitado</th>
                    <th>Facturado</th>
                    <th>Faltante</th>
                    <th>Precio</th>
                    <th>Importe</th>
                    <th>Peso</th>
                    <th>Fecha disp.</th>
                    <th>Cambio fecha disp.</th>
                    <th>Comentarios</th>
                </tr>
            </thead>
            <tbody>
                @foreach($upload->processedData as $row)
                    <tr>
                        <td>{{ $row->no }}</td>
                        <td>{{ $row->producto }}</td>
                        <td>{{ $row->descripcion }}</td>
                        <td>{{ $row->solicitado }}</td>
                        <td>{{ $row->facturado }}</td>
                        <td>{{ $row->faltante }}</td>
                        <td>{{ $row->precio }}</td>
                        <td>{{ $row->importe }}</td>
                        <td>{{ $row->peso }}</td>
                        <td>{{ $row->fecha_disponibilidad }}</td>
                        <td>{{ $row->cambio_fecha_disponibilidad }}</td>
                        <td>{{ $row->comentarios }}</td>
                        <td>
    <a href="{{ route('uploads.data', $upload->id) }}" class="btn btn-sm btn-info">
        Ver datos
    </a>
</td>

                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="alert alert-info">Este archivo aún no tiene registros procesados.</div>
    @endif
</div>
@endsection
