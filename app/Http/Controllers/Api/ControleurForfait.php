<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ServiceForfaitPublications;
use App\Models\ForfaitPublication;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ControleurForfait extends Controller
{
    protected $serviceForfait;

    public function __construct(ServiceForfaitPublications $serviceForfait)
    {
        $this->serviceForfait = $serviceForfait;
    }

    /**
     * Voir tous les forfaits disponibles
     */
    public function index(): JsonResponse
    {
        $forfaits = $this->serviceForfait->listerForfaitsActifs();
        return response()->json([
            'data' => $forfaits
        ]);
    }

    /**
     * Acheter un forfait : le paiement Kkiapay est obligatoire (aucun achat gratuit).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'forfait_id' => 'required|integer|exists:forfaits_publications,id',
            'transaction_id' => 'required|string|max:100',
            'methode' => 'required|string|in:kkiapay',
        ], [
            'forfait_id.exists' => "Ce pack n'existe pas.",
            'transaction_id.required' => 'La référence de paiement est obligatoire.',
        ]);

        $forfait = ForfaitPublication::where('id', $validated['forfait_id'])
            ->where('est_actif', true)
            ->firstOrFail();
        $user = $request->user();

        try {
            $achat = $this->serviceForfait->validerAchatKkiapay($user, $forfait, $validated['transaction_id']);

            $servicePortefeuille = new \App\Services\ServicePortefeuillePublications();
            $resume = $servicePortefeuille->obtenirResumePortefeuille($user);

            return response()->json([
                'success' => true,
                'message' => "Pack « {$forfait->titre} » ({$forfait->nombre_publications} crédits) activé avec succès !",
                'achat' => $achat,
                'portefeuille' => $resume,
            ]);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Erreur lors de la validation d\'un paiement', [
                'user_id' => $user->id,
                'forfait_id' => $forfait->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'La validation du paiement a échoué. Veuillez réessayer ou contacter le support avec votre référence de paiement.',
            ], 502);
        }
    }
}

