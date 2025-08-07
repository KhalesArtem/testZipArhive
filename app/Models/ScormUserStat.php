<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScormUserStat extends Model
{
    protected $fillable = [
        'user_id',
        'scorm_package_id',
        'views_count',
        'last_viewed_at'
    ];

    protected $casts = [
        'last_viewed_at' => 'datetime'
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(ScormPackage::class, 'scorm_package_id');
    }

    public function incrementViews(): void
    {
        $this->increment('views_count');
        $this->update(['last_viewed_at' => now()]);
    }
}