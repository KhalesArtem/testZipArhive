<?php

namespace App\Jobs;

use App\Services\ChunkUploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupOldUploadsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ChunkUploadService $chunkService): void
    {
        $count = $chunkService->cleanupOldUploads();
        
        if ($count > 0) {
            Log::info('Cleaned up old uploads', ['count' => $count]);
        }
    }
}