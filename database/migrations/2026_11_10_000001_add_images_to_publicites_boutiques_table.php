<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('publicites_boutiques', function (Blueprint $table) {
            $table->json('images')->nullable()->after('url_image_banniere');
        });
    }

    public function down(): void
    {
        Schema::table('publicites_boutiques', function (Blueprint $table) {
            $table->dropColumn('images');
        });
    }
};
