@extends('layouts.app')

@section('title', 'Загрузка пакета')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Загрузка пакета </h5>
            </div>
            <div class="card-body">
                <div id="upload-area" class="border-dashed border-3 p-5 text-center mb-3" style="border-style: dashed; background: #f8f9fa;">
                    <i class="bi bi-cloud-upload" style="font-size: 3rem;"></i>
                    <h4>Перетащите файл сюда</h4>
                    <p class="text-muted">или нажмите для выбора файла</p>
                    <input type="file" id="fileInput" accept=".zip" style="display: none;">
                    <label for="fileInput" class="btn btn-primary" style="cursor: pointer;">
                        <i class="bi bi-folder-open"></i> Выбрать файл
                    </label>
                </div>

                <div id="file-info" style="display: none;">
                    <div class="mb-3">
                        <label class="form-label">Название пакета</label>
                        <input type="text" id="packageTitle" class="form-control" placeholder="Введите название">
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>Файл:</strong> <span id="fileName"></span><br>
                        <strong>Размер:</strong> <span id="fileSize"></span>
                    </div>
                </div>

                <div id="upload-progress" style="display: none;">
                    <div class="mb-2">
                        <div class="d-flex justify-content-between">
                            <span>Загрузка: <span id="progressPercent">0%</span></span>
                            <span id="uploadSpeed"></span>
                        </div>
                    </div>
                    <div class="progress mb-3" style="height: 25px;">
                        <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" 
                             role="progressbar" style="width: 0%">
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button id="pauseBtn" class="btn btn-warning">
                            <i class="bi bi-pause"></i> Пауза
                        </button>
                        <button id="resumeBtn" class="btn btn-success" style="display: none;">
                            <i class="bi bi-play"></i> Продолжить
                        </button>
                        <button id="cancelBtn" class="btn btn-danger">
                            <i class="bi bi-x-circle"></i> Отмена
                        </button>
                    </div>
                </div>

                <div id="processing-status" style="display: none;">
                    <div class="alert alert-info">
                        <h5><i class="bi bi-gear-fill spin"></i> Обработка файла...</h5>
                        <p id="processingMessage" class="mb-0">Файл загружен, идет обработка SCORM пакета...</p>
                        <div id="chunkProgress" class="small text-muted mt-2"></div>
                    </div>
                    <div class="progress" style="height: 30px;">
                        <div id="processingBar" class="progress-bar bg-success progress-bar-striped progress-bar-animated" 
                             role="progressbar" style="width: 0%; transition: width 0.3s ease;">
                            <span id="processingPercent" style="line-height: 30px; font-size: 14px;"></span>
                        </div>
                    </div>
                    <div class="mt-2 small text-muted">
                        <div class="row">
                            <div class="col-3"><i class="bi bi-collection"></i> Сборка: 10-30%</div>
                            <div class="col-3"><i class="bi bi-check-circle"></i> Проверка: 30-50%</div>
                            <div class="col-3"><i class="bi bi-archive"></i> Распаковка: 50-80%</div>
                            <div class="col-3"><i class="bi bi-database"></i> Сохранение: 80-100%</div>
                        </div>
                    </div>
                </div>

                <div id="upload-complete" style="display: none;">
                    <div class="alert alert-success">
                        <h5><i class="bi bi-check-circle"></i> Загрузка завершена!</h5>
                        <p>SCORM пакет успешно загружен и обработан.</p>
                        <a href="{{ route('scorm.index') }}" class="btn btn-primary">
                            <i class="bi bi-list"></i> К списку пакетов
                        </a>
                    </div>
                </div>

                <div id="upload-error" style="display: none;">
                    <div class="alert alert-danger">
                        <h5><i class="bi bi-exclamation-triangle"></i> Ошибка загрузки</h5>
                        <p id="errorMessage"></p>
                        <button id="retryBtn" class="btn btn-primary">
                            <i class="bi bi-arrow-clockwise"></i> Попробовать снова
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.spin {
    display: inline-block;
    animation: spin 2s linear infinite;
}
</style>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/resumablejs@1.1.0/resumable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const uploadArea = document.getElementById('upload-area');
    const fileInput = document.getElementById('fileInput');
    const fileInfo = document.getElementById('file-info');
    const uploadProgress = document.getElementById('upload-progress');
    const processingStatus = document.getElementById('processing-status');
    const uploadComplete = document.getElementById('upload-complete');
    const uploadError = document.getElementById('upload-error');
    
    let currentSessionId = null;
    let processingInterval = null;
    let totalChunks = 0;
    let uploadedChunks = 0;
    
    // Initialize Resumable.js
    const r = new Resumable({
        target: '/api/upload/chunk',
        testTarget: '/api/upload/chunk',
        chunkSize: 5 * 1024 * 1024, // 5MB chunks
        simultaneousUploads: 3,
        testChunks: true,
        throttleProgressCallbacks: 1,
        method: 'multipart',
        uploadMethod: 'POST',
        testMethod: 'GET',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        query: {
            _token: '{{ csrf_token() }}'
        },
        generateUniqueIdentifier: function(file) {
            // Add timestamp to make identifier unique for duplicate files
            const timestamp = Date.now();
            const relativePath = file.relativePath || file.fileName || '';
            return file.size + '-' + relativePath.replace(/[^0-9a-zA-Z_-]/img, '') + '-' + timestamp;
        }
    });
    
    if (!r.support) {
        alert('Ваш браузер не поддерживает resumable uploads!');
        return;
    }
    
    // Assign drop zone and browse
    r.assignDrop(uploadArea);
    r.assignBrowse(document.getElementById('fileInput'));
    
    // File added
    r.on('fileAdded', function(file) {
        // Calculate total chunks
        totalChunks = Math.ceil(file.size / (5 * 1024 * 1024));
        uploadedChunks = 0;
        
        // Show file info
        document.getElementById('fileName').textContent = file.fileName;
        document.getElementById('fileSize').textContent = formatBytes(file.size);
        fileInfo.style.display = 'block';
        
        // Auto-fill title
        const title = file.fileName.replace('.zip', '');
        document.getElementById('packageTitle').value = title;
        
        // Start upload automatically
        setTimeout(() => {
            r.upload();
        }, 500);
    });
    
    // Upload started
    r.on('uploadStart', function() {
        uploadProgress.style.display = 'block';
        fileInfo.style.display = 'none';
    });
    
    // Chunk success
    r.on('chunkingComplete', function(file, message) {
        uploadedChunks++;
        updateChunkProgress();
    });
    
    // Progress update
    r.on('progress', function() {
        const progress = Math.floor(r.progress() * 100);
        document.getElementById('progressPercent').textContent = progress + '%';
        document.getElementById('progressBar').style.width = progress + '%';
        
        // Update chunk info
        uploadedChunks = Math.floor((progress / 100) * totalChunks);
        updateChunkProgress();
        
        // Calculate speed (simplified)
        const speed = r.files.length > 0 ? formatBytes(r.files[0].averageSpeed || 0) + '/s' : '';
        document.getElementById('uploadSpeed').textContent = speed;
    });
    
    function updateChunkProgress() {
        if (totalChunks > 0) {
            const chunkInfo = `Загружено частей: ${uploadedChunks} из ${totalChunks}`;
            document.getElementById('chunkProgress').textContent = chunkInfo;
        }
    }
    
    // File upload success
    r.on('fileSuccess', function(file, message) {
        try {
            const response = JSON.parse(message);
            if (response.status === 'complete' && response.session_id) {
                currentSessionId = response.session_id;
                showProcessingStatus();
                startProcessingMonitor();
            }
        } catch (e) {
            console.error('Error parsing response:', e);
        }
    });
    
    // Upload complete
    r.on('complete', function() {
        if (!currentSessionId) {
            uploadProgress.style.display = 'none';
        }
    });
    
    // Error handling
    r.on('error', function(message, file) {
        showError('Ошибка загрузки: ' + message);
    });
    
    // Pause/Resume buttons
    document.getElementById('pauseBtn').addEventListener('click', function() {
        r.pause();
        this.style.display = 'none';
        document.getElementById('resumeBtn').style.display = 'inline-block';
    });
    
    document.getElementById('resumeBtn').addEventListener('click', function() {
        r.upload();
        this.style.display = 'none';
        document.getElementById('pauseBtn').style.display = 'inline-block';
    });
    
    // Cancel button
    document.getElementById('cancelBtn').addEventListener('click', function() {
        if (confirm('Отменить загрузку?')) {
            r.cancel();
            resetUpload();
        }
    });
    
    // Retry button
    document.getElementById('retryBtn').addEventListener('click', function() {
        resetUpload();
        r.upload();
    });
    
    // Processing monitor using Server-Sent Events
    function startProcessingMonitor() {
        let isCompleted = false;
        let currentProgress = 0;
        let targetProgress = 0;
        let animationFrame = null;
        
        // Smooth progress animation
        function animateProgress() {
            if (currentProgress < targetProgress) {
                currentProgress += 0.5; // Smooth increment
                if (currentProgress > targetProgress) {
                    currentProgress = targetProgress;
                }
                document.getElementById('processingBar').style.width = currentProgress + '%';
                document.getElementById('processingPercent').textContent = Math.floor(currentProgress) + '%';
                animationFrame = requestAnimationFrame(animateProgress);
            }
        }
        
        // Use SSE for real-time updates
        const eventSource = new EventSource(`/api/upload/stream/${currentSessionId}`);
        
        eventSource.addEventListener('progress', function(event) {
            const data = JSON.parse(event.data);
            targetProgress = data.progress;
            
            // Start smooth animation to target progress
            if (!animationFrame) {
                animateProgress();
            }
            
            if (data.message) {
                document.getElementById('processingMessage').textContent = data.message;
                
                // Add detailed chunk info if in assembly phase
                if (data.message.includes('Сборка файла:')) {
                    document.getElementById('chunkProgress').textContent = data.message;
                }
            }
        });
        
        eventSource.addEventListener('complete', function(event) {
            const data = JSON.parse(event.data);
            isCompleted = true;
            eventSource.close();
            showComplete();
        });
        
        eventSource.addEventListener('error', function(event) {
            eventSource.close();
            if (!isCompleted) {
                if (event.eventPhase === EventSource.CLOSED) {
                    // Connection closed, fallback to polling
                    startPollingMonitor();
                } else {
                    try {
                        const data = JSON.parse(event.data);
                        showError(data.message || 'Упс! Что-то пошло не так при обработке файла');
                    } catch (e) {
                        // SSE connection error, fallback to polling
                        startPollingMonitor();
                    }
                }
            }
        });
        
        eventSource.addEventListener('timeout', function(event) {
            eventSource.close();
            if (!isCompleted) {
                // Check one more time if it's actually completed
                fetch(`/api/upload/status/${currentSessionId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'completed') {
                            showComplete();
                        } else {
                            showError('Упс! Что-то пошло не так. Попробуйте обновить страницу.');
                        }
                    })
                    .catch(() => {
                        showError('Упс! Что-то пошло не так. Попробуйте обновить страницу.');
                    });
            }
        });
    }
    
    // Fallback polling monitor
    function startPollingMonitor() {
        let pollAttempts = 0;
        const maxPollAttempts = 60; // 2 minutes max
        
        processingInterval = setInterval(() => {
            pollAttempts++;
            
            fetch(`/api/upload/status/${currentSessionId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'completed') {
                        clearInterval(processingInterval);
                        showComplete();
                    } else if (data.status === 'failed') {
                        clearInterval(processingInterval);
                        showError(data.error || 'Упс! Что-то пошло не так при обработке файла');
                    } else if (data.progress) {
                        document.getElementById('processingBar').style.width = data.progress + '%';
                    }
                    
                    // Stop polling after max attempts
                    if (pollAttempts >= maxPollAttempts) {
                        clearInterval(processingInterval);
                        // Don't show error if processing might still be running
                        console.log('Polling stopped after max attempts');
                    }
                })
                .catch(error => {
                    console.error('Error checking status:', error);
                    clearInterval(processingInterval);
                });
        }, 2000);
    }
    
    function showProcessingStatus() {
        uploadProgress.style.display = 'none';
        processingStatus.style.display = 'block';
        // Show final chunk info
        document.getElementById('chunkProgress').textContent = `Все ${totalChunks} частей загружены успешно!`;
    }
    
    function showComplete() {
        processingStatus.style.display = 'none';
        uploadComplete.style.display = 'block';
    }
    
    function showError(message) {
        uploadProgress.style.display = 'none';
        processingStatus.style.display = 'none';
        uploadError.style.display = 'block';
        document.getElementById('errorMessage').textContent = message;
    }
    
    function resetUpload() {
        fileInfo.style.display = 'none';
        uploadProgress.style.display = 'none';
        processingStatus.style.display = 'none';
        uploadComplete.style.display = 'none';
        uploadError.style.display = 'none';
        currentSessionId = null;
        if (processingInterval) {
            clearInterval(processingInterval);
        }
    }
    
    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }
    
    // Drag and drop styling
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('bg-light');
    });
    
    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.classList.remove('bg-light');
    });
    
    uploadArea.addEventListener('drop', function(e) {
        this.classList.remove('bg-light');
    });
});
</script>
@endpush