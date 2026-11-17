<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Enums\TokenType;
use InvalidArgumentException;

final readonly class TokenUsage
{
    public function __construct(
        public int $inputText,
        public int $inputImage,
        public int $output,
        public int $reasoning,
    ) {
        if (min($inputText, $inputImage, $output, $reasoning) < 0) {
            throw new InvalidArgumentException('Token counts cannot be negative.');
        }
    }

    public function tokens(TokenType $type): int
    {
        return match ($type) {
            TokenType::InputText => $this->inputText,
            TokenType::InputImage => $this->inputImage,
            TokenType::Output => $this->output,
            TokenType::Reasoning => $this->reasoning,
        };
    }

    public function total(): int
    {
        return $this->inputText + $this->inputImage + $this->output + $this->reasoning;
    }
}
