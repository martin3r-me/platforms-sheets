<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sheets_cell_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cell_id')->constrained('sheets_cells')->cascadeOnDelete();
            $table->foreignId('depends_on_cell_id')->constrained('sheets_cells')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['cell_id', 'depends_on_cell_id']);
            $table->index('depends_on_cell_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sheets_cell_dependencies');
    }
};
