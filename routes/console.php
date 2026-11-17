<?php

use Illuminate\Support\Facades\Schedule;

// Webhooks do the work; this catches anything they missed.
Schedule::command('pixelforge:poll')->everyThirtySeconds()->withoutOverlapping()->onOneServer();
