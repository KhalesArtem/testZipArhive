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
        Schema::create('upload_chunks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id');
            $table->integer('chunk_number');
            $table->string('chunk_path');
            $table->bigInteger('chunk_size');
            $table->string('checksum')->nullable();
            $table->timestamp('uploaded_at');
            
            $table->foreign('session_id')->references('id')->on('upload_sessions')->onDelete('cascade');
            $table->unique(['session_id', 'chunk_number']);
            $table->index('session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upload_chunks');
    }
};
