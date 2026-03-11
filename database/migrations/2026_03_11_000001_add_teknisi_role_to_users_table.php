<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Pastikan kolom role diubah agar mengizinkan nilai 'teknisi'
        if (Schema::hasTable('users')) {
            DB::statement("
                ALTER TABLE `users`
                MODIFY COLUMN `role`
                    ENUM('admin','nasabah','warga','teknisi')
                    CHARACTER SET utf8mb4
                    COLLATE utf8mb4_unicode_ci
                    NOT NULL
                    DEFAULT 'warga'
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Kembalikan ke enum awal tanpa 'teknisi'
        if (Schema::hasTable('users')) {
            DB::statement("
                ALTER TABLE `users`
                MODIFY COLUMN `role`
                    ENUM('admin','nasabah','warga')
                    CHARACTER SET utf8mb4
                    COLLATE utf8mb4_unicode_ci
                    NOT NULL
                    DEFAULT 'warga'
            ");
        }
    }
};

