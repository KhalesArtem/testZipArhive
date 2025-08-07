<?php

use App\Http\Controllers\ScormController;
use App\Http\Controllers\ChunkUploadController;
use App\Http\Controllers\SseController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ScormController::class, 'index'])->name('scorm.index');
Route::get('/upload', [ScormController::class, 'create'])->name('scorm.create');
Route::post('/upload', [ScormController::class, 'store'])->name('scorm.store');
Route::get('/scorm/{id}', [ScormController::class, 'show'])->name('scorm.show');
Route::delete('/scorm/{id}', [ScormController::class, 'destroy'])->name('scorm.destroy');
Route::get('/scorm/{id}/content/{path}', [ScormController::class, 'serveContent'])
    ->where('path', '.*')
    ->name('scorm.content');

// Chunked upload routes
Route::prefix('api/upload')->group(function () {
    Route::get('/chunk', [ChunkUploadController::class, 'testChunk'])->name('upload.test');
    Route::post('/chunk', [ChunkUploadController::class, 'uploadChunk'])->name('upload.chunk');
    Route::get('/progress/{identifier}', [ChunkUploadController::class, 'getProgress'])->name('upload.progress');
    Route::post('/cancel', [ChunkUploadController::class, 'cancelUpload'])->name('upload.cancel');
    Route::get('/status/{sessionId}', [ChunkUploadController::class, 'getProcessingStatus'])->name('upload.status');
    Route::get('/stream/{sessionId}', [SseController::class, 'streamProgress'])->name('upload.stream');
});