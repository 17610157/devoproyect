<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Upload extends Model
{
    protected $fillable = [
        'user_id',
        'file_name',
        'file_path',
        'file_hash',
        'status',
        'total_rows',
        'success_rows',
        'error_rows'
    ];

    public function logs()
    {
        return $this->hasMany(UploadLog::class);
    }

    public function processedData()
    {
        return $this->hasMany(ProcessedData::class, 'upload_id');
    }
}
