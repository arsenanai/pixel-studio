<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class QuoteGenerationRequest extends FormRequest
{
    public const PROMPT_MAX_CHARACTERS = 4_000;

    public const REFERENCE_IMAGES_MAX = 4;

    /** @return array<string, list<string|ValidationRule|Enum>> */
    public function rules(): array
    {
        return [
            'prompt' => ['nullable', 'string', 'max:'.self::PROMPT_MAX_CHARACTERS],
            'size' => ['required', Rule::enum(ImageSize::class)],
            'quality' => ['required', Rule::enum(ImageQuality::class)],
            'reference_images' => ['nullable', 'array', 'max:'.self::REFERENCE_IMAGES_MAX],
            'reference_images.*' => ['required', 'string', 'url:http,https', 'max:2048', 'distinct'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reference_images.max' => 'Pixelforge accepts at most :max reference images.',
            'reference_images.*.url' => 'Enter a full http:// or https:// image URL.',
            'reference_images.*.distinct' => 'This URL is already in the list.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['reference_images.*' => 'reference image URL'];
    }

    public function prompt(): string
    {
        return $this->string('prompt')->toString();
    }

    public function size(): ImageSize
    {
        return $this->enum('size', ImageSize::class) ?? ImageSize::Square;
    }

    public function quality(): ImageQuality
    {
        return $this->enum('quality', ImageQuality::class) ?? ImageQuality::Medium;
    }

    /** @return list<string> */
    public function referenceImages(): array
    {
        return array_values(array_filter($this->array('reference_images'), is_string(...)));
    }
}
