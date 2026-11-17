<?php

use Illuminate\Support\Facades\Schedule;

// Webhooks do the work; this catches anything they missed.
Schedule::command('pixelforge:poll')->everyThirtySeconds()->withoutOverlapping()->onOneServer();

// Yesterday is final by now; any difference is logged and the command exits 1.
Schedule::command('pixelforge:reconcile', ['--to' => 'yesterday'])->dailyAt('00:15')->withoutOverlapping()->onOneServer();
