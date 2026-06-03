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
        Schema::table('papers', function (Blueprint $table) {
            $table->decimal('final_score_override', 6, 2)->nullable()->after('final_score');
            $table->text('final_score_override_reason')->nullable()->after('final_score_override');
            $table->foreignId('final_score_override_by')->nullable()->after('final_score_override_reason')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('papers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('final_score_override_by');
            $table->dropColumn(['final_score_override', 'final_score_override_reason']);
        });
    }
};
