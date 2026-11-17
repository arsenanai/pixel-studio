<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Generations\PollPendingGenerations;
use Illuminate\Console\Command;

final class PollGenerations extends Command
{
    protected $signature = 'pixelforge:poll';

    protected $description = 'Fetch pending generations from Pixelforge in case a webhook was missed';

    public function handle(PollPendingGenerations $poll): int
    {
        $result = $poll();

        $this->info("Polled {$result['polled']}, resubmitted {$result['resubmitted']}, abandoned {$result['abandoned']}.");

        return self::SUCCESS;
    }
}
