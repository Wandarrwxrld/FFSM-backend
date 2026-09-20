<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

require_once __DIR__ . '/../vendor/autoload.php';

class Mailer
{
    private static function client(): PHPMailer
    {
        require_once __DIR__ . '/../config/config.php';
        $config = ffms_config();
        $mail = $config['mail'];

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

        return $phpMailer;
    }

    private static function send(string $toEmail, string $toName, string $subject, string $bodyHtml, string $bodyText): bool
    {
        require_once __DIR__ . '/../config/config.php';
        $config = ffms_config();
        if (empty($config['mail']['host'])) {
            // No SMTP configured (e.g. fresh local install) — don't hard-fail
            // the request, just log it so the flow is still testable.
            error_log("[Mailer] SMTP not configured — would have sent '{$subject}' to {$toEmail}");
            return false;
        }

        try {
            $mail = self::client();
            $mail->addAddress($toEmail, $toName);
            $mail->Subject = $subject;
            $mail->Body = $bodyHtml;
            $mail->AltBody = $bodyText;
            $mail->send();
            return true;
        } catch (PHPMailerException $e) {
            error_log('[Mailer] send failed: ' . $e->getMessage());
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
