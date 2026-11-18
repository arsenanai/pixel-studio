<?php

declare(strict_types=1);

namespace App\Enums;

enum PollOutcome
{
    case Updated;
    case Skipped;
    case StopForNow;
}
