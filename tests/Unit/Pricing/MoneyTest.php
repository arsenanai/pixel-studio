<?php

use App\ValueObjects\Money;

it('parses exact USD strings into nanodollars', function (string $usd, int $nano) {
    expect(Money::fromUsdString($usd)->nano)->toBe($nano);
})->with([
    'rate with two places' => ['5.00', 5_000_000_000],
    'fractional rate' => ['12.50', 12_500_000_000],
    'usage cost with nine places' => ['0.169080000', 169_080_000],
    'one nanodollar' => ['0.000000001', 1],
    'whole dollars' => ['7', 7_000_000_000],
    'zero' => ['0.000000000', 0],
]);

it('rejects strings that are not exact non-negative USD amounts', function (string $usd) {
    Money::fromUsdString($usd);
})->with([
    'empty' => [''],
    'negative' => ['-1.00'],
    'ten decimal places' => ['0.0000000001'],
    'comma separator' => ['1,00'],
    'scientific notation' => ['1e-3'],
    'no integer part' => ['.5'],
    'trailing dot' => ['1.'],
    'padded' => [' 1.00'],
])->throws(InvalidArgumentException::class);

it('formats exactly, without floats', function (int $nano, string $display) {
    expect(Money::fromNano($nano)->format())->toBe($display);
})->with([
    [381_887_500, '$0.3818875'],
    [0, '$0.00'],
    [1, '$0.000000001'],
    [5_000_000_000, '$5.00'],
    [1_250_000_000_000, '$1,250.00'],
    [-10_000_000, '-$0.01'],
]);

it('round-trips the decimal strings Pixelforge sends', function () {
    expect(Money::fromUsdString('12.50')->toDecimalString())->toBe('12.50')
        ->and(Money::fromUsdString('0.169080000')->toDecimalString())->toBe('0.16908');
});

it('adds, subtracts and compares', function () {
    $a = Money::fromNano(305_510_000);
    $b = Money::fromNano(76_377_500);

    expect($a->add($b)->nano)->toBe(381_887_500)
        ->and($a->add($b)->subtract($a)->equals($b))->toBeTrue()
        ->and($a->isGreaterThan($b))->toBeTrue()
        ->and(Money::zero()->isZero())->toBeTrue();
});

it('applies a 25% markup rounded up to the next nanodollar', function (int $cost, int $charge) {
    expect(Money::fromNano($cost)->withMarkup(2_500)->nano)->toBe($charge);
})->with([
    'docs succeeded example' => [305_510_000, 381_887_500],
    'docs maximum example' => [373_875_000, 467_343_750],
    'one nanodollar never rounds to free' => [1, 2],
    'exactly divisible' => [4, 5],
    'zero stays zero' => [0, 0],
]);

it('charges more when each generation is rounded than when the total is', function () {
    $costs = [6_036, 1, 1];

    $perGeneration = array_sum(array_map(
        fn (int $nano) => Money::fromNano($nano)->withMarkup(2_500)->nano,
        $costs,
    ));
    $onTheTotal = Money::fromNano(array_sum($costs))->withMarkup(2_500)->nano;

    expect($perGeneration)->toBe(7_549)
        ->and($onTheTotal)->toBe(7_548);
});

it('refuses to mark up a negative amount', function () {
    Money::fromNano(-1)->withMarkup(2_500);
})->throws(InvalidArgumentException::class);

it('serializes nanodollars as a string so browsers cannot round them', function () {
    expect(json_encode(Money::fromNano(467_343_750)))
        ->toBe('{"nano":"467343750","display":"$0.46734375"}');
});
