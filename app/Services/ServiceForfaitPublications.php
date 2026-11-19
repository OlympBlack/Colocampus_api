<?php

namespace App\Services;

use App\Models\ForfaitPublication;
use App\Models\AchatForfait;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ServiceForfaitPublications
{
    protected KkiapayService $kkiapay;

    public function __construct(KkiapayService $kkiapay)
    {
        $this->kkiapay = $kkiapay;
    }

    public function listerForfaitsActifs()
    {
        $forfaits = ForfaitPublication::where('est_actif', true)
            ->orderBy('ordre_affichage')
            ->orderBy('id')
            ->get();

        return $forfaits->unique(function ($item) {
            return trim(mb_strtolower($item->titre));
        })->values();
    }

    /**
     * Crédite le portefeuille. Privé : aucun crédit ne peut être ajouté sans
     * vérification effective d'un paiement auprès de Kkiapay.
     */
    private function acheterForfait(User $user, ForfaitPublication $forfait, string $transactionId, array $details)
    {
        return DB::transaction(function () use ($user, $forfait, $transactionId, $details) {
            $existing = AchatForfait::where('reference_transaction', $transactionId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing->load('forfait');
            }

            $achat = AchatForfait::create([
                'user_id'               => $user->id,
                'forfait_id'            => $forfait->id,
                'nombre_publications'   => $forfait->nombre_publications,
                'montant'               => $details['amount'] ?? $forfait->prix,
                'statut_paiement'       => 'valide',
                'methode_paiement'      => 'kkiapay',
                'reference_transaction' => $transactionId,
                'devise'                => $details['currency'] ?? 'XOF',
                'payeur'                => $this->extractPayer($details),
                'verifie_le'            => now(),
            ]);

            $servicePortefeuille = new ServicePortefeuillePublications();
            $servicePortefeuille->ajouterCreditsAchetes($user, $forfait->nombre_publications, $achat->id);

            Log::info('Paiement Kkiapay vérifié et crédits ajoutés', [
                'user_id' => $user->id,
                'forfait_id' => $forfait->id,
                'credits' => $forfait->nombre_publications,
                'transaction' => $transactionId,
            ]);

            return $achat;
        });
    }

    /**
     * Valider un achat : le serveur vérifie lui-même la transaction Kkiapay
     * (montant exact, devise, identité du payeur, idempotence). Le client ne
     * transmet qu'un identifiant de transaction, jamais un état de paiement.
     */
    public function validerAchatKkiapay(User $user, ForfaitPublication $forfait, string $transactionId)
    {
        $transactionId = trim($transactionId);
        if (!preg_match('/^[A-Za-z0-9_-]{6,100}$/', $transactionId)) {
            throw new \InvalidArgumentException('Référence de transaction invalide.');
        }

        if (!$forfait->est_actif) {
            throw new \InvalidArgumentException("Ce pack n'est plus disponible.");
        }

        $achatExistant = AchatForfait::where('reference_transaction', $transactionId)->first();
        if ($achatExistant) {
            if ($achatExistant->user_id !== $user->id || $achatExistant->forfait_id !== $forfait->id) {
                Log::warning('Tentative de réutilisation d\'une transaction par un autre compte/pack', [
                    'transaction' => $transactionId,
                    'user_id' => $user->id,
                ]);
                throw new \InvalidArgumentException('Cette transaction a déjà été utilisée.');
            }
            return $achatExistant->load('forfait');
        }

        $details = $this->kkiapay->verifyTransaction($transactionId);

        if (!$details || ($details['status'] ?? '') !== 'SUCCESS') {
            Log::warning('Transaction Kkiapay non confirmée', ['transaction' => $transactionId]);
            throw new \RuntimeException("La transaction n'a pas pu être confirmée. Vérifiez votre référence de paiement.");
        }

        $reportedId = (string) ($details['transactionId'] ?? $details['transaction_id'] ?? $transactionId);
        if ($reportedId !== $transactionId) {
            throw new \RuntimeException('La référence de transaction ne correspond pas au paiement.');
        }

        $montantPaye = (int) round((float) ($details['amount'] ?? 0));
        $prix = (int) round((float) $forfait->prix);
        if ($montantPaye !== $prix) {
            Log::warning('Montant Kkiapay non conforme au pack', [
                'transaction' => $transactionId,
                'montant_paye' => $montantPaye,
                'prix_forfait' => $prix,
            ]);
            throw new \RuntimeException('Le montant payé ne correspond pas à ce pack.');
        }

        $devise = strtoupper((string) ($details['currency'] ?? 'XOF'));
        if (!in_array($devise, ['XOF', 'FCFA', ''], true)) {
            throw new \RuntimeException('La devise du paiement est invalide.');
        }

        $payeur = $this->extractPayer($details);
        if ($payeur !== null && !$this->payerMatchesUser($payeur, $user)) {
            Log::warning('Payeur Kkiapay différent du compte connecté', [
                'transaction' => $transactionId,
                'user_id' => $user->id,
            ]);
            throw new \RuntimeException("Ce paiement n'est pas associé à votre compte.");
        }

        return $this->acheterForfait($user, $forfait, $transactionId, [
            'amount' => $montantPaye,
            'currency' => $devise,
        ]);
    }

    private function extractPayer(array $details): ?string
    {
        $customer = $details['customer'] ?? null;
        $source = is_array($customer) ? $customer : $details;

        $value = $source['email'] ?? $source['phone'] ?? $source['telephone'] ?? $source['customer_email'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function payerMatchesUser(string $payer, User $user): bool
    {
        $normalize = static fn (string $value): string => strtolower(preg_replace('/[^a-z0-9]/i', '', $value) ?? '');

        $normalizedPayer = $normalize($payer);
        $candidates = array_filter([$user->email, $user->telephone], static fn ($value) => is_string($value) && $value !== '');

        foreach ($candidates as $candidate) {
            if ($normalizedPayer === $normalize($candidate)) return true;
            // Le numéro peut être enregistré au format international ou local (229...).
            if (strlen($normalizedPayer) === 8 && str_ends_with($normalize((string) $candidate), $normalizedPayer)) return true;
        }

        return false;
    }
}
