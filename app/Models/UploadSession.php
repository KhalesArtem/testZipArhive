<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UploadSession extends Model
{
    protected $fillable = [
        'file_identifier',
        'filename',
        'total_size',
        'total_chunks',
        'uploaded_chunks',
        'status',
        'user_id',
        'file_type',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'total_size' => 'integer',
        'total_chunks' => 'integer',
        'uploaded_chunks' => 'integer',
        'user_id' => 'integer'
    ];

    public function chunks(): HasMany
    {
        return $this->hasMany(UploadChunk::class, 'session_id');
    }

    public function isComplete(): bool
    {
        return $this->uploaded_chunks === $this->total_chunks;
    }

    public function getProgress(): float
    {
        if ($this->total_chunks === 0) {
            return 0;
        }
        return ($this->uploaded_chunks / $this->total_chunks) * 100;
    }

    public function incrementUploadedChunks(): void
    {
        $this->increment('uploaded_chunks');
        // Status will be updated by controller when dispatching job
    }
}