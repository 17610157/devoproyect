<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UploadLog extends Model {
    protected $fillable = ['upload_id','row_number','column_name','error_message','raw_data'];

    public function upload() {
        return $this->belongsTo(Upload::class);
    }
}
