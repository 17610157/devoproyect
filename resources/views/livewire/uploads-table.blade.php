<div wire:poll.5s>
    {{-- Header estilo Admin --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h3 class="mb-0">Panel de Cargas</h3>
        <div>
            <select class="form-select d-inline-block w-auto me-2" wire:change="setPerPage($event.target.value)">
                <option value="10" @selected($perPage==10)>10</option>
                <option value="25" @selected($perPage==25)>25</option>
                <option value="50" @selected($perPage==50)>50</option>
            </select>
            <input type="text" wire:model.debounce.400ms="search" class="form-control d-inline-block w-auto"
                   placeholder="Buscar archivo...">
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="alert alert-success py-2">{{ session('success') }}</div>
    @endif
    @error('files.*')
        <div class="alert alert-danger py-2">{{ $message }}</div>
    @enderror

    {{-- Tarjetas resumen --}}
    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted">En cola</div>
                            <div class="h4 mb-0">
                                {{ \App\Models\Upload::whereIn('status',['PENDIENTE','PROCESANDO'])->count() }}
                            </div>
                        </div>
                        <i class="bi bi-cloud-arrow-up fs-1 text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted">Completados</div>
                            <div class="h4 mb-0">
                                {{ \App\Models\Upload::where('status','COMPLETADO')->count() }}
                            </div>
                        </div>
                        <i class="bi bi-check2-circle fs-1 text-success"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted">Con errores</div>
                            <div class="h4 mb-0">
                                {{ \App\Models\Upload::whereIn('status',['ERROR','CARGADO_CON_ERRORES'])->count() }}
                            </div>
                        </div>
                        <i class="bi bi-exclamation-triangle fs-1 text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Carga de archivos (todo en esta misma página) --}}
    <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold">Subir archivos Excel</div>
        <div class="card-body">
            <input type="file" wire:model="files" multiple accept=".xlsx,.xls,.csv" class="form-control mb-2">
            @if(!empty($files))
                <div class="small text-muted mb-2">Seleccionados:</div>
                <ul class="list-group list-group-flush mb-2">
                    @foreach($files as $f)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>{{ $f->getClientOriginalName() }}</span>
                            <span class="text-muted">{{ number_format($f->getSize()/1024, 2) }} KB</span>
                        </li>
                    @endforeach
                </ul>
            @endif
            <button class="btn btn-primary" wire:click="uploadFiles" @disabled(empty($files))>
                <i class="bi bi-upload"></i> Enviar y procesar
            </button>
        </div>
    </div>

    {{-- Tabla principal --}}
    <div class="card shadow-sm">
        <div class="card-header fw-semibold">Historial de cargas</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 align-middle">
                    <thead class="table-light">
                    <tr>
                        <th>Archivo</th>
                        <th>Estatus</th>
                        <th>Correctos</th>
                        <th>Errores</th>
                        <th>Total</th>
                        <th>Fecha</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($uploads as $u)
                        <tr>
                            <td class="text-truncate" style="max-width:260px">{{ $u->file_name }}</td>
                            <td>
                                @php $s=$u->status; @endphp
                                <span class="badge
                                    {{ $s==='PENDIENTE'?'bg-secondary':'' }}
                                    {{ $s==='PROCESANDO'?'bg-warning text-dark':'' }}
                                    {{ $s==='COMPLETADO'?'bg-success':'' }}
                                    {{ $s==='ERROR'?'bg-danger':'' }}
                                    {{ $s==='CARGADO_CON_ERRORES'?'bg-info text-dark':'' }}
                                ">{{ $u->status }}</span>
                            </td>
                            <td>{{ $u->success_rows }}</td>
                            <td>{{ $u->error_rows }}</td>
                            <td>{{ $u->total_rows }}</td>
                            <td>{{ optional($u->created_at)->format('Y-m-d H:i') }}</td>
                            <td class="text-end">
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-info"
                                            wire:click="openDataModal({{ $u->id }})">
                                        <i class="bi bi-table"></i> Datos
                                    </button>
                                    <button class="btn btn-sm btn-outline-warning"
                                            wire:click="openErrorsModal({{ $u->id }})">
                                        <i class="bi bi-bug"></i> Errores
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary"
                                            wire:click="reprocess({{ $u->id }})">
                                        <i class="bi bi-arrow-repeat"></i> Reprocesar
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger"
                                            wire:click="deleteUpload({{ $u->id }})">
                                        <i class="bi bi-trash"></i> Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-4">No hay cargas registradas</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            {{ $uploads->links() }}
        </div>
    </div>

    {{-- Modal: Datos procesados --}}
    <div class="modal fade" id="dataModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Datos procesados</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Close" wire:click="closeDataModal"></button>
                </div>
                <div class="modal-body p-0">
                    @if($showDataModal && $selectedUploadId && $dataRecords)
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0">
                                <thead class="table-light">
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
                                    <th>Cambio fecha</th>
                                    <th>Comentarios</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($dataRecords as $r)
                                    <tr>
                                        <td>{{ $r->no }}</td>
                                        <td>{{ $r->producto }}</td>
                                        <td class="text-truncate" style="max-width:240px">{{ $r->descripcion }}</td>
                                        <td>{{ $r->solicitado }}</td>
                                        <td>{{ $r->facturado }}</td>
                                        <td>{{ $r->faltante }}</td>
                                        <td>{{ $r->precio }}</td>
                                        <td>{{ $r->importe }}</td>
                                        <td>{{ $r->peso }}</td>
                                        <td>{{ $r->fecha_disponibilidad }}</td>
                                        <td>{{ $r->cambio_fecha_disponibilidad }}</td>
                                        <td class="text-truncate" style="max-width:240px">{{ $r->comentarios }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-3 text-muted">Sin datos.</div>
                    @endif
                </div>
                <div class="modal-footer">
                    @if($dataRecords) {{ $dataRecords->links() }} @endif
                    <button class="btn btn-secondary" data-bs-dismiss="modal" wire:click="closeDataModal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal: Errores --}}
    <div class="modal fade" id="errorsModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-lg  modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Errores de carga</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Close" wire:click="closeErrorsModal"></button>
                </div>
                <div class="modal-body p-0">
                    @if($showErrorsModal && $selectedUploadId && $logs)
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>Fila</th>
                                    <th>Columna</th>
                                    <th>Error</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($logs as $e)
                                    <tr>
                                        <td>{{ $e->row_number }}</td>
                                        <td>{{ $e->column_name ?? '-' }}</td>
                                        <td class="text-truncate" style="max-width:520px">{{ $e->error_message }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-3 text-muted">Sin errores registrados.</div>
                    @endif
                </div>
                <div class="modal-footer">
                    @if($logs) {{ $logs->links() }} @endif
                    <button class="btn btn-secondary" data-bs-dismiss="modal" wire:click="closeErrorsModal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Control de modales Bootstrap --}}
<script>
    window.addEventListener('open-modal', (e) => {
        const id = e.detail.
