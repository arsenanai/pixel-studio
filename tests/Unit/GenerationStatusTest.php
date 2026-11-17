<?php

use App\Enums\GenerationStatus;

it('ranks statuses in lifecycle order with both outcomes at the top', function () {
    expect(GenerationStatus::Queued->rank())->toBeLessThan(GenerationStatus::Processing->rank())
        ->and(GenerationStatus::Processing->rank())->toBeLessThan(GenerationStatus::Succeeded->rank())
        ->and(GenerationStatus::Succeeded->rank())->toBe(GenerationStatus::Failed->rank());
});

it('treats only succeeded and failed as final', function () {
    expect(array_map(fn (GenerationStatus $s) => $s->isTerminal(), GenerationStatus::cases()))
        ->toBe([false, false, true, true]);
});
