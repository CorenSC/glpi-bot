<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('glpi_ai_assignment_suggestions', function (Blueprint $table): void {
            $table->decimal('ranking_confidence', 5, 2)->nullable()->after('confidence');
            $table->decimal('ai_confidence', 5, 2)->nullable()->after('ranking_confidence');
            $table->decimal('final_confidence', 5, 2)->nullable()->after('ai_confidence');
            $table->string('block_reason_code')->nullable()->index()->after('risk_level');
            $table->text('block_reason')->nullable()->after('block_reason_code');
        });

        Schema::table('glpi_ai_human_feedbacks', function (Blueprint $table): void {
            $table->string('reason_code')->nullable()->index()->after('action');
            $table->decimal('learning_weight', 5, 2)->default(0)->after('reason_code');
        });
    }

    public function down(): void
    {
        Schema::table('glpi_ai_human_feedbacks', function (Blueprint $table): void {
            $table->dropColumn(['reason_code', 'learning_weight']);
        });

        Schema::table('glpi_ai_assignment_suggestions', function (Blueprint $table): void {
            $table->dropColumn([
                'ranking_confidence',
                'ai_confidence',
                'final_confidence',
                'block_reason_code',
                'block_reason',
            ]);
        });
    }
};
