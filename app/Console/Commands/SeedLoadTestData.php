<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\RebuildDailySpend;
use App\Enums\CreditTransactionType;
use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Random\Engine\Mt19937;
use Random\Randomizer;

final class SeedLoadTestData extends Command
{
    private const CHUNK = 1000;

    private const COST_RANGE_NANO = [
        'low' => [11_000_000, 17_000_000],
        'medium' => [40_000_000, 60_000_000],
        'high' => [280_000_000, 380_000_000],
    ];

    private const PROMPTS = [
        'A lighthouse on a basalt cliff at dusk',
        'A red fox in fresh snow, watercolor',
        'A ceramic teapot on a wooden table, studio light',
        'Product shot of a leather backpack on white',
        'An isometric city block at night, neon signs',
        'A bowl of ramen, top-down food photography',
        'Portrait of an astronaut in a sunflower field',
        'A cozy reading nook with rain on the window',
    ];

    protected $signature = 'load-test:seed
        {--users=5000 : Users to create}
        {--generations=500000 : Generations to spread across those users}
        {--days=90 : How many past days the generations cover}
        {--seed=42 : Random seed, so runs are repeatable}';

    protected $description = 'Fill a non-production database with synthetic users, generations and ledger rows for load testing';

    private Randomizer $random;

    public function handle(RebuildDailySpend $rebuild): int
    {
        if (app()->isProduction()) {
            $this->error('Refusing to seed synthetic data in production: it would break reconciliation with Pixelforge.');

            return self::FAILURE;
        }

        $this->random = new Randomizer(new Mt19937($this->integerOption('seed')));
        $now = CarbonImmutable::now('UTC');
        $start = $now->subDays($this->integerOption('days'));

        $userIds = $this->seedUsers($this->integerOption('users'), $start->subDay());
        $charged = $this->seedGenerations($userIds, $this->integerOption('generations'), $start, $now);
        $this->seedPurchases($charged, $start->subDay());
        $rollups = $rebuild();

        $this->info(sprintf('Seeded %d users and %d generations, and rebuilt %d daily spend rows.', count($userIds), $this->integerOption('generations'), $rollups));

        return self::SUCCESS;
    }

    /** @return list<int> */
    private function seedUsers(int $count, CarbonImmutable $createdAt): array
    {
        $password = Hash::make(Str::random(32));
        $batch = Str::lower(Str::random(6));
        $bar = $this->output->createProgressBar($count);
        $bar->setFormat(' users        %current%/%max% [%bar%] %percent:3s%%');

        foreach (array_chunk(range(1, $count), self::CHUNK) as $chunk) {
            DB::table('users')->insert(array_map(fn (int $n) => [
                'name' => "Load User {$batch}-{$n}",
                'email' => "load-{$batch}-{$n}@example.test",
                'password' => $password,
                'is_admin' => false,
                'email_verified_at' => $createdAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ], $chunk));
            $bar->advance(count($chunk));
        }

        $bar->finish();
        $this->newLine();

        return array_values(DB::table('users')->where('email', 'like', "load-{$batch}-%")->orderBy('id')->pluck('id')
            ->map(fn (mixed $id) => is_numeric($id) ? (int) $id : 0)
            ->all());
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, int>
     */
    private function seedGenerations(array $userIds, int $count, CarbonImmutable $start, CarbonImmutable $now): array
    {
        $charged = [];
        $span = $now->getTimestamp() - $start->getTimestamp();
        $bar = $this->output->createProgressBar($count);
        $bar->setFormat(' generations  %current%/%max% [%bar%] %percent:3s%%');

        for ($done = 0; $done < $count; $done += self::CHUNK) {
            $generations = [];
            $charges = [];

            for ($i = 0; $i < min(self::CHUNK, $count - $done); $i++) {
                $userId = $userIds[(int) floor(count($userIds) * ($this->random->nextFloat() ** 3))];
                $createdAt = $start->addSeconds($this->random->getInt(0, $span));
                [$row, $charge] = $this->generation($userId, $createdAt, $now);
                $generations[] = $row;

                if ($charge !== null) {
                    $charged[$userId] = ($charged[$userId] ?? 0) + $charge;
                    $charges[] = [
                        'user_id' => $userId,
                        'type' => CreditTransactionType::Charge->value,
                        'amount_nano' => -$charge,
                        'generation_id' => $row['id'],
                        'created_at' => $row['completed_at'],
                    ];
                }
            }

            DB::transaction(function () use ($generations, $charges): void {
                DB::table('generations')->insert($generations);
                DB::table('credit_transactions')->insert($charges);
            });
            $bar->advance(count($generations));
        }

        $bar->finish();
        $this->newLine();

        return $charged;
    }

    /** @return array{0: array<string, mixed>, 1: int|null} */
    private function generation(int $userId, CarbonImmutable $createdAt, CarbonImmutable $now): array
    {
        $quality = $this->pick(ImageQuality::cases())->value;
        [$low, $high] = self::COST_RANGE_NANO[$quality];
        $cost = $this->random->getInt($low, $high);
        $recent = $createdAt->greaterThan($now->subMinutes(2));
        $status = match (true) {
            $recent => GenerationStatus::Processing,
            $this->random->getInt(1, 100) <= 5 => GenerationStatus::Failed,
            default => GenerationStatus::Succeeded,
        };
        $completedAt = $recent ? null : $createdAt->addSeconds($this->random->getInt(8, 40));
        $succeeded = $status === GenerationStatus::Succeeded;

        return [[
            'id' => (string) Str::uuid7($createdAt),
            'user_id' => $userId,
            'prompt' => $this->pick(self::PROMPTS),
            'size' => $this->pick(ImageSize::cases())->value,
            'quality' => $quality,
            'reference_images' => '[]',
            'status' => $status->value,
            'provider_job_id' => 'gen_load_'.strtolower(Str::ulid()->toBase32()),
            'usage_input_text_tokens' => $succeeded ? $this->random->getInt(8, 60) : null,
            'usage_input_image_tokens' => $succeeded ? 0 : null,
            'usage_output_tokens' => $succeeded ? $this->random->getInt(1000, 6000) : null,
            'usage_reasoning_tokens' => $succeeded ? $this->random->getInt(100, 900) : null,
            'estimated_provider_cost_nano' => intdiv($low + $high, 2),
            'estimated_charge_nano' => $this->withMarkup(intdiv($low + $high, 2)),
            'estimated_min_charge_nano' => $this->withMarkup($low),
            'estimated_max_charge_nano' => $this->withMarkup($high),
            'provider_cost_nano' => $succeeded ? $cost : ($status === GenerationStatus::Failed ? 0 : null),
            'charge_nano' => $succeeded ? $this->withMarkup($cost) : ($status === GenerationStatus::Failed ? 0 : null),
            'error_code' => $status === GenerationStatus::Failed ? 'generation_failed' : null,
            'error_message' => $status === GenerationStatus::Failed ? 'The model could not produce an image for this prompt.' : null,
            'submit_attempts' => 1,
            'submitted_at' => $createdAt,
            'started_at' => $createdAt->addSeconds(2),
            'completed_at' => $completedAt,
            'created_at' => $createdAt,
            'updated_at' => $completedAt ?? $createdAt,
        ], $succeeded ? $this->withMarkup($cost) : null];
    }

    /** @param array<int, int> $charged */
    private function seedPurchases(array $charged, CarbonImmutable $createdAt): void
    {
        $rows = [];

        foreach ($charged as $userId => $nano) {
            $dollars = intdiv($nano, 1_000_000_000) + 5;
            $rows[] = [
                'user_id' => $userId,
                'type' => CreditTransactionType::Purchase->value,
                'amount_nano' => $dollars * 1_000_000_000,
                'generation_id' => null,
                'created_at' => $createdAt,
            ];
        }

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table('credit_transactions')->insert($chunk);
        }
    }

    private function withMarkup(int $nano): int
    {
        return intdiv($nano * 12_500 + 9_999, 10_000);
    }

    /**
     * @template T
     *
     * @param  non-empty-list<T>  $items
     * @return T
     */
    private function pick(array $items): mixed
    {
        return $items[$this->random->getInt(0, count($items) - 1)];
    }

    private function integerOption(string $name): int
    {
        $value = $this->option($name);

        return is_numeric($value) ? max(1, (int) $value) : 1;
    }
}
