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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'telegram_chat_id')) {
                $table->string('telegram_chat_id')->nullable()->index();
            }

            if (!Schema::hasColumn('users', 'telegram_auth_token')) {
                $table->string('telegram_auth_token')->nullable()->index();
            }

            if (!Schema::hasColumn('users', 'telegram_verified_at')) {
                $table->timestamp('telegram_verified_at')->nullable();
            }

            if (!Schema::hasColumn('users', 'telegram_username')) {
                $table->string('telegram_username')->nullable();
            }

            if (!Schema::hasColumn('users', 'telegram_first_name')) {
                $table->string('telegram_first_name')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [
                'telegram_chat_id',
                'telegram_auth_token',
                'telegram_verified_at',
                'telegram_username',
                'telegram_first_name'
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};