<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sheets_cells', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worksheet_id')->constrained('sheets_worksheets')->cascadeOnDelete();
            $table->unsignedInteger('row');
            $table->unsignedInteger('col');
            $table->text('raw_value')->nullable();
            $table->text('computed_value')->nullable();
            $table->foreignId('cell_type_id')->constrained('sheets_cell_types');
            $table->json('format')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['worksheet_id', 'row', 'col']);
            $table->index(['worksheet_id', 'row']);
            $table->index(['worksheet_id', 'col']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sheets_cells');
    }
};
