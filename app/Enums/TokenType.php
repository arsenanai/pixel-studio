<?php

declare(strict_types=1);

namespace App\Enums;

enum TokenType: string
{
    case InputText = 'input_text_tokens';
    case InputImage = 'input_image_tokens';
    case Output = 'output_tokens';
    case Reasoning = 'reasoning_tokens';

    public function label(): string
    {
        return match ($this) {
            self::InputText => __('Prompt text'),
            self::InputImage => __('Reference images'),
            self::Output => __('Image output'),
            self::Reasoning => __('Reasoning'),
        };
    }
}
