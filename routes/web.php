<?php

use App\Livewire\UploadDashboard;
use App\Http\Controllers\ReportController;

Route::get('/admin/uploads', UploadDashboard::class)->name('admin.uploads');
Route::get('/admin/report/negados/excel', [ReportController::class, 'negadosPivotExcel'])
    ->name('report.negados.excel');

