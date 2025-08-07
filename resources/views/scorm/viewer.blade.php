@extends('layouts.app')

@section('title', $package->title)

@push('scripts')
<script>
    // Set package ID for SCORM tracking
    window.scormPackageId = {{ $package->id }};
</script>
<script src="/js/scorm-api.js"></script>
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>
        <i class="bi bi-book"></i> {{ $package->title }}
    </h2>
    <a href="{{ route('scorm.index') }}" class="btn btn-secondary">
        <i class="bi bi-x-lg"></i> Закрыть
    </a>
</div>

<div class="border rounded bg-white" style="height: calc(100vh - 200px);">
    <iframe 
        src="{{ route('scorm.content', [$package->id, $entryPoint]) }}"
        style="width: 100%; height: 100%; border: none;"
        allowfullscreen>
    </iframe>
</div>

@endsection