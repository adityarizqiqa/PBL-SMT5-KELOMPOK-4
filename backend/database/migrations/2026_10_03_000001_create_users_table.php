<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            throw new RuntimeException('SmartPark migrations require MySQL 8.0.16 or newer.');
        }

        Schema::create('users', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('name', 100);
            $table->string('username', 50)->unique();
            $table->string('password', 255);
            $table->enum('role', ['ADMIN', 'PETUGAS']);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable();
            $table->dateTime('last_login_at')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            $table->index(['role', 'is_active']);
            $table->index('created_by');
            $table->foreign('created_by')->references('id')->on('users')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE users
                ADD CONSTRAINT users_name_nonempty CHECK (CHAR_LENGTH(TRIM(name)) > 0),
                ADD CONSTRAINT users_username_nonempty CHECK (CHAR_LENGTH(TRIM(username)) > 0),
                ADD CONSTRAINT users_is_active_boolean CHECK (is_active IN (0, 1))
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
