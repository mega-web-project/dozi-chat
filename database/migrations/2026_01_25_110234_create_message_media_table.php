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
        Schema::create('message_media', function (Blueprint $table) {
        $table->id();
        $table->foreignId('message_id')->constrained()->cascadeOnDelete();
        $table->string('file_url');
        $table->string('file_type');
        $table->unsignedBigInteger('file_size')->nullable();
        $table->integer('duration')->nullable(); // seconds for audio/video
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_media');
    }
};
