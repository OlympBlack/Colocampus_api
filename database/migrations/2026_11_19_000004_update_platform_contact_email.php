<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('parametres_plateforme')->update([
            'email_contact' => 'contact@colocampus.bj',
            'email_support' => 'contact@colocampus.bj',
        ]);
    }

    public function down(): void
    {
    }
};
