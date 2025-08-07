<?php

namespace App\Http\Controllers;

use App\Contracts\UserResolver;
use App\Models\ScormPackage;
use App\Services\ScormService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class ScormController extends Controller
{
    private ScormService $scormService;
    private UserResolver $userResolver;

    public function __construct(ScormService $scormService, UserResolver $userResolver)
    {
        $this->scormService = $scormService;
        $this->userResolver = $userResolver;
    }

    public function index(): \Illuminate\Contracts\View\View
    {
        $userId = $this->userResolver->getUserId();
        
        $packages = ScormPackage::with(['stats' => function($query) use ($userId) {
            $query->where('user_id', $userId);
        }])->latest()->get();

        return view('scorm.index', [
            'packages' => $packages,
            'currentUser' => [
                'id' => $userId,
                'name' => $this->userResolver->getUserName()
            ]
        ]);
    }

    public function create(): \Illuminate\Contracts\View\View
    {
        // Use chunked upload view for large files
        return view('scorm.upload-chunked', [
            'currentUser' => [
                'id' => $this->userResolver->getUserId(),
                'name' => $this->userResolver->getUserName()
            ]
        ]);
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'file' => 'required|mimes:zip|max:102400'
        ]);

        $file = $request->file('file');
        if (!$file) {
            return back()->with('error', 'Файл не был загружен');
        }
        $originalFilename = $file->getClientOriginalName();
        $fileSize = $file->getSize();
        
        // Сохраняем временно
        $tempPath = $file->store('scorm-uploads');
        if (!$tempPath) {
            return back()->with('error', 'Ошибка при сохранении файла');
        }
        $fullTempPath = Storage::path($tempPath);

        // Валидируем SCORM пакет с проверками безопасности
        $validation = $this->scormService->validateScormPackage($fullTempPath);
        if (!$validation['valid']) {
            Storage::delete($tempPath);
            return back()->with('error', $validation['error'] ?? 'Ошибка валидации SCORM пакета');
        }

        // Создаем запись в БД
        $package = ScormPackage::create([
            'title' => $request->title,
            'original_filename' => $originalFilename,
            'path' => '',
            'file_size' => $fileSize
        ]);

        // Распаковываем
        $extractPath = storage_path('app/scorm/' . $package->id);
        if (!$this->scormService->extractPackage($fullTempPath, $extractPath)) {
            $package->delete();
            Storage::delete($tempPath);
            return back()->with('error', 'Ошибка при распаковке архива');
        }

        // Обновляем путь
        $package->update(['path' => 'scorm/' . $package->id]);

        // Удаляем временный файл
        Storage::delete($tempPath);

        return redirect()->route('scorm.index')->with('success', 'SCORM пакет успешно загружен');
    }

    public function show(int $id): \Illuminate\Contracts\View\View
    {
        $package = ScormPackage::findOrFail($id);
        
        // Записываем просмотр
        $this->scormService->recordView($package->id);

        // Находим точку входа
        $packagePath = storage_path('app/' . $package->path);
        $entryPoint = $this->scormService->findEntryPoint($packagePath);

        return view('scorm.viewer', [
            'package' => $package, 
            'entryPoint' => $entryPoint,
            'currentUser' => [
                'id' => $this->userResolver->getUserId(),
                'name' => $this->userResolver->getUserName()
            ]
        ]);
    }

    public function destroy(int $id): \Illuminate\Http\RedirectResponse
    {
        $package = ScormPackage::findOrFail($id);
        
        // Удаляем файлы
        $packagePath = storage_path('app/' . $package->path);
        if (File::exists($packagePath)) {
            File::deleteDirectory($packagePath);
        }

        // Удаляем связанную сессию загрузки, если есть
        if ($package->upload_session_id) {
            $uploadSession = \App\Models\UploadSession::find($package->upload_session_id);
            if ($uploadSession) {
                // Удаляем чанки
                $uploadSession->chunks()->delete();
                // Удаляем сессию
                $uploadSession->delete();
            }
        }

        // Удаляем запись из БД
        $package->delete();

        return redirect()->route('scorm.index')->with('success', 'SCORM пакет удален');
    }

    public function serveContent(int $id, string $path): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $package = ScormPackage::findOrFail($id);
        
        // Защита от path traversal
        $path = str_replace('..', '', $path);
        
        $fullPath = storage_path('app/' . $package->path . '/' . $path);
        
        if (!File::exists($fullPath)) {
            abort(404);
        }

        // Проверяем что файл находится внутри директории пакета
        $packageDir = storage_path('app/' . $package->path);
        $realFullPath = realpath($fullPath);
        $realPackageDir = realpath($packageDir);
        if (!$realFullPath || !$realPackageDir || !str_starts_with($realFullPath, $realPackageDir)) {
            abort(403);
        }

        $mimeType = File::mimeType($fullPath);
        
        return response()->file($fullPath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'no-cache'
        ]);
    }
}