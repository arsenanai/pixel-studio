<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only: a balance is the sum of its rows, never a stored number.
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);
            // Signed nanodollars: purchases are positive, charges negative.
            $table->bigInteger('amount_nano');
            $table->foreignUuid('generation_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at');

            // At most one charge per generation, whatever happens upstream.
            $table->unique(['generation_id', 'type']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
