<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

// Prune expired tokens every hour
Schedule::command('auth:prune-tokens')->hourly()->withoutOverlapping();
