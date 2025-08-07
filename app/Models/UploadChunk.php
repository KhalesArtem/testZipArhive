<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UploadChunk extends Model
{
    public $timestamps = false;
    
    protected $fillable = [
        'session_id',
        'chunk_number',
        'chunk_path',
        'chunk_size',
        'checksum',
        'uploaded_at'
    ];

    protected $casts = [
        'chunk_number' => 'integer',
        'chunk_size' => 'integer',
        'uploaded_at' => 'datetime'
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(UploadSession::class, 'session_id');
    }
}