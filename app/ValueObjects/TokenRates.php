<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Enums\TokenType;
use InvalidArgumentException;
use UnexpectedValueException;

final readonly class TokenRates
{
    private const TOKENS_PER_RATE = 1_000_000;

    public function __construct(
        public Money $inputText,
        public Money $inputImage,
        public Money $output,
        public Money $reasoning,
    ) {}

    /** @param array<string, string> $rates */
    public static function fromUsdStrings(array $rates): self
    {
        $rate = static function (TokenType $type) use ($rates): Money {
            if (! isset($rates[$type->value])) {
                throw new InvalidArgumentException("Missing rate for [{$type->value}].");
            }

            return Money::fromUsdString($rates[$type->value]);
        };

        return new self(
            $rate(TokenType::InputText),
            $rate(TokenType::InputImage),
            $rate(TokenType::Output),
            $rate(TokenType::Reasoning),
        );
    }

    public function rate(TokenType $type): Money
    {
        return match ($type) {
            TokenType::InputText => $this->inputText,
            TokenType::InputImage => $this->inputImage,
            TokenType::Output => $this->output,
            TokenType::Reasoning => $this->reasoning,
        };
    }

    public function cost(TokenUsage $usage): Money
    {
        $numerator = 0;

        foreach (TokenType::cases() as $type) {
            $numerator += $usage->tokens($type) * $this->rate($type)->nano;
        }

        return $this->exact($numerator);
    }

    public function costOf(TokenType $type, int $tokens): Money
    {
        return $this->exact($tokens * $this->rate($type)->nano);
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        $rates = [];

        foreach (TokenType::cases() as $type) {
            $rates[$type->value] = $this->rate($type)->toDecimalString();
        }

        return $rates;
    }

    private function exact(int $numerator): Money
    {
        if ($numerator % self::TOKENS_PER_RATE !== 0) {
            throw new UnexpectedValueException('Token cost is not a whole number of nanodollars.');
        }

        return Money::fromNano(intdiv($numerator, self::TOKENS_PER_RATE));
    }
}
