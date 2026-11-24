<?php

namespace Database\Seeders;

use App\Models\ForfaitPublication;
use Illuminate\Database\Seeder;

class ForfaitPublicationSeeder extends Seeder
{
    public function run(): void
    {
        ForfaitPublication::updateOrCreate(
            ['titre' => 'Pack Standard'],
            [
                'description' => 'Idéal pour débuter votre activité.',
                'prix' => 1500,
                'nombre_publications' => 5,
                'est_actif' => true,
                'est_populaire' => false,
                'ordre_affichage' => 1,
            ]
        );

        ForfaitPublication::updateOrCreate(
            ['titre' => 'Pack Pro'],
            [
                'description' => 'Pour les agents et démarcheurs réguliers.',
                'prix' => 2500,
                'nombre_publications' => 10,
                'est_actif' => true,
                'est_populaire' => false,
                'ordre_affichage' => 2,
            ]
        );

        ForfaitPublication::updateOrCreate(
            ['titre' => 'Pack Premium'],
            [
                'description' => 'Le pack recommandé pour une visibilité maximale et un gros volume.',
                'prix' => 5000,
                'nombre_publications' => 25,
                'est_actif' => true,
                'est_populaire' => true,
                'ordre_affichage' => 3,
            ]
        );
    }
}
