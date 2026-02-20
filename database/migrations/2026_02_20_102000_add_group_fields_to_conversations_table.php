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
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('group_name')->nullable()->after('title');
            $table->string('group_logo')->nullable()->after('group_name');
            $table->string('group_banner')->nullable()->after('group_logo');
            $table->text('group_description')->nullable()->after('group_banner');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn([
                'group_name',
                'group_logo',
                'group_banner',
                'group_description',
            ]);
        });
    }
};
