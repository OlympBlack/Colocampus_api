<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Annonce;
use App\Models\ContactDeblocage;
use App\Support\ApiData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnnonceContactController extends Controller
{
    /**
     * Débloque le numéro d'un annonceur uniquement pour un compte connecté.
     * Un contact gratuit est autorisé après OTP ; la certification (KYC) est
     * requise pour les suivants. Chaque déblocage est journalisé.
     */
    public function unlock(Request $request, Annonce $annonce): JsonResponse
    {
        $user = $request->user();

        if ($annonce->statut_publication !== 'active' || $annonce->statut_disponibilite === 'taken') {
            return response()->json(['message' => 'Cette annonce n\'est plus disponible.'], 404);
        }

        if ((int) $annonce->user_id === (int) $user->id) {
            return response()->json(['message' => 'Vous ne pouvez pas débloquer le contact de votre propre annonce.'], 403);
        }

        if (!$user->isKycValidated()) {
            $dejaDebloque = ContactDeblocage::where('user_id', $user->id)->exists();
            if ($dejaDebloque) {
                return response()->json([
                    'message' => 'Votre contact gratuit a déjà été utilisé. Faites certifier votre compte pour continuer.',
                    'verification_required' => true,
                ], 403);
            }
        }

        $deblocage = DB::transaction(function () use ($request, $user, $annonce) {
            return ContactDeblocage::firstOrCreate(
                ['user_id' => $user->id, 'annonce_id' => $annonce->id],
                [
                    'annonceur_id' => $annonce->user_id,
                    'ip' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 255),
                ]
            );
        });

        Log::info('Contact annonceur débloqué', [
            'deblocage_id' => $deblocage->id,
            'user_id' => $user->id,
            'annonce_id' => $annonce->id,
            'annonceur_id' => $annonce->user_id,
        ]);

        return response()->json([
            'data' => ApiData::annonce($annonce, ['include_contact' => true]),
        ]);
    }
}
