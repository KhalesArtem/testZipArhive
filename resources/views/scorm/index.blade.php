@extends('layouts.app')

@section('title', 'Список SCORM пакетов')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>SCORM пакеты</h1>
            <a href="{{ route('scorm.create') }}" class="btn btn-primary">
                <i class="bi bi-upload"></i> Загрузить
            </a>
        </div>

        @if($packages->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Название</th>
                            <th>Дата загрузки</th>
                            <th>Размер</th>
                            <th>Просмотры</th>
                            <th>Последний просмотр</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($packages as $package)
                            @php
                                $stats = $package->getUserStats($currentUser['id'] ?? 1);
                            @endphp
                            <tr>
                                <td>{{ $package->title }}</td>
                                <td>{{ $package->created_at->format('d.m.Y H:i') }}</td>
                                <td>{{ number_format($package->file_size / 1048576, 2) }} MB</td>
                                <td>
                                    <span class="badge bg-info">
                                        {{ $stats ? $stats->views_count : 0 }}
                                    </span>
                                </td>
                                <td>
                                    @if($stats && $stats->last_viewed_at)
                                        {{ $stats->last_viewed_at->format('d.m.Y H:i') }}
                                    @else
                                        <span class="text-muted">Не просмотрено</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('scorm.show', $package->id) }}" 
                                       class="btn btn-sm btn-success">
                                        <i class="bi bi-play"></i> Открыть
                                    </a>
                                    <form action="{{ route('scorm.destroy', $package->id) }}" 
                                          method="POST" 
                                          class="d-inline"
                                          onsubmit="return confirm('Удалить этот SCORM пакет?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="bi bi-trash"></i> Удалить
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Нет загруженных SCORM пакетов. 
                <a href="{{ route('scorm.create') }}">Загрузить первый пакет</a>
            </div>
        @endif
    </div>
</div>
@endsection