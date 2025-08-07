<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scorm_user_stats', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->default(1);
            $table->foreignId('scorm_package_id')->constrained()->onDelete('cascade');
            $table->integer('views_count')->default(0);
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'scorm_package_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scorm_user_stats');
    }
};