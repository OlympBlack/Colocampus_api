<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Annonce;
use App\Models\BoostAnnonce;
use App\Services\KkiapayService;
use App\Support\ApiData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnnonceBoostController extends Controller
{
    protected KkiapayService $kkiapay;

    public const PACKS = [
        'boost_3j' => [
            'id' => 'boost_3j',
            'titre' => 'Boost 3 jours',
            'description' => 'Mise en avant express pendant 3 jours en tête de liste.',
            'prix' => 200,
            'duree_jours' => 3,
            'badge' => 'Express',
        ],
        'boost_7j' => [
            'id' => 'boost_7j',
            'titre' => 'Boost 7 jours (1 semaine)',
            'description' => 'Recommandé : visibilité maximale pendant 7 jours consécutifs.',
            'prix' => 500,
            'duree_jours' => 7,
            'badge' => 'Recommandé',
        ],
    ];

    public function __construct(KkiapayService $kkiapay)
    {
        $this->kkiapay = $kkiapay;
    }

    /**
     * Liste des packs de boost disponibles
     */
    public function packs(): JsonResponse
    {
        return response()->json([
            'data' => array_values(self::PACKS),
        ]);
    }

    /**
     * Activer le boost pour une annonce après paiement Kkiapay
     */
    public function boost(Request $request, Annonce $annonce): JsonResponse
    {
        $user = $request->user();

        // 1. Contrôle d'accès : seul le propriétaire ou l'admin peut booster
        if ($annonce->user_id !== $user->id && $user->role !== 'admin') {
            return response()->json([
                'message' => "Vous n'êtes pas autorisé à booster cette annonce.",
            ], 403);
        }

        // 2. Validation de la requête
        $validated = $request->validate([
            'pack_type' => ['required', 'string', 'in:boost_3j,boost_7j'],
            'transaction_id' => ['required', 'string', 'max:100'],
            'methode' => ['required', 'string', 'in:kkiapay'],
        ], [
            'pack_type.in' => 'Pack de boost sélectionné invalide.',
            'transaction_id.required' => 'La référence de paiement est obligatoire.',
        ]);

        $pack = self::PACKS[$validated['pack_type']];
        $transactionId = trim($validated['transaction_id']);

        // 3. Vérification de réutilisation de transaction
        $existing = BoostAnnonce::where('reference_transaction', $transactionId)->first();
        if ($existing) {
            return response()->json([
                'message' => 'Cette transaction a déjà été utilisée pour un boost.',
            ], 422);
        }

        // 4. Vérification de la transaction via l'API Kkiapay
        try {
            $details = $this->kkiapay->verifyTransaction($transactionId);

            if (!$details || ($details['status'] ?? '') !== 'SUCCESS') {
                Log::warning('Transaction Kkiapay invalide pour boost', [
                    'transaction_id' => $transactionId,
                    'details' => $details,
                ]);
                return response()->json([
                    'message' => "Le paiement n'a pas pu être confirmé par Kkiapay. Veuillez réessayer.",
                ], 422);
            }

            $montantPaye = (int) round((float) ($details['amount'] ?? 0));
            if ($montantPaye !== $pack['prix']) {
                Log::warning('Montant Kkiapay non conforme au pack boost', [
                    'transaction_id' => $transactionId,
                    'montant_paye' => $montantPaye,
                    'prix_attendu' => $pack['prix'],
                ]);
                return response()->json([
                    'message' => "Le montant payé ({$montantPaye} FCFA) ne correspond pas au prix du pack ({$pack['prix']} FCFA).",
                ], 422);
            }

            // 5. Mise à jour de l'annonce et enregistrement du boost en transaction
            return DB::transaction(function () use ($user, $annonce, $pack, $transactionId, $details) {
                // Si l'annonce est déjà boostée, on prolonge à partir de la date de fin actuelle
                $baseDate = ($annonce->boostee_jusqu_a && $annonce->boostee_jusqu_a->isFuture())
                    ? $annonce->boostee_jusqu_a
                    : now();

                $dateFin = $baseDate->copy()->addDays($pack['duree_jours']);

                $annonce->update([
                    'est_boostee' => true,
                    'boostee_jusqu_a' => $dateFin,
                ]);

                $boost = BoostAnnonce::create([
                    'user_id' => $user->id,
                    'annonce_id' => $annonce->id,
                    'pack_type' => $pack['id'],
                    'duree_jours' => $pack['duree_jours'],
                    'montant' => $pack['prix'],
                    'reference_transaction' => $transactionId,
                    'statut_paiement' => 'valide',
                    'methode_paiement' => 'kkiapay',
                    'devise' => 'XOF',
                    'payeur' => $this->extractPayer($details),
                    'date_debut' => now(),
                    'date_fin' => $dateFin,
                ]);

                Log::info('Annonce boostée avec succès', [
                    'annonce_id' => $annonce->id,
                    'user_id' => $user->id,
                    'pack' => $pack['id'],
                    'date_fin' => $dateFin->toDateTimeString(),
                    'transaction' => $transactionId,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => "🎉 Félicitations ! Votre annonce est boostée jusqu'au " . $dateFin->format('d/m/Y à H:i') . " !",
                    'data' => ApiData::annonce($annonce->fresh(['user', 'medias', 'universite'])),
                    'boost' => $boost,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Erreur lors du boost de l\'annonce', [
                'annonce_id' => $annonce->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => "Une erreur est survenue lors de l'activation du boost. Contactez le support.",
            ], 500);
        }
    }

    private function extractPayer(array $details): ?string
    {
        $customer = $details['customer'] ?? null;
        $source = is_array($customer) ? $customer : $details;
        $value = $source['email'] ?? $source['phone'] ?? $source['telephone'] ?? null;
        return is_string($value) && $value !== '' ? $value : null;
    }
}

