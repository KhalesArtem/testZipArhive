<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessScormPackageJob;
use App\Models\ScormPackage;
use App\Models\UploadSession;
use App\Services\ChunkUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChunkUploadController extends Controller
{
    private ChunkUploadService $chunkService;
    
    public function __construct(ChunkUploadService $chunkService)
    {
        $this->chunkService = $chunkService;
    }

    /**
     * Test if chunk exists (GET request from resumable.js)
     */
    public function testChunk(Request $request): JsonResponse
    {
        $identifier = $request->input('resumableIdentifier');
        $chunkNumber = (int) $request->input('resumableChunkNumber');
        
        // Return not found if identifier is missing
        if (!$identifier) {
            return response()->json(['status' => 'not_found'], 204);
        }
        
        // Check if session is completed or processing - if so, treat as new upload
        $session = UploadSession::where('file_identifier', $identifier)->first();
        if ($session && ($session->status === 'completed' || $session->status === 'processing')) {
            return response()->json(['status' => 'not_found'], 204);
        }
        
        if ($this->chunkService->chunkExists($identifier, $chunkNumber)) {
            return response()->json(['status' => 'found'], 200);
        }
        
        return response()->json(['status' => 'not_found'], 204);
    }

    /**
     * Upload a chunk (POST request from resumable.js)
     */
    public function uploadChunk(Request $request): JsonResponse
    {
        // Validate request
        if (!$request->hasFile('file')) {
            return response()->json(['error' => 'No file provided'], 400);
        }
        
        $data = $request->all();
        $file = $request->file('file');
        
        // Initialize session if needed
        $session = UploadSession::where('file_identifier', $data['resumableIdentifier'])->first();
        if (!$session) {
            $session = $this->chunkService->initializeSession($data);
        }
        
        // Store chunk
        if ($this->chunkService->storeChunk($data, $file)) {
            // Check if upload is complete
            $session->refresh();
            if ($session->isComplete() && $session->status === 'uploading') {
                // Update status and dispatch job
                $session->update(['status' => 'processing']);
                ProcessScormPackageJob::dispatch($session);
                
                return response()->json([
                    'status' => 'complete',
                    'session_id' => $session->id
                ], 200);
            }
            
            return response()->json([
                'status' => 'uploaded',
                'progress' => $session->getProgress()
            ], 200);
        }
        
        return response()->json(['error' => 'Failed to store chunk'], 500);
    }

    /**
     * Get upload progress
     */
    public function getProgress(string $identifier): JsonResponse
    {
        $progress = $this->chunkService->getProgress($identifier);
        
        if (!$progress) {
            return response()->json(['error' => 'Upload session not found'], 404);
        }
        
        return response()->json($progress);
    }

    /**
     * Cancel upload
     */
    public function cancelUpload(Request $request): JsonResponse
    {
        $identifier = $request->input('identifier');
        
        if ($this->chunkService->cancelUpload($identifier)) {
            return response()->json(['status' => 'cancelled'], 200);
        }
        
        return response()->json(['error' => 'Upload session not found'], 404);
    }

    /**
     * Get processing status for a completed upload
     */
    public function getProcessingStatus(int $sessionId): JsonResponse
    {
        $session = UploadSession::find($sessionId);
        
        if (!$session) {
            return response()->json(['error' => 'Session not found'], 404);
        }
        
        // Find related SCORM package
        $package = ScormPackage::where('upload_session_id', $sessionId)->first();
        
        if ($package) {
            return response()->json([
                'status' => $package->processing_status,
                'progress' => $package->processing_progress,
                'error' => $package->processing_error,
                'package_id' => $package->id
            ]);
        }
        
        return response()->json([
            'status' => $session->status,
            'progress' => 0
        ]);
    }
}