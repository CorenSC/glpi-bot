<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('glpi_ai_technician_scores', function (Blueprint $table): void {
            if (! Schema::hasColumn('glpi_ai_technician_scores', 'context_score')) {
                $table->decimal('context_score', 8, 4)->default(0)->after('category_score');
            }
        });
    }

    public function down(): void
    {
        Schema::table('glpi_ai_technician_scores', function (Blueprint $table): void {
            if (Schema::hasColumn('glpi_ai_technician_scores', 'context_score')) {
                $table->dropColumn('context_score');
            }
        });
    }
};
