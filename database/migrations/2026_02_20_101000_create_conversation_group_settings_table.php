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
        Schema::create('conversation_group_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->unique()->constrained('conversations')->cascadeOnDelete();
            $table->enum('who_can_send_messages', ['all', 'admins'])->default('all');
            $table->enum('who_can_edit_info', ['all', 'admins'])->default('admins');
            $table->boolean('allow_member_invite')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_group_settings');
    }
};
