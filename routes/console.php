<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('pixelforge:poll')->everyThirtySeconds()->withoutOverlapping()->onOneServer();

Schedule::command('pixelforge:reconcile', ['--to' => 'yesterday'])->dailyAt('00:15')->withoutOverlapping()->onOneServer();

Schedule::command('model:prune')->dailyAt('03:30')->withoutOverlapping()->onOneServer();
