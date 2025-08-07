<?php

namespace App\Http\Controllers;

use App\Models\ScormPackage;
use App\Models\UploadSession;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SseController extends Controller
{
    /**
     * Stream processing progress via Server-Sent Events
     */
    public function streamProgress(int $sessionId): StreamedResponse
    {
        return response()->stream(function() use ($sessionId) {
            // Set headers for SSE
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('X-Accel-Buffering: no');
            
            $lastProgress = -1;
            $attempts = 0;
            $maxAttempts = 120; // 2 minutes max (120 * 1 second)
            
            while ($attempts < $maxAttempts) {
                $session = UploadSession::find($sessionId);
                
                if (!$session) {
                    $this->sendEvent('error', ['message' => 'Session not found']);
                    break;
                }
                
                // Check if there's a SCORM package for this session
                $package = ScormPackage::where('upload_session_id', $sessionId)->first();
                
                if ($package) {
                    $progress = $package->processing_progress;
                    $status = $package->processing_status;
                    
                    // Only send if progress changed
                    if ($progress !== $lastProgress) {
                        $this->sendEvent('progress', [
                            'progress' => $progress,
                            'status' => $status,
                            'message' => $this->getProgressMessage($progress)
                        ]);
                        $lastProgress = $progress;
                    }
                    
                    // Check if processing is complete
                    if ($status === 'completed') {
                        $this->sendEvent('complete', [
                            'package_id' => $package->id,
                            'redirect_url' => route('scorm.show', $package->id)
                        ]);
                        break;
                    }
                    
                    // Check if processing failed
                    if ($status === 'failed') {
                        $this->sendEvent('error', [
                            'message' => $package->processing_error ?? 'Processing failed'
                        ]);
                        break;
                    }
                } else {
                    // Package not created yet, check processing progress in session metadata
                    $metadata = $session->metadata ?? [];
                    if (isset($metadata['processing_progress'])) {
                        $progress = $metadata['processing_progress'];
                        $message = $metadata['processing_message'] ?? '';
                        
                        if ($progress !== $lastProgress) {
                            $this->sendEvent('progress', [
                                'progress' => $progress,
                                'status' => 'processing',
                                'message' => $message
                            ]);
                            $lastProgress = $progress;
                        }
                    } else {
                        // Send upload progress
                        $uploadProgress = $session->getProgress();
                        if ($uploadProgress !== $lastProgress) {
                            $this->sendEvent('upload', [
                                'progress' => $uploadProgress,
                                'status' => $session->status
                            ]);
                            $lastProgress = $uploadProgress;
                        }
                    }
                }
                
                // Flush output
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
                
                // Wait before next check
                sleep(1);
                $attempts++;
            }
            
            // Timeout reached
            if ($attempts >= $maxAttempts) {
                $this->sendEvent('timeout', ['message' => 'Processing timeout']);
            }
            
        }, 200, [
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'text/event-stream',
        ]);
    }
    
    /**
     * @param array<string, mixed> $data
     */
    private function sendEvent(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo "data: " . json_encode($data) . "\n\n";
        
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
    
    private function getProgressMessage(int $progress): string
    {
        if ($progress < 10) return 'Инициализация...';
        if ($progress < 30) return 'Сборка файла из частей...';
        if ($progress < 50) return 'Валидация SCORM пакета...';
        if ($progress < 70) return 'Распаковка содержимого...';
        if ($progress < 90) return 'Финализация...';
        if ($progress >= 100) return 'Обработка завершена!';
        return 'Обработка...';
    }
}