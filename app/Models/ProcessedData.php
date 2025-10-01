<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcessedData extends Model
{
    protected $table = 'processed_data';
    protected $fillable = [
        'upload_id','no','producto','descripcion',
        'solicitado','facturado','faltante',
        'precio','importe','peso',
        'fecha_disponibilidad','cambio_fecha_disponibilidad',
        'comentarios',
    ];

    public function upload(){ return $this->belongsTo(Upload::class); }
}
