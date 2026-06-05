<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('glpi-ai:sync-history')->hourly()->withoutOverlapping()->onOneServer();
Schedule::command('glpi-ai:generate-embeddings')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('glpi-ai:analyze-new-tickets')
    ->cron('*/'.max(1, (int) config('glpi-ai.analyze_new_tickets_interval_minutes', 2)).' * * * *')
    ->withoutOverlapping()
    ->onOneServer();
Schedule::command('glpi-ai:sync-suggestion-statuses')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('glpi-ai:archive-suggestions --days='.max(1, (int) config('glpi-ai.archive_after_days', 30)))->dailyAt('02:30')->withoutOverlapping()->onOneServer();
