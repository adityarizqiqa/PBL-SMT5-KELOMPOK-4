<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_sessions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('parking_location_id');
            $table->enum('shift', ['PAGI', 'SIANG', 'MALAM']);
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            $table->index(['user_id', 'is_active']);
            $table->index(['parking_location_id', 'is_active']);
            $table->foreign('user_id')->references('id')->on('users')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('parking_location_id')->references('id')->on('parking_locations')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE operational_sessions
                ADD CONSTRAINT operational_sessions_active_state CHECK (
                    (is_active = 1 AND ended_at IS NULL)
                    OR (is_active = 0 AND ended_at IS NOT NULL)
                ),
                ADD CONSTRAINT operational_sessions_time_order CHECK (
                    ended_at IS NULL OR ended_at >= started_at
                )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_sessions');
    }
};
