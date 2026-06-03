<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->timestamp('locked_at')->nullable()->after('is_submitted');
            $table->timestamp('unlocked_at')->nullable()->after('locked_at');
            $table->text('unlock_reason')->nullable()->after('unlocked_at');
            $table->foreignId('unlocked_by')->nullable()->after('unlock_reason')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unlocked_by');
            $table->dropColumn(['locked_at', 'unlocked_at', 'unlock_reason']);
        });
    }
};
