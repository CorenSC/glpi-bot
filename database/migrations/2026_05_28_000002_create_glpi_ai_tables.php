<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('glpi_ai_ticket_histories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('glpi_ticket_id')->unique();
            $table->string('title')->nullable();
            $table->longText('original_content')->nullable();
            $table->longText('clean_content')->nullable();
            $table->longText('canonical_text');
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->string('category_name')->nullable();
            $table->string('category_path')->nullable();
            $table->unsignedBigInteger('assigned_group_id')->nullable()->index();
            $table->string('assigned_group_name')->nullable();
            $table->unsignedBigInteger('assigned_technician_id')->nullable()->index();
            $table->string('assigned_technician_name')->nullable();
            $table->unsignedBigInteger('solver_technician_id')->nullable()->index();
            $table->string('solver_technician_name')->nullable();
            $table->integer('status')->nullable()->index();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('updated_at_glpi')->nullable();
            $table->timestamp('solved_at')->nullable()->index();
            $table->timestamp('closed_at')->nullable();
            $table->longText('solution_text')->nullable();
            $table->longText('followup_summary')->nullable();
            $table->string('embedding_provider')->nullable();
            $table->string('embedding_model')->nullable();
            $table->longText('embedding')->nullable();
            $table->string('embedding_hash')->nullable()->index();
            $table->timestamp('embedding_generated_at')->nullable();
            $table->string('content_hash')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('glpi_ai_analysis_runs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('glpi_ticket_id')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('status')->index();
            $table->string('algorithm_version');
            $table->string('model_used')->nullable();
            $table->string('embedding_provider_used')->nullable();
            $table->string('embedding_model_used')->nullable();
            $table->boolean('dry_run')->default(true)->index();
            $table->boolean('auto_assign_enabled')->default(false);
            $table->longText('normalized_text')->nullable();
            $table->longText('canonical_text')->nullable();
            $table->string('text_hash')->nullable()->index();
            $table->string('risk_level')->default('low')->index();
            $table->json('sensitive_words_found')->nullable();
            $table->json('deterministic_decision')->nullable();
            $table->json('ai_decision')->nullable();
            $table->json('final_decision')->nullable();
            $table->string('recommended_action')->nullable()->index();
            $table->unsignedBigInteger('recommended_technician_id')->nullable()->index();
            $table->unsignedBigInteger('recommended_group_id')->nullable()->index();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->text('error_message')->nullable();
            $table->text('error_trace')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('glpi_ai_similar_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained('glpi_ai_analysis_runs')->cascadeOnDelete();
            $table->foreignId('glpi_ticket_history_id')->constrained('glpi_ai_ticket_histories')->cascadeOnDelete();
            $table->unsignedBigInteger('glpi_ticket_id')->index();
            $table->decimal('similarity_score', 8, 5);
            $table->decimal('category_score', 8, 5)->default(0);
            $table->decimal('recency_score', 8, 5)->default(0);
            $table->decimal('final_similarity_score', 8, 5)->default(0);
            $table->string('title')->nullable();
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->string('category_path')->nullable();
            $table->unsignedBigInteger('assigned_technician_id')->nullable();
            $table->string('assigned_technician_name')->nullable();
            $table->unsignedBigInteger('solver_technician_id')->nullable();
            $table->string('solver_technician_name')->nullable();
            $table->unsignedBigInteger('assigned_group_id')->nullable();
            $table->string('assigned_group_name')->nullable();
            $table->timestamp('solved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('glpi_ai_technician_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained('glpi_ai_analysis_runs')->cascadeOnDelete();
            $table->unsignedBigInteger('technician_id')->nullable()->index();
            $table->string('technician_name')->nullable();
            $table->unsignedBigInteger('group_id')->nullable()->index();
            $table->string('group_name')->nullable();
            $table->decimal('text_similarity_score', 8, 4)->default(0);
            $table->decimal('category_score', 8, 4)->default(0);
            $table->decimal('context_score', 8, 4)->default(0);
            $table->decimal('history_score', 8, 4)->default(0);
            $table->decimal('recency_score', 8, 4)->default(0);
            $table->decimal('workload_score', 8, 4)->default(0);
            $table->decimal('final_score', 8, 4)->default(0);
            $table->unsignedInteger('rank_position')->index();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_blocked')->default(false);
            $table->string('blocked_reason')->nullable();
            $table->unsignedInteger('similar_tickets_count')->default(0);
            $table->unsignedInteger('average_resolution_time_minutes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('glpi_ai_assignment_suggestions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained('glpi_ai_analysis_runs')->cascadeOnDelete();
            $table->unsignedBigInteger('glpi_ticket_id')->index();
            $table->string('title')->nullable();
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->string('category_name')->nullable();
            $table->string('recommended_action')->index();
            $table->unsignedBigInteger('recommended_technician_id')->nullable()->index();
            $table->string('recommended_technician_name')->nullable();
            $table->unsignedBigInteger('recommended_group_id')->nullable()->index();
            $table->string('recommended_group_name')->nullable();
            $table->decimal('confidence', 5, 2)->default(0);
            $table->text('reason')->nullable();
            $table->json('warnings')->nullable();
            $table->string('risk_level')->default('low')->index();
            $table->string('status')->default('pending')->index();
            $table->json('ai_payload')->nullable();
            $table->longText('ai_raw_response')->nullable();
            $table->json('ai_parsed_response')->nullable();
            $table->json('ranking_payload')->nullable();
            $table->json('glpi_payload')->nullable();
            $table->json('glpi_api_response')->nullable();
            $table->string('action_taken')->nullable();
            $table->timestamp('action_taken_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('human_observation')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('glpi_ai_human_feedbacks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assignment_suggestion_id')->constrained('glpi_ai_assignment_suggestions')->cascadeOnDelete();
            $table->foreignId('analysis_run_id')->constrained('glpi_ai_analysis_runs')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->index();
            $table->string('previous_status')->nullable();
            $table->string('new_status')->nullable();
            $table->unsignedBigInteger('selected_technician_id')->nullable();
            $table->unsignedBigInteger('selected_group_id')->nullable();
            $table->text('observation')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('glpi_ai_blocked_technicians', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('technician_id')->unique();
            $table->string('technician_name')->nullable();
            $table->text('reason')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('glpi_ai_blocked_categories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('category_id')->unique();
            $table->string('category_name')->nullable();
            $table->text('reason')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('glpi_ai_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->string('type')->default('string');
            $table->text('description')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('glpi_ai_settings');
        Schema::dropIfExists('glpi_ai_blocked_categories');
        Schema::dropIfExists('glpi_ai_blocked_technicians');
        Schema::dropIfExists('glpi_ai_human_feedbacks');
        Schema::dropIfExists('glpi_ai_assignment_suggestions');
        Schema::dropIfExists('glpi_ai_technician_scores');
        Schema::dropIfExists('glpi_ai_similar_tickets');
        Schema::dropIfExists('glpi_ai_analysis_runs');
        Schema::dropIfExists('glpi_ai_ticket_histories');
    }
};
