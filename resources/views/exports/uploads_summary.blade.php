{{-- resources/views/exports/uploads_summary.blade.php --}}
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body{ font-family: DejaVu Sans, sans-serif; font-size:12px; }
        h3{ margin:0 0 8px; }
        table{ width:100%; border-collapse: collapse; }
        th, td{ border:1px solid #999; padding:6px 8px; }
        th{ background:#eee; }
        .small{ color:#666; font-size:11px; }
    </style>
</head>
<body>
    <h3>Resumen de cargas</h3>
    <div class="small">
        @if(!empty($filters['date_from'])) Desde: {{ $filters['date_from'] }} @endif
        @if(!empty($filters['date_to'])) &nbsp;Hasta: {{ $filters['date_to'] }} @endif
        @if(!empty($filters['status'])) &nbsp;Estatus: {{ $filters['status'] }} @endif
        @if(!empty($filters['search'])) &nbsp;Buscar: “{{ $filters['search'] }}” @endif
    </div>

    <table>
        <thead>
        <tr>
            <th>Archivo</th>
            <th>Estatus</th>
            <th>Correctos</th>
            <th>Errores</th>
            <th>Total</th>
            <th>Creado en</th>
        </tr>
        </thead>
        <tbody>
        @forelse($rows as $u)
            <tr>
                <td>{{ $u->file_name }}</td>
                <td>{{ $u->status }}</td>
                <td>{{ $u->success_rows }}</td>
                <td>{{ $u->error_rows }}</td>
                <td>{{ $u->total_rows }}</td>
                <td>{{ optional($u->created_at)->format('Y-m-d H:i') }}</td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align:center;">Sin resultados</td></tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>
