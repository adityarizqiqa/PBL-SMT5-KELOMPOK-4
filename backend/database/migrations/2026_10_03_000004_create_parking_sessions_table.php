<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parking_sessions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('nim', 20);
            $table->enum('nim_input_method', ['QR', 'MANUAL']);

            $table->string('checkin_plate', 20);
            $table->string('checkin_original_image', 255)->nullable();
            $table->string('checkin_plate_crop', 255)->nullable();
            $table->decimal('checkin_ocr_confidence', 5, 2)->nullable();
            $table->dateTime('checkin_time');
            $table->foreignId('checkin_operational_session_id');

            $table->string('checkout_plate', 20)->nullable();
            $table->string('checkout_original_image', 255)->nullable();
            $table->string('checkout_plate_crop', 255)->nullable();
            $table->decimal('checkout_ocr_confidence', 5, 2)->nullable();
            $table->decimal('similarity_score', 5, 2)->nullable();
            $table->dateTime('checkout_time')->nullable();
            $table->foreignId('checkout_operational_session_id')->nullable();

            $table->enum('status', ['PARKED', 'SESUAI', 'VERIFIKASI_MANUAL'])->default('PARKED');

            // NULL allows repeated historical values, while active values remain unique.
            $table->string('active_nim', 20)->nullable()
                ->storedAs("CASE WHEN `status` = 'PARKED' THEN `nim` ELSE NULL END")
                ->unique();
            $table->string('active_plate', 20)->nullable()
                ->storedAs("CASE WHEN `status` = 'PARKED' THEN `checkin_plate` ELSE NULL END")
                ->unique();

            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            $table->index(['status', 'checkin_time']);
            $table->index('checkin_time');
            $table->index('checkin_operational_session_id');
            $table->index('checkout_operational_session_id');
            $table->foreign('checkin_operational_session_id')->references('id')->on('operational_sessions')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('checkout_operational_session_id')->references('id')->on('operational_sessions')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE parking_sessions
                ADD CONSTRAINT parking_sessions_nim_nonempty CHECK (
                    CHAR_LENGTH(TRIM(nim)) > 0
                    AND CHAR_LENGTH(nim) = CHAR_LENGTH(TRIM(nim))
                ),
                ADD CONSTRAINT parking_sessions_checkin_plate_canonical CHECK (
                    CHAR_LENGTH(checkin_plate) > 0
                    AND checkin_plate COLLATE utf8mb4_bin =
                        UPPER(REPLACE(REPLACE(TRIM(checkin_plate), ' ', ''), '-', '')) COLLATE utf8mb4_bin
                    AND CHAR_LENGTH(checkin_plate) =
                        CHAR_LENGTH(REPLACE(REPLACE(TRIM(checkin_plate), ' ', ''), '-', ''))
                ),
                ADD CONSTRAINT parking_sessions_checkout_plate_canonical CHECK (
                    checkout_plate IS NULL OR (
                        CHAR_LENGTH(checkout_plate) > 0
                        AND checkout_plate COLLATE utf8mb4_bin =
                            UPPER(REPLACE(REPLACE(TRIM(checkout_plate), ' ', ''), '-', '')) COLLATE utf8mb4_bin
                        AND CHAR_LENGTH(checkout_plate) =
                            CHAR_LENGTH(REPLACE(REPLACE(TRIM(checkout_plate), ' ', ''), '-', ''))
                    )
                ),
                ADD CONSTRAINT parking_sessions_checkin_confidence_range CHECK (
                    checkin_ocr_confidence IS NULL OR checkin_ocr_confidence BETWEEN 0 AND 100
                ),
                ADD CONSTRAINT parking_sessions_checkout_confidence_range CHECK (
                    checkout_ocr_confidence IS NULL OR checkout_ocr_confidence BETWEEN 0 AND 100
                ),
                ADD CONSTRAINT parking_sessions_similarity_range CHECK (
                    similarity_score IS NULL OR similarity_score BETWEEN 0 AND 100
                ),
                ADD CONSTRAINT parking_sessions_checkout_time_order CHECK (
                    checkout_time IS NULL OR checkout_time >= checkin_time
                ),
                ADD CONSTRAINT parking_sessions_checkout_state CHECK (
                    (
                        status = 'PARKED'
                        AND checkout_plate IS NULL
                        AND checkout_original_image IS NULL
                        AND checkout_plate_crop IS NULL
                        AND checkout_ocr_confidence IS NULL
                        AND similarity_score IS NULL
                        AND checkout_time IS NULL
                        AND checkout_operational_session_id IS NULL
                    )
                    OR (
                        status IN ('SESUAI', 'VERIFIKASI_MANUAL')
                        AND checkout_plate IS NOT NULL
                        AND checkout_time IS NOT NULL
                        AND checkout_operational_session_id IS NOT NULL
                    )
                )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('parking_sessions');
    }
};
