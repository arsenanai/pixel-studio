<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class StoreGenerationRequest extends QuoteGenerationRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'prompt' => ['required', 'string', 'max:'.self::PROMPT_MAX_CHARACTERS],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'prompt.required' => __('Describe the image you want.'),
        ];
    }
}
