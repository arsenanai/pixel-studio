<?php

declare(strict_types=1);

use App\Actions\Admin\RebuildDailySpend;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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

        app(RebuildDailySpend::class)();
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_spend');
    }
};
