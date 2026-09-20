<?php

namespace App\Services;

class EmailService
{
    private string $fromEmail;
    private string $fromName;
    private bool $useMock;
    private array $sentEmails = [];

    public function __construct(
        string $fromEmail = 'noreply@dahira.sn',
        string $fromName = 'Dahira A Khiba-i Rassouloulahi',
        bool $useMock = false
    ) {
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
        $this->useMock = $useMock;
    }

    /**
     * Envoie le code de vérification par email pour réinitialiser le mot de passe.
     */
    public function sendPasswordResetCode(string $toEmail, string $userName, string $code): bool
    {
        $subject = "Votre code de réinitialisation : {$code} — Dahira AKR";
        $html = $this->buildResetPasswordHtml($userName, $code);

        return $this->send($toEmail, $subject, $html);
    }

    /**
     * Envoi générique d'un email HTML
     */
    public function send(string $toEmail, string $subject, string $htmlBody): bool
    {
        if ($this->useMock) {
            $this->sentEmails[] = [
                'to' => $toEmail,
                'subject' => $subject,
                'body' => $htmlBody,
                'date' => date('Y-m-d H:i:s')
            ];
            return true;
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . sprintf('=?UTF-8?B?%s?= <%s>', base64_encode($this->fromName), $this->fromEmail),
            'Reply-To: ' . $this->fromEmail,
            'X-Mailer: PHP/' . phpversion()
        ];

        // Subject encodé UTF-8
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        return @mail($toEmail, $encodedSubject, $htmlBody, implode("\r\n", $headers));
    }

    /**
     * Récupère la liste des emails envoyés (en mode mock / tests)
     */
    public function getSentEmails(): array
    {
        return $this->sentEmails;
    }

    /**
     * Génère le template HTML de l'email
     */
    private function buildResetPasswordHtml(string $userName, string $code): string
    {
        $safeName = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
        $year = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation de mot de passe</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; color: #1e293b;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="table-layout: fixed; background-color: #f1f5f9; padding: 30px 10px;">
        <tr>
            <td align="center">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 520px; background-color: #ffffff; border-radius: 20px; overflow: hidden; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);">
                    <!-- Header -->
                    <tr>
                        <td align="center" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); padding: 32px 20px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 20px; font-weight: 700; letter-spacing: 0.5px;">Dahira AKR</h1>
                            <p style="color: #94a3b8; margin: 6px 0 0; font-size: 12px;">Dahira A Khiba-i Rassouloulahi (SAWS)</p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 32px 28px 24px;">
                            <h2 style="color: #0f172a; margin: 0 0 12px; font-size: 18px; font-weight: 700;">Réinitialisation de votre mot de passe</h2>
                            <p style="color: #475569; font-size: 14px; line-height: 1.6; margin: 0 0 20px;">
                                Bonjour <strong>{$safeName}</strong>,<br>
                                Vous avez demandé à réinitialiser le mot de passe de votre compte. Utilisez le code de vérification ci-dessous pour poursuivre :
                            </p>

                            <!-- Code Box -->
                            <div style="background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 14px; padding: 20px; text-align: center; margin: 24px 0;">
                                <span style="display: block; font-size: 11px; text-transform: uppercase; font-weight: 600; color: #64748b; margin-bottom: 8px; letter-spacing: 1px;">Code de vérification</span>
                                <span style="display: inline-block; font-family: 'Courier New', Courier, monospace; font-size: 34px; font-weight: 800; color: #0f172a; letter-spacing: 8px;">{$code}</span>
                                <span style="display: block; font-size: 11px; color: #d97706; margin-top: 8px; font-weight: 600;">⏱ Valable pendant 15 minutes</span>
                            </div>

                            <p style="color: #64748b; font-size: 12px; line-height: 1.5; margin: 20px 0 0; border-top: 1px solid #f1f5f9; padding-top: 18px;">
                                🔒 <strong>Sécurité :</strong> Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet email en toute sécurité. Votre mot de passe actuel restera inchangé et personne ne peut accéder à votre compte sans ce code.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8fafc; padding: 18px 24px; text-align: center; border-top: 1px solid #e2e8f0;">
                            <p style="color: #94a3b8; font-size: 11px; margin: 0;">
                                © {$year} Dahira A Khiba-i Rassouloulahi. Tous droits réservés.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}
