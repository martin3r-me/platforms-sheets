<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sheets_folder_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->constrained('sheets_folders')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('folder_role_id')->constrained('sheets_folder_roles');
            $table->timestamps();

            $table->unique(['folder_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sheets_folder_users');
    }
};
