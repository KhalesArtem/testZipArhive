<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('scorm_packages', function (Blueprint $table) {
            $table->enum('processing_status', ['pending', 'processing', 'completed', 'failed'])
                  ->default('completed')
                  ->after('file_size');
            $table->unsignedBigInteger('upload_session_id')->nullable()->after('id');
            $table->integer('processing_progress')->default(0)->after('processing_status');
            $table->text('processing_error')->nullable()->after('processing_progress');
            
            $table->index('processing_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scorm_packages', function (Blueprint $table) {
            $table->dropColumn(['processing_status', 'upload_session_id', 'processing_progress', 'processing_error']);
        });
    }
};
