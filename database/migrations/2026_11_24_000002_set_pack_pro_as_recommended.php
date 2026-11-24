<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('forfaits_publications')
            ->where('titre', 'Pack Standard')
            ->update([
                'prix' => 1500,
                'nombre_publications' => 5,
                'est_actif' => true,
                'est_populaire' => false,
                'ordre_affichage' => 1,
                'description' => 'Idéal pour débuter votre activité.',
                'updated_at' => now(),
            ]);

        DB::table('forfaits_publications')
            ->where('titre', 'Pack Pro')
            ->update([
                'prix' => 2500,
                'nombre_publications' => 10,
                'est_actif' => true,
                'est_populaire' => true,
                'ordre_affichage' => 2,
                'description' => 'Notre pack recommandé pour les agents et démarcheurs actifs.',
                'updated_at' => now(),
            ]);

        DB::table('forfaits_publications')
            ->where('titre', 'Pack Premium')
            ->update([
                'prix' => 5000,
                'nombre_publications' => 25,
                'est_actif' => true,
                'est_populaire' => false,
                'ordre_affichage' => 3,
                'description' => 'Pour une visibilité maximale et un gros volume.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('forfaits_publications')
            ->where('titre', 'Pack Pro')
            ->update(['est_populaire' => false, 'updated_at' => now()]);

        DB::table('forfaits_publications')
            ->where('titre', 'Pack Premium')
            ->update(['est_populaire' => true, 'updated_at' => now()]);
    }
};
