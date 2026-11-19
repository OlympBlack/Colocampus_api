<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('achats_forfaits', function (Blueprint $table) {
            if (!Schema::hasColumn('achats_forfaits', 'devise')) {
                $table->string('devise', 8)->nullable()->after('reference_transaction');
            }
            if (!Schema::hasColumn('achats_forfaits', 'payeur')) {
                $table->string('payeur')->nullable()->after('devise');
            }
            if (!Schema::hasColumn('achats_forfaits', 'verifie_le')) {
                $table->timestamp('verifie_le')->nullable()->after('payeur');
            }
        });
    }

    public function down(): void
    {
        Schema::table('achats_forfaits', function (Blueprint $table) {
            $table->dropColumn(['devise', 'payeur', 'verifie_le']);
        });
    }
};
