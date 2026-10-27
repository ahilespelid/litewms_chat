<?php

use Illuminate\Support\Facades\Schedule;

// Расписание живёт в Laravel (отдельный контейнер scheduler), а не в cron хоста.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('cache:prune-stale-tags')->hourly();
