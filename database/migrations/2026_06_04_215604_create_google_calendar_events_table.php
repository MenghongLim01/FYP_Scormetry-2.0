<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('defense_attempt_id')->constrained()->cascadeOnDelete();
            $table->string('google_event_id')->nullable();
            $table->string('status')->default('synced');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'defense_attempt_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_calendar_events');
    }
};
