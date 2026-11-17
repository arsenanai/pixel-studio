<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            // The transport-level dedupe: a redelivered event cannot be inserted twice.
            $table->string('provider_event_id')->unique();
            $table->string('type', 40);
            $table->foreignUuid('generation_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('delivery_attempt')->nullable();
            // The exact bytes that were signed, for auditing.
            $table->longText('payload');
            $table->timestamp('received_at');

            $table->index('generation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
