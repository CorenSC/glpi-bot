<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('glpi-ai:sync-history')->hourly()->withoutOverlapping()->onOneServer();
Schedule::command('glpi-ai:generate-embeddings')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('glpi-ai:analyze-new-tickets')->cron('*/2 * * * *')->withoutOverlapping()->onOneServer();
Schedule::command('glpi-ai:sync-suggestion-statuses')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
