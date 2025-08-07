<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScormPackage extends Model
{
    protected $fillable = [
        'title',
        'original_filename',
        'path',
        'file_size',
        'upload_session_id',
        'processing_status',
        'processing_progress',
        'processing_error'
    ];

    public function stats(): HasMany
    {
        return $this->hasMany(ScormUserStat::class);
    }

    public function getUserStats(int $userId = 1): ?ScormUserStat
    {
        /** @var ScormUserStat|null */
        return $this->stats()->where('user_id', $userId)->first();
    }
}