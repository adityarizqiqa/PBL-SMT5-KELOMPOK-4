<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parking_locations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by');
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            $table->index('created_by');
            $table->foreign('created_by')->references('id')->on('users')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE parking_locations
                ADD CONSTRAINT parking_locations_code_nonempty CHECK (CHAR_LENGTH(TRIM(code)) > 0),
                ADD CONSTRAINT parking_locations_name_nonempty CHECK (CHAR_LENGTH(TRIM(name)) > 0),
                ADD CONSTRAINT parking_locations_is_active_boolean CHECK (is_active IN (0, 1))
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('parking_locations');
    }
};
