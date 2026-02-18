<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sheets_worksheets', function (Blueprint $table) {
            $table->json('column_widths')->nullable()->after('col_count');
            $table->json('row_heights')->nullable()->after('column_widths');
            $table->unsignedInteger('frozen_rows')->default(0)->after('row_heights');
            $table->unsignedInteger('frozen_cols')->default(0)->after('frozen_rows');
        });
    }

    public function down(): void
    {
        Schema::table('sheets_worksheets', function (Blueprint $table) {
            $table->dropColumn(['column_widths', 'row_heights', 'frozen_rows', 'frozen_cols']);
        });
    }
};
