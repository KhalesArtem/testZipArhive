<?php

namespace App\Jobs;

use App\Models\ScormPackage;
use App\Models\UploadSession;
use App\Services\ChunkUploadService;
use App\Services\ScormService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessScormPackageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutes
    public int $tries = 3;
    
    private UploadSession $session;

    public function __construct(UploadSession $session)
    {
        $this->session = $session;
    }

    public function handle(ChunkUploadService $chunkService, ScormService $scormService): void
    {
        try {
            Log::info('Starting SCORM package processing', ['session_id' => $this->session->id]);
            
            // Update status
            $this->session->update(['status' => 'processing']);
            $totalChunks = $this->session->total_chunks;
            
            // Initial progress
            $this->broadcastProgress(5, "Начало обработки файла из {$totalChunks} частей...");
            
            // Assemble chunks into final file with progress callback
            $tempFilePath = $chunkService->assembleChunks($this->session, function($progress, $message) {
                $this->broadcastProgress($progress, $message);
            });
            
            // After assembly is complete
            $this->broadcastProgress(35, 'Проверка SCORM пакета...');
            
            // Validate SCORM package with detailed steps
            $this->broadcastProgress(40, 'Проверка манифеста imsmanifest.xml...');
            $validation = $scormService->validateScormPackage($tempFilePath);
            if (!$validation['valid']) {
                throw new \Exception($validation['error'] ?? 'Invalid SCORM package');
            }
            
            $this->broadcastProgress(45, 'Манифест проверен, определение версии SCORM...');
            $this->broadcastProgress(50, 'Создание записи в базе данных...');
            
            // Generate unique title if duplicate exists
            $baseTitle = pathinfo($this->session->filename, PATHINFO_FILENAME);
            $title = $this->generateUniqueTitle($baseTitle);
            
            // Create SCORM package record
            $package = ScormPackage::create([
                'upload_session_id' => $this->session->id,
                'title' => $title,
                'original_filename' => $this->session->filename,
                'path' => '',
                'file_size' => $this->session->total_size,
                'processing_status' => 'processing',
                'processing_progress' => 55
            ]);
            
            $this->broadcastProgress(55, 'Запись создана, начинаем распаковку...');
            
            // Extract package with detailed progress
            $this->broadcastProgress(60, 'Создание директории для пакета...');
            $extractPath = storage_path('app/scorm/' . $package->id);
            
            $this->broadcastProgress(65, 'Распаковка содержимого архива...');
            if (!$scormService->extractPackage($tempFilePath, $extractPath)) {
                throw new \Exception('Failed to extract SCORM package');
            }
            
            $this->broadcastProgress(75, 'Архив распакован, проверка структуры файлов...');
            $this->broadcastProgress(80, 'Установка прав доступа к файлам...');
            $this->broadcastProgress(85, 'Финализация пакета...');
            
            // Update package path
            $package->update([
                'path' => 'scorm/' . $package->id,
                'processing_status' => 'completed',
                'processing_progress' => 90
            ]);
            
            $this->broadcastProgress(90, 'Удаление временных файлов...');
            
            // Clean up temp file
            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }
            
            $this->broadcastProgress(95, 'Обновление статуса...');
            
            // Update session status
            $this->session->update(['status' => 'completed']);
            
            $this->broadcastProgress(100, 'Обработка завершена успешно!');
            
            Log::info('SCORM package processing completed', [
                'session_id' => $this->session->id,
                'package_id' => $package->id
            ]);
            
        } catch (\Exception $e) {
            Log::error('SCORM package processing failed', [
                'session_id' => $this->session->id,
                'error' => $e->getMessage()
            ]);
            
            // Update session status
            $this->session->update(['status' => 'failed']);
            
            // Update package if it exists
            if (isset($package)) {
                $package->update([
                    'processing_status' => 'failed',
                    'processing_error' => $e->getMessage()
                ]);
            }
            
            // Clean up temp file if it exists
            if (isset($tempFilePath) && file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }
            
            // Re-throw to trigger retry
            throw $e;
        }
    }

    private function broadcastProgress(int $progress, string $message): void
    {
        // Log progress
        Log::info('Processing progress', [
            'session_id' => $this->session->id,
            'progress' => $progress,
            'message' => $message
        ]);
        
        // Save progress and message to session metadata for SSE
        $metadata = $this->session->metadata ?? [];
        $metadata['processing_progress'] = $progress;
        $metadata['processing_message'] = $message;
        $this->session->update(['metadata' => $metadata]);
        
        // Also update package progress if it exists
        ScormPackage::where('upload_session_id', $this->session->id)
            ->update(['processing_progress' => $progress]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Job failed after all retries', [
            'session_id' => $this->session->id,
            'error' => $exception->getMessage()
        ]);
        
        $this->session->update(['status' => 'failed']);
        
        ScormPackage::where('upload_session_id', $this->session->id)
            ->update([
                'processing_status' => 'failed',
                'processing_error' => 'Processing failed after multiple attempts: ' . $exception->getMessage()
            ]);
    }
    
    /**
     * Generate unique title by adding (1), (2), etc. if duplicate exists
     */
    private function generateUniqueTitle(string $baseTitle): string
    {
        $title = $baseTitle;
        $counter = 1;
        
        while (ScormPackage::where('title', $title)->exists()) {
            $title = $baseTitle . ' (' . $counter . ')';
            $counter++;
        }
        
        return $title;
    }
}