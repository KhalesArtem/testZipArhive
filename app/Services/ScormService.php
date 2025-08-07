<?php

namespace App\Services;

use App\Contracts\UserResolver;
use App\Models\ScormUserStat;
use ZipArchive;
use Illuminate\Support\Facades\File;

class ScormService
{
    private UserResolver $userResolver;

    public function __construct(UserResolver $userResolver)
    {
        $this->userResolver = $userResolver;
    }
    // Максимальные лимиты для защиты от zip-бомб
    private const MAX_FILES = 1000;              // Максимум файлов в архиве
    private const MAX_UNCOMPRESSED_SIZE = 524288000; // 500 MB распакованный размер
    private const MAX_COMPRESSION_RATIO = 100;   // Максимальное соотношение сжатия
    private const MAX_NESTED_DEPTH = 5;          // Максимальная глубина вложенности

    /**
     * Валидация SCORM пакета с проверками безопасности
     * @return array{valid: bool, error?: string, uncompressed_size?: int}
     */
    public function validateScormPackage(string $zipPath): array
    {
        $zip = new ZipArchive();
        
        if ($zip->open($zipPath) !== true) {
            return ['valid' => false, 'error' => 'Не удалось открыть ZIP архив'];
        }

        // Проверка на количество файлов
        if ($zip->numFiles > self::MAX_FILES) {
            $zip->close();
            return ['valid' => false, 'error' => 'Архив содержит слишком много файлов (максимум ' . self::MAX_FILES . ')'];
        }

        // Проверка на zip-бомбу
        $totalUncompressed = 0;
        $hasManifest = false;
        $maxDepth = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                continue;
            }

            $filename = $zip->getNameIndex($i);
            if ($filename === false) {
                continue;
            }

            // Проверка на path traversal
            if (strpos($filename, '..') !== false || strpos($filename, '//') !== false) {
                $zip->close();
                return ['valid' => false, 'error' => 'Обнаружены небезопасные пути в архиве'];
            }

            // Проверка глубины вложенности
            $depth = substr_count($filename, '/');
            if ($depth > $maxDepth) {
                $maxDepth = $depth;
            }
            if ($maxDepth > self::MAX_NESTED_DEPTH) {
                $zip->close();
                return ['valid' => false, 'error' => 'Слишком глубокая вложенность файлов'];
            }

            // Суммируем распакованный размер
            $totalUncompressed += $stat['size'];

            // Проверка на наличие манифеста
            if (basename($filename) === 'imsmanifest.xml') {
                $hasManifest = true;
            }

            // Проверка на подозрительные расширения
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            if (in_array(strtolower($ext), ['exe', 'bat', 'cmd', 'sh', 'ps1', 'app'])) {
                $zip->close();
                return ['valid' => false, 'error' => 'Архив содержит исполняемые файлы'];
            }
        }

        // Проверка общего распакованного размера
        if ($totalUncompressed > self::MAX_UNCOMPRESSED_SIZE) {
            $zip->close();
            return ['valid' => false, 'error' => 'Распакованный размер превышает лимит (максимум ' . (self::MAX_UNCOMPRESSED_SIZE / 1048576) . ' MB)'];
        }

        // Проверка соотношения сжатия (защита от zip-бомб)
        $compressedSize = filesize($zipPath);
        if ($compressedSize > 0) {
            $ratio = $totalUncompressed / $compressedSize;
            if ($ratio > self::MAX_COMPRESSION_RATIO) {
                $zip->close();
                return ['valid' => false, 'error' => 'Подозрительное соотношение сжатия (возможна zip-бомба)'];
            }
        }

        $zip->close();

        if (!$hasManifest) {
            return ['valid' => false, 'error' => 'Файл не является валидным SCORM пакетом (отсутствует imsmanifest.xml)'];
        }

        return ['valid' => true, 'uncompressed_size' => $totalUncompressed];
    }

    /**
     * Безопасная распаковка архива с дополнительными проверками
     */
    public function extractPackage(string $zipPath, string $destinationPath): bool
    {
        $zip = new ZipArchive();
        
        if ($zip->open($zipPath) !== true) {
            return false;
        }

        // Создаем директорию если не существует
        if (!File::exists($destinationPath)) {
            File::makeDirectory($destinationPath, 0755, true);
        }

        // Распаковываем с дополнительной проверкой каждого файла
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if ($filename === false) {
                continue;
            }

            // Финальная проверка на path traversal
            $targetPath = $destinationPath . '/' . $filename;
            $realDestination = realpath($destinationPath);
            $realTarget = realpath(dirname($targetPath));
            
            // Проверяем что файл будет внутри целевой директории
            if ($realTarget !== false && $realDestination !== false) {
                if (strpos($realTarget, $realDestination) !== 0) {
                    $zip->close();
                    // Удаляем частично распакованные файлы
                    File::deleteDirectory($destinationPath);
                    return false;
                }
            }
        }

        $result = $zip->extractTo($destinationPath);
        $zip->close();

        // Устанавливаем права только на чтение для безопасности
        $this->setSecurePermissions($destinationPath);

        return $result;
    }

    /**
     * Установка безопасных прав доступа
     */
    private function setSecurePermissions(string $path): void
    {
        // Устанавливаем права 644 для файлов и 755 для директорий
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                chmod($item->getPathname(), 0755);
            } else {
                chmod($item->getPathname(), 0644);
            }
        }
    }

    public function findEntryPoint(string $packagePath): string
    {
        // Проверяем стандартные точки входа
        $possibleEntries = ['index.html', 'index.htm', 'index_lms.html', 'start.html'];
        
        foreach ($possibleEntries as $entry) {
            if (File::exists($packagePath . '/' . $entry)) {
                return $entry;
            }
        }

        // Если не нашли, пробуем найти первый HTML файл
        $files = File::files($packagePath);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'html' || 
                pathinfo($file, PATHINFO_EXTENSION) === 'htm') {
                return basename($file);
            }
        }

        return 'index.html'; // fallback
    }

    public function recordView(int $packageId, ?int $userId = null): void
    {
        $userId = $userId ?? $this->userResolver->getUserId();
        
        $stat = ScormUserStat::firstOrCreate(
            [
                'user_id' => $userId,
                'scorm_package_id' => $packageId
            ],
            [
                'views_count' => 0,
                'last_viewed_at' => null
            ]
        );

        $stat->incrementViews();
    }
}