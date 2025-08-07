<?php

namespace App\Services;

use App\Contracts\UserResolver;
use App\Models\UploadChunk;
use App\Models\UploadSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ChunkUploadService
{
    private UserResolver $userResolver;
    
    public function __construct(UserResolver $userResolver)
    {
        $this->userResolver = $userResolver;
    }

    /**
     * Initialize a new upload session
     * @param array<string, mixed> $data
     */
    public function initializeSession(array $data): UploadSession
    {
        $identifier = $data['resumableIdentifier'] ?? Str::uuid()->toString();
        
        // Check if session exists
        $existingSession = UploadSession::where('file_identifier', $identifier)->first();
        
        if ($existingSession) {
            // If session is completed or processing, create new one
            if (in_array($existingSession->status, ['completed', 'processing'])) {
                // Generate new unique identifier
                $identifier = Str::uuid()->toString();
            } else {
                // Reset existing incomplete session
                $existingSession->update([
                    'uploaded_chunks' => 0,
                    'status' => 'uploading'
                ]);
                // Delete old chunks
                $existingSession->chunks()->delete();
                return $existingSession;
            }
        }
        
        // Create new session (use firstOrCreate to handle race conditions)
        return UploadSession::firstOrCreate(
            ['file_identifier' => $identifier],
            [
                'filename' => $data['resumableFilename'],
                'total_size' => $data['resumableTotalSize'],
                'total_chunks' => $data['resumableTotalChunks'],
                'uploaded_chunks' => 0,
                'status' => 'uploading',
                'user_id' => $this->userResolver->getUserId(),
                'file_type' => $data['resumableType'] ?? 'application/zip',
                'metadata' => [
                    'original_name' => $data['resumableFilename'],
                    'relative_path' => $data['resumableRelativePath'] ?? '',
                ]
            ]
        );
    }

    /**
     * Check if a chunk has already been uploaded
     */
    public function chunkExists(string $identifier, int $chunkNumber): bool
    {
        $session = UploadSession::where('file_identifier', $identifier)->first();
        
        if (!$session) {
            return false;
        }
        
        return UploadChunk::where('session_id', $session->id)
            ->where('chunk_number', $chunkNumber)
            ->exists();
    }

    /**
     * Store an uploaded chunk
     * @param array<string, mixed> $data
     */
    public function storeChunk(array $data, UploadedFile $file): bool
    {
        $session = UploadSession::where('file_identifier', $data['resumableIdentifier'])->first();
        
        if (!$session) {
            return false;
        }
        
        // Check if chunk already exists
        if ($this->chunkExists($data['resumableIdentifier'], $data['resumableChunkNumber'])) {
            return true;
        }
        
        // Create chunk directory if it doesn't exist
        $chunkDir = config('upload.temp_path', storage_path('app/chunks')) . '/' . $session->file_identifier;
        if (!File::exists($chunkDir)) {
            File::makeDirectory($chunkDir, 0755, true, true);
        }
        
        // Store chunk file
        $chunkPath = $chunkDir . '/chunk_' . $data['resumableChunkNumber'];
        $file->move($chunkDir, 'chunk_' . $data['resumableChunkNumber']);
        
        // Save chunk record
        UploadChunk::create([
            'session_id' => $session->id,
            'chunk_number' => $data['resumableChunkNumber'],
            'chunk_path' => $chunkPath,
            'chunk_size' => $data['resumableCurrentChunkSize'],
            'checksum' => md5_file($chunkPath),
            'uploaded_at' => now()
        ]);
        
        // Update session
        $session->incrementUploadedChunks();
        
        return true;
    }

    /**
     * Assemble chunks into final file
     * @param UploadSession $session
     * @param callable|null $progressCallback
     */
    public function assembleChunks(UploadSession $session, ?callable $progressCallback = null): string
    {
        $chunks = $session->chunks()->orderBy('chunk_number')->get();
        $totalChunks = $chunks->count();
        
        // Create final file path
        $finalPath = storage_path('app/temp/') . $session->filename;
        $finalDir = dirname($finalPath);
        
        if (!File::exists($finalDir)) {
            File::makeDirectory($finalDir, 0755, true);
        }
        
        // Report progress: starting assembly
        if ($progressCallback) {
            $progressCallback(10, "Подготовка к сборке файла из {$totalChunks} частей...");
        }
        
        // Open final file for writing
        $finalFile = fopen($finalPath, 'wb');
        
        if (!$finalFile) {
            throw new \Exception('Could not create final file');
        }
        
        // Write each chunk to final file with progress
        $processedChunks = 0;
        foreach ($chunks as $chunk) {
            /** @var UploadChunk $chunk */
            $chunkPath = $chunk->chunk_path;
            $chunkData = file_get_contents($chunkPath);
            if ($chunkData !== false) {
                fwrite($finalFile, $chunkData);
            }
            
            $processedChunks++;
            
            // Calculate progress (10% to 30% for assembly)
            $assemblyProgress = 10 + (($processedChunks / $totalChunks) * 20);
            
            if ($progressCallback) {
                $progressCallback(
                    $assemblyProgress, 
                    "Сборка файла: собрано {$processedChunks} из {$totalChunks} частей..."
                );
            }
        }
        
        fclose($finalFile);
        
        // Report progress: verifying file
        if ($progressCallback) {
            $progressCallback(30, "Проверка целостности собранного файла...");
        }
        
        // Verify file size
        if (filesize($finalPath) !== $session->total_size) {
            unlink($finalPath);
            throw new \Exception('Assembled file size does not match expected size');
        }
        
        // Clean up chunks
        $this->cleanupChunks($session);
        
        return $finalPath;
    }

    /**
     * Clean up chunk files
     */
    public function cleanupChunks(UploadSession $session): void
    {
        $chunkDir = config('upload.temp_path', storage_path('app/chunks')) . '/' . $session->file_identifier;
        
        if (File::exists($chunkDir)) {
            File::deleteDirectory($chunkDir);
        }
        
        // Also try to clean by default path if config not set
        $defaultChunkDir = storage_path('app/chunks/' . $session->file_identifier);
        if (File::exists($defaultChunkDir)) {
            File::deleteDirectory($defaultChunkDir);
        }
        
        // Delete chunk records
        $session->chunks()->delete();
    }

    /**
     * Cancel upload and clean up
     */
    public function cancelUpload(string $identifier): bool
    {
        $session = UploadSession::where('file_identifier', $identifier)->first();
        
        if (!$session) {
            return false;
        }
        
        $this->cleanupChunks($session);
        $session->update(['status' => 'cancelled']);
        
        return true;
    }

    /**
     * Get upload progress
     * @return array<string, mixed>|null
     */
    public function getProgress(string $identifier): ?array
    {
        $session = UploadSession::where('file_identifier', $identifier)->first();
        
        if (!$session) {
            return null;
        }
        
        return [
            'progress' => $session->getProgress(),
            'uploaded_chunks' => $session->uploaded_chunks,
            'total_chunks' => $session->total_chunks,
            'status' => $session->status,
            'filename' => $session->filename,
            'total_size' => $session->total_size
        ];
    }

    /**
     * Clean up old incomplete uploads
     */
    public function cleanupOldUploads(): int
    {
        $cutoffTime = now()->subHours(config('upload.cleanup_after_hours', 24));
        
        $oldSessions = UploadSession::where('status', '!=', 'completed')
            ->where('created_at', '<', $cutoffTime)
            ->get();
        
        $count = 0;
        foreach ($oldSessions as $session) {
            $this->cleanupChunks($session);
            $session->delete();
            $count++;
        }
        
        return $count;
    }
}