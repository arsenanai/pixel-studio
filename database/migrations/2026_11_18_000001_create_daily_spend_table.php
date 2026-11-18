<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_spend', function (Blueprint $table) {
            $table->date('day');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('succeeded')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->bigInteger('charged_nano')->default(0);
            $table->bigInteger('provider_cost_nano')->default(0);

            $table->primary(['day', 'user_id']);
            $table->index(['user_id', 'day']);
        });

        DB::table('daily_spend')->insertUsing(
            ['day', 'user_id', 'succeeded', 'failed', 'charged_nano', 'provider_cost_nano'],
            DB::table('generations')
                ->whereIn('status', ['succeeded', 'failed'])
                ->whereNotNull('completed_at')
                ->selectRaw('DATE(completed_at) as day, user_id')
                ->selectRaw("SUM(CASE WHEN status = 'succeeded' THEN 1 ELSE 0 END) as succeeded")
                ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed")
                ->selectRaw('COALESCE(SUM(charge_nano), 0) as charged_nano')
                ->selectRaw('COALESCE(SUM(provider_cost_nano), 0) as provider_cost_nano')
                ->groupByRaw('DATE(completed_at), user_id'),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_spend');
    }
};
