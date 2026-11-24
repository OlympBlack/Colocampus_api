<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $packs = [
            [
                'titre' => 'Pack Standard',
                'description' => 'Idéal pour débuter votre activité.',
                'prix' => 1500,
                'nombre_publications' => 5,
                'est_actif' => true,
                'est_populaire' => false,
                'ordre_affichage' => 1,
            ],
            [
                'titre' => 'Pack Pro',
                'description' => 'Notre pack le plus populaire pour les agents actifs.',
                'prix' => 2500,
                'nombre_publications' => 10,
                'est_actif' => true,
                'est_populaire' => true,
                'ordre_affichage' => 2,
            ],
            [
                'titre' => 'Pack Premium',
                'description' => 'Pour une visibilité maximale et un gros volume.',
                'prix' => 5000,
                'nombre_publications' => 25,
                'est_actif' => true,
                'est_populaire' => false,
                'ordre_affichage' => 3,
            ],
        ];

        foreach ($packs as $pack) {
            $updated = DB::table('forfaits_publications')
                ->where('titre', $pack['titre'])
                ->update(array_merge($pack, ['updated_at' => now()]));

            if (!$updated) {
                DB::table('forfaits_publications')->insert(array_merge($pack, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }

        DB::table('forfaits_publications')
            ->whereNotIn('titre', array_column($packs, 'titre'))
            ->update(['est_actif' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        $previousPacks = [
            ['titre' => 'Pack Standard', 'prix' => 5000, 'nombre_publications' => 5, 'ordre_affichage' => 1, 'est_populaire' => false],
            ['titre' => 'Pack Pro', 'prix' => 12000, 'nombre_publications' => 15, 'ordre_affichage' => 2, 'est_populaire' => true],
            ['titre' => 'Pack Premium', 'prix' => 25000, 'nombre_publications' => 40, 'ordre_affichage' => 3, 'est_populaire' => false],
        ];

        foreach ($previousPacks as $pack) {
            DB::table('forfaits_publications')
                ->where('titre', $pack['titre'])
                ->update(array_merge($pack, ['est_actif' => true, 'updated_at' => now()]));
        }
    }
};
