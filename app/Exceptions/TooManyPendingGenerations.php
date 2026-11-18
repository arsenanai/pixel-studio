<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class TooManyPendingGenerations extends RuntimeException
{
    public function __construct(public readonly int $limit)
    {
        parent::__construct(__('You already have :limit images in progress. Wait for one to finish before starting another.', ['limit' => $limit]));
    }
}
