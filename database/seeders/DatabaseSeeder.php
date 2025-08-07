<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ScormPackage;
use App\Models\ScormUserStat;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Создаем тестовые пакеты
        $packages = [
            [
                'title' => 'Тестовый курс по безопасности',
                'original_filename' => 'safety-course.zip',
                'path' => 'scorm/test-1',
                'file_size' => 5242880
            ],
            [
                'title' => 'Введение в Laravel',
                'original_filename' => 'laravel-intro.zip',
                'path' => 'scorm/test-2',
                'file_size' => 3145728
            ],
            [
                'title' => 'Основы PHP',
                'original_filename' => 'php-basics.zip',
                'path' => 'scorm/test-3',
                'file_size' => 4194304
            ]
        ];

        foreach ($packages as $packageData) {
            $package = ScormPackage::create($packageData);
            
            // Создаем статистику для некоторых пакетов
            if ($package->id <= 2) {
                ScormUserStat::create([
                    'user_id' => 1,
                    'scorm_package_id' => $package->id,
                    'views_count' => rand(1, 10),
                    'last_viewed_at' => now()->subHours(rand(1, 48))
                ]);
            }
        }
    }
}