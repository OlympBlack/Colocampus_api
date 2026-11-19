<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    private const GENERIC_MESSAGE = 'Si cette adresse est associée à un compte, un lien de réinitialisation a été envoyé.';

    public function requestReset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $email = mb_strtolower(trim($validated['email']));
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            $plainToken = Str::random(64);
            DB::table('password_reset_tokens')->insert([
                'email' => $email,
                'token' => hash('sha256', $plainToken),
                'created_at' => now(),
            ]);

            $frontendUrl = rtrim((string) config('app.frontend_url', ''), '/');
            if ($frontendUrl === '') {
                $frontendUrl = rtrim((string) config('app.url'), '/');
            }

            $resetUrl = $frontendUrl . '/#/reinitialiser-mot-de-passe?email=' . rawurlencode($email) . '&token=' . rawurlencode($plainToken);

            try {
                Mail::to($user->email)->send(new ResetPasswordMail($resetUrl, $user->prenoms));
            } catch (\Throwable $e) {
                Log::error('Envoi du mail de réinitialisation impossible', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['message' => self::GENERIC_MESSAGE]);
    }

    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'token' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $email = mb_strtolower(trim($validated['email']));
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (
            ! $record ||
            ! hash_equals($record->token, hash('sha256', $validated['token'])) ||
            ! $record->created_at ||
            now()->diffInMinutes($record->created_at) > 60
        ) {
            if ($record) {
                DB::table('password_reset_tokens')->where('email', $email)->delete();
            }

            return response()->json([
                'message' => 'Ce lien de réinitialisation est invalide ou a expiré.',
            ], 422);
        }

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $user) {
            return response()->json(['message' => 'Ce lien de réinitialisation est invalide ou a expiré.'], 422);
        }

        $user->password = Hash::make($validated['password']);
        $user->save();

        $user->tokens()->delete();
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return response()->json(['message' => 'Votre mot de passe a été réinitialisé. Vous pouvez vous connecter.']);
    }
}
