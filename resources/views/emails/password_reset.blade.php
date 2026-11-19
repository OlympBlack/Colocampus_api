<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Réinitialisation du mot de passe</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;padding:32px;">
                    <tr>
                        <td>
                            <h1 style="margin:0 0 16px;font-size:22px;">Bonjour {{ $userName ?: 'ColoCampus' }},</h1>
                            <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#475569;">
                                Une demande de réinitialisation de mot de passe a été effectuée pour votre compte.
                                Ce lien expire dans 60 minutes.
                            </p>
                            <p style="margin:0 0 24px;">
                                <a href="{{ $resetUrl }}" style="display:inline-block;background:#a4c639;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:10px;font-weight:bold;">
                                    Réinitialiser mon mot de passe
                                </a>
                            </p>
                            <p style="margin:0;font-size:13px;line-height:1.6;color:#64748b;">
                                Si vous n'êtes pas à l'origine de cette demande, ignorez cet email. Votre mot de passe restera inchangé.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
