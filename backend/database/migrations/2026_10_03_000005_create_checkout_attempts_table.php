<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_attempts', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('parking_session_id');
            $table->foreignId('operational_session_id');
            $table->string('scanned_plate', 20);
            $table->string('original_image', 255)->nullable();
            $table->string('plate_crop', 255)->nullable();
            $table->decimal('ocr_confidence', 5, 2)->nullable();
            $table->decimal('similarity_score', 5, 2)->nullable();
            $table->enum('result', ['SESUAI', 'VERIFIKASI_MANUAL', 'TIDAK_SESUAI']);
            $table->text('note')->nullable();
            $table->dateTime('attempted_at');

            $table->unsignedBigInteger('successful_parking_session_id')->nullable()
                ->storedAs("CASE WHEN `result` IN ('SESUAI', 'VERIFIKASI_MANUAL') THEN `parking_session_id` ELSE NULL END");
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            $table->index(['parking_session_id', 'attempted_at']);
            $table->index('operational_session_id');
            $table->unique('successful_parking_session_id', 'checkout_attempts_one_success_per_session');
            $table->foreign('parking_session_id')->references('id')->on('parking_sessions')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('operational_session_id')->references('id')->on('operational_sessions')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE checkout_attempts
                ADD CONSTRAINT checkout_attempts_scanned_plate_canonical CHECK (
                    CHAR_LENGTH(scanned_plate) > 0
                    AND scanned_plate COLLATE utf8mb4_bin =
                        UPPER(REPLACE(REPLACE(TRIM(scanned_plate), ' ', ''), '-', '')) COLLATE utf8mb4_bin
                    AND CHAR_LENGTH(scanned_plate) =
                        CHAR_LENGTH(REPLACE(REPLACE(TRIM(scanned_plate), ' ', ''), '-', ''))
                ),
                ADD CONSTRAINT checkout_attempts_confidence_range CHECK (
                    ocr_confidence IS NULL OR ocr_confidence BETWEEN 0 AND 100
                ),
                ADD CONSTRAINT checkout_attempts_similarity_range CHECK (
                    similarity_score IS NULL OR similarity_score BETWEEN 0 AND 100
                ),
                ADD CONSTRAINT checkout_attempts_manual_note_required CHECK (
                    result <> 'VERIFIKASI_MANUAL'
                    OR (note IS NOT NULL AND CHAR_LENGTH(TRIM(note)) > 0)
                )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_attempts');
    }
};
