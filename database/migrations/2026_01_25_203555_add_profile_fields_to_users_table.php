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
        Schema::table('users', function (Blueprint $table) {
            $table->string('department')->nullable()->after('avatar');
            $table->string('job_title')->nullable()->after('department');
            $table->string('position')->nullable()->after('job_title');
            $table->string('location')->nullable()->after('position');
            $table->enum('availability', ['available', 'busy', 'away'])->default('available')->after('location');
            $table->boolean('do_not_disturb')->default(false)->after('availability');
            $table->enum('role', ['user', 'admin'])->default('user')->after('do_not_disturb');
            $table->enum('status', ['active', 'inactive'])->default('active')->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'department',
                'job_title',
                'position',
                'location',
                'availability',
                'do_not_disturb',
                'role',
                'status',
            ]);
        });
    }
};
