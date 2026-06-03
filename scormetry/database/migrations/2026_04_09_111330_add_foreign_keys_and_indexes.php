<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->foreign('teacher_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('subject_members', function (Blueprint $table) {
            $table->foreign('subject_id')->references('id')->on('subjects')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('subject_invitations', function (Blueprint $table) {
            $table->foreign('subject_id')->references('id')->on('subjects')->cascadeOnDelete();
        });

        Schema::table('rubrics', function (Blueprint $table) {
            $table->foreign('subject_id')->references('id')->on('subjects')->cascadeOnDelete();
            $table->index('subject_id');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->foreign('subject_id')->references('id')->on('subjects')->cascadeOnDelete();
            $table->index('subject_id');
        });

        Schema::table('team_members', function (Blueprint $table) {
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('papers', function (Blueprint $table) {
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->foreign('subject_id')->references('id')->on('subjects')->cascadeOnDelete();
            $table->index('subject_id');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->foreign('paper_id')->references('id')->on('papers')->cascadeOnDelete();
            $table->foreign('reviewer_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('paper_id');
            $table->index('reviewer_id');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['paper_id']);
            $table->dropForeign(['reviewer_id']);
            $table->dropIndex(['paper_id']);
            $table->dropIndex(['reviewer_id']);
        });

        Schema::table('papers', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropForeign(['subject_id']);
            $table->dropIndex(['subject_id']);
        });

        Schema::table('team_members', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropForeign(['user_id']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->dropIndex(['subject_id']);
        });

        Schema::table('rubrics', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->dropIndex(['subject_id']);
        });

        Schema::table('subject_invitations', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
        });

        Schema::table('subject_members', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->dropForeign(['user_id']);
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropForeign(['teacher_id']);
        });
    }
};
