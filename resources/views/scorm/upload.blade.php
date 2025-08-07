@extends('layouts.app')

@section('title', 'Загрузка SCORM пакета')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3><i class="bi bi-upload"></i> Загрузка SCORM пакета</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('scorm.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">Название пакета *</label>
                        <input type="text" 
                               class="form-control @error('title') is-invalid @enderror" 
                               id="title" 
                               name="title" 
                               value="{{ old('title') }}"
                               required>
                        @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="file" class="form-label">SCORM файл (ZIP) *</label>
                        <input type="file" 
                               class="form-control @error('file') is-invalid @enderror" 
                               id="file" 
                               name="file" 
                               accept=".zip"
                               required>
                        <div class="form-text">Максимальный размер: 100 MB</div>
                        @error('file')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 
                        Файл должен быть валидным SCORM пакетом и содержать файл imsmanifest.xml
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('scorm.index') }}" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Назад
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload"></i> Загрузить
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection