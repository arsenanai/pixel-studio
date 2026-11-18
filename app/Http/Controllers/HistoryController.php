<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\GenerationStatus;
use App\Enums\HistoryFilter;
use App\Http\Resources\GenerationResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class HistoryController
{
    private const PER_PAGE = 20;

    public function __invoke(Request $request, #[CurrentUser] User $user): Response
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(HistoryFilter::class)],
            'q' => ['nullable', 'string', 'max:200'],
        ]);

        $filter = $request->enum('status', HistoryFilter::class) ?? HistoryFilter::All;
        $search = trim($request->string('q')->toString());

        $generations = $user->generations()
            ->whereIn('status', $filter->statuses())
            ->when($search !== '', fn ($query) => $query->whereLike('prompt', "%{$search}%"))
            ->latest()
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('History/Index', [
            'generations' => GenerationResource::collection($generations),
            'filters' => ['status' => $filter->value, 'q' => $search],
            'counts' => fn () => $this->counts($user),
            'hasPending' => $user->generations()->pending()->exists(),
        ]);
    }

    /** @return array<string, int> */
    private function counts(User $user): array
    {
        $byStatus = $user->generations()->toBase()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn (mixed $count) => is_numeric($count) ? (int) $count : 0);

        $counts = [];

        foreach (HistoryFilter::cases() as $filter) {
            $counts[$filter->value] = array_sum(array_map(
                fn (GenerationStatus $status) => $byStatus->get($status->value, 0),
                $filter->statuses(),
            ));
        }

        return $counts;
    }
}
