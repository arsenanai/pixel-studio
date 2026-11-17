<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->text('prompt');
            $table->string('size', 9);
            $table->string('quality', 6);
            $table->json('reference_images');

            $table->string('status', 10)->default('queued');
            $table->string('provider_job_id')->nullable()->unique();
            $table->json('rates')->nullable();

            $table->text('image_url')->nullable();
            $table->timestamp('image_url_expires_at')->nullable();

            $table->unsignedInteger('usage_input_text_tokens')->nullable();
            $table->unsignedInteger('usage_input_image_tokens')->nullable();
            $table->unsignedInteger('usage_output_tokens')->nullable();
            $table->unsignedInteger('usage_reasoning_tokens')->nullable();

            $table->bigInteger('estimated_provider_cost_nano');
            $table->bigInteger('estimated_charge_nano');
            $table->bigInteger('estimated_min_charge_nano');
            $table->bigInteger('estimated_max_charge_nano');
            $table->bigInteger('provider_cost_nano')->nullable();
            $table->bigInteger('charge_nano')->nullable();

            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();

            $table->unsignedSmallInteger('submit_attempts')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'updated_at']);
            $table->index('completed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
