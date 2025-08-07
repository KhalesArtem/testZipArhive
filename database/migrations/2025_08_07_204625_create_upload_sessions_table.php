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
        Schema::create('upload_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('file_identifier')->unique();
            $table->string('filename');
            $table->bigInteger('total_size');
            $table->integer('total_chunks');
            $table->integer('uploaded_chunks')->default(0);
            $table->enum('status', ['pending', 'uploading', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->unsignedBigInteger('user_id');
            $table->string('file_type')->nullable();
            $table->text('metadata')->nullable();
            $table->timestamps();
            
            $table->index('file_identifier');
            $table->index('user_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upload_sessions');
    }
};
