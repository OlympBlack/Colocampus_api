<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePubliciteBoutiqueRequest;
use App\Support\ApiData;
use Illuminate\Http\JsonResponse;

class ManagedPubliciteController extends Controller
{
    public function store(StorePubliciteBoutiqueRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'boutique') {
            return response()->json(['message' => 'Seules les boutiques peuvent publier un article.'], 403);
        }

        $validated = $request->validated();

        $storedPaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                if ($file && $file->isValid()) {
                    $storedPaths[] = $file->store('publicites', 'public');
                }
            }
        }

        $thumbIndex = (int) ($validated['thumbnail_index'] ?? 0);
        $thumbnailPath = null;

        if (!empty($storedPaths)) {
            $thumbnailPath = $storedPaths[$thumbIndex] ?? $storedPaths[0];
        } elseif ($request->hasFile('banniere')) {
            $stored = $request->file('banniere')->store('publicites', 'public');
            $thumbnailPath = $stored;
            $storedPaths = [$stored];
        }

        $publicite = $user->publicitesBoutiques()->create([
            'titre_promo' => $validated['titre_promo'],
            'prix' => isset($validated['prix']) ? (int) $validated['prix'] : null,
            'description' => $validated['description'] ?? null,
            'categorie' => $validated['categorie'] ?? ($user->profilBoutique?->categorie ?? 'Équipements & Services'),
            'url_image_banniere' => $thumbnailPath,
            'images' => $storedPaths,
            'lien_action' => $validated['lien_action'] ?? ('#/boutiques'),
            'quartier_cible' => $validated['quartier_cible'] ?? null,
            'vues_count' => 0,
            'clics_count' => 0,
        ]);

        return response()->json([
            'message' => 'Article publié avec succès sur l\'e-shop boutique.',
            'data' => ApiData::publicite($publicite->fresh(['user.profilBoutique'])),
        ], 201);
    }
}
