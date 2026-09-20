<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Sends verification/reset emails two possible ways:
 *
 * 1. Resend's HTTP API (https://resend.com), if RESEND_API_KEY is set —
 *    goes over plain HTTPS (port 443), which every host allows, including
 *    Railway's free/trial/hobby plans, which block raw SMTP entirely.
 * 2. PHPMailer over SMTP, if RESEND_API_KEY is NOT set — used for local
 *    development (MAMP) or any host where SMTP genuinely isn't blocked.
 *
 * Whichever path is used, a failure here is never allowed to crash the
 * calling request — the account itself is already safely created in the
 * database by the time this runs, so email delivery failing should
 * degrade gracefully (return false), not take down the whole response.
 */
class Mailer
{
    private static function client(array $mail): PHPMailer
    {
        $phpMailer = new PHPMailer(true);
        $phpMailer->isSMTP();
        $phpMailer->Host = $mail['host'];
        $phpMailer->SMTPAuth = true;
        $phpMailer->Username = $mail['user'];
        $phpMailer->Password = $mail['pass'];
        $phpMailer->SMTPSecure = $mail['encryption'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $phpMailer->Port = $mail['port'];
        $phpMailer->setFrom($mail['from_email'], $mail['from_name']);
        $phpMailer->isHTML(true);
        $phpMailer->CharSet = 'UTF-8';
        $phpMailer->Timeout = 10;
        $phpMailer->SMTPKeepAlive = false;

        return $phpMailer;
    }

    private static function sendViaResend(array $mail, string $toEmail, string $toName, string $subject, string $bodyHtml, string $bodyText): bool
    {
        $payload = json_encode([
            'from' => "{$mail['from_name']} <{$mail['from_email']}>",
            'to' => [$toEmail],
            'subject' => $subject,
            'html' => $bodyHtml,
            'text' => $bodyText,
        ]);

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $mail['resend_api_key'],
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("[Mailer/Resend] cURL error: {$curlError}");
            return false;
        }
        if ($status < 200 || $status >= 300) {
            error_log("[Mailer/Resend] HTTP {$status}: {$response}");
            return false;
        }
        return true;
    }

    private static function sendViaSmtp(array $mail, string $toEmail, string $toName, string $subject, string $bodyHtml, string $bodyText): bool
    {
        try {
            $phpMailer = self::client($mail);
            $phpMailer->addAddress($toEmail, $toName);
            $phpMailer->Subject = $subject;
            $phpMailer->Body = $bodyHtml;
            $phpMailer->AltBody = $bodyText;
            $phpMailer->send();
            return true;
        } catch (PHPMailerException $e) {
            error_log('[Mailer/SMTP] send failed: ' . $e->getMessage());
            return false;
        }
    }

    private static function send(string $toEmail, string $toName, string $subject, string $bodyHtml, string $bodyText): bool
    {
        require_once __DIR__ . '/../config/config.php';
        $config = ffms_config();
        $mail = $config['mail'];

        try {
            if (!empty($mail['resend_api_key'])) {
                return self::sendViaResend($mail, $toEmail, $toName, $subject, $bodyHtml, $bodyText);
            }
            if (!empty($mail['host'])) {
                return self::sendViaSmtp($mail, $toEmail, $toName, $subject, $bodyHtml, $bodyText);
            }
            error_log("[Mailer] No email method configured — would have sent '{$subject}' to {$toEmail}");
            return false;
        } catch (Throwable $e) {
            error_log('[Mailer] unexpected failure: ' . $e->getMessage());
            return false;
        }
    }

    public static function sendVerificationEmail(string $toEmail, string $firstName, string $rawToken): bool
    {
        require_once __DIR__ . '/../config/config.php';
        $config = ffms_config();
        $link = rtrim($config['app']['frontend_url'], '/') . '/verify-email.html?token=' . urlencode($rawToken) . '&email=' . urlencode($toEmail);

        $html = self::layout(
            "Confirm your email",
            "Hi {$firstName},",
            "Thanks for creating an FFMS account. Confirm your email address to activate it — this link expires in 48 hours.",
            $link,
            "Confirm email"
        );
        $text = "Hi {$firstName},\n\nConfirm your FFMS account by visiting:\n{$link}\n\nThis link expires in 48 hours.";

        return self::send($toEmail, $firstName, 'Confirm your FFMS account', $html, $text);
    }

    public static function sendPasswordResetEmail(string $toEmail, string $firstName, string $rawToken): bool
    {
        require_once __DIR__ . '/../config/config.php';
        $config = ffms_config();
        $link = rtrim($config['app']['frontend_url'], '/') . '/reset-password.html?token=' . urlencode($rawToken) . '&email=' . urlencode($toEmail);

        $html = self::layout(
            "Reset your password",
            "Hi {$firstName},",
            "We received a request to reset your FFMS password. This link expires in 60 minutes. If you didn't request this, you can safely ignore this email.",
            $link,
            "Reset password"
        );
        $text = "Hi {$firstName},\n\nReset your FFMS password by visiting:\n{$link}\n\nThis link expires in 60 minutes. If you didn't request this, ignore this email.";

        return self::send($toEmail, $firstName, 'Reset your FFMS password', $html, $text);
    }

    private static function layout(string $title, string $greeting, string $body, string $link, string $buttonLabel): string
    {
        return <<<HTML
        <div style="font-family:Arial,sans-serif;background:#FFF1E6;padding:32px;">
          <div style="max-width:480px;margin:0 auto;background:#FFFFFF;border:1px solid #E7D8C9;border-radius:8px;overflow:hidden;">
            <div style="background:#997B66;padding:20px 24px;">
              <span style="color:#FFF1E6;font-size:18px;font-weight:700;">FFMS</span>
              <span style="color:#E9C9AE;font-size:18px;">Farm Management</span>
            </div>
            <div style="padding:28px 24px;color:#362A20;">
              <h2 style="margin:0 0 12px;font-size:20px;">{$title}</h2>
              <p style="margin:0 0 8px;">{$greeting}</p>
              <p style="margin:0 0 22px;color:#6E5B48;line-height:1.6;">{$body}</p>
              <a href="{$link}" style="display:inline-block;background:#997B66;color:#FFF1E6;text-decoration:none;padding:12px 22px;border-radius:4px;font-weight:600;">{$buttonLabel}</a>
              <p style="margin:22px 0 0;color:#A08C76;font-size:12px;">If the button doesn't work, copy and paste this link:<br>{$link}</p>
            </div>
          </div>
        </div>
        HTML;
    }
}
