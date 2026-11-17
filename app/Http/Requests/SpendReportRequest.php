<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class SpendReportRequest extends FormRequest
{
    /** GET /v1/usage covers at most 92 days, so the report does too. */
    public const MAX_DAYS = 92;

    public const DEFAULT_DAYS = 30;

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->from()->greaterThan($this->to())) {
                $validator->errors()->add('from', 'The start date must be on or before the end date.');
            } elseif ($this->from()->diffInDays($this->to()) + 1 > self::MAX_DAYS) {
                $validator->errors()->add('from', 'Pick at most '.self::MAX_DAYS.' days; that is all Pixelforge reports at once.');
            }
        }];
    }

    public function to(): CarbonImmutable
    {
        return $this->day('to') ?? CarbonImmutable::today('UTC');
    }

    public function from(): CarbonImmutable
    {
        return $this->day('from') ?? $this->to()->subDays(self::DEFAULT_DAYS - 1);
    }

    private function day(string $key): ?CarbonImmutable
    {
        $value = $this->string($key)->toString();

        return $value === '' ? null : (CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC') ?: null);
    }
}
