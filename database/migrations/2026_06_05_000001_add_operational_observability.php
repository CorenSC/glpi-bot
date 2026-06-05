<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('glpi_ai_operational_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('command')->index();
            $table->string('status')->index();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->integer('exit_code')->nullable();
            $table->text('summary')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::table('glpi_ai_assignment_suggestions', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->index()->after('action_taken_at');
            $table->string('ai_validation_status')->default('pending')->index()->after('ai_parsed_response');
            $table->unsignedTinyInteger('ai_validation_attempts')->default(0)->after('ai_validation_status');
            $table->timestamp('ai_validation_next_retry_at')->nullable()->index()->after('ai_validation_attempts');
            $table->text('ai_validation_error')->nullable()->after('ai_validation_next_retry_at');
        });
    }

    public function down(): void
    {
        Schema::table('glpi_ai_assignment_suggestions', function (Blueprint $table): void {
            $table->dropColumn([
                'archived_at',
                'ai_validation_status',
                'ai_validation_attempts',
                'ai_validation_next_retry_at',
                'ai_validation_error',
            ]);
        });

        Schema::dropIfExists('glpi_ai_operational_runs');
    }
};
