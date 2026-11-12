<?php

declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

/**
 * Email notification service backed by PHPMailer.
 * Strips CR/LF header injection sequences and fails gracefully when SMTP is unavailable.
 */
final class Mailer
{
    /**
     * Send applicant registration confirmation email.
     */
    public static function sendRegistrationConfirmation(string $toEmail, string $toName, string $regNo): bool
    {
        $council = self::sanitizeHeader((string) appConfig('council_name'));
        $subject = self::sanitizeHeader("Registration Received — {$regNo} | {$council}");
        $safeName = htmlspecialchars(self::sanitizeHeader($toName), ENT_QUOTES, 'UTF-8');
        $safeReg  = htmlspecialchars($regNo, ENT_QUOTES, 'UTF-8');
        $safeCouncil = htmlspecialchars($council, ENT_QUOTES, 'UTF-8');

        $html = "<p>Dear {$safeName},</p>"
            . "<p>Thank you for submitting your membership registration with <strong>{$safeCouncil}</strong>.</p>"
            . "<p>Your application reference number is: <strong style=\"font-family:monospace;font-size:1.1em;\">{$safeReg}</strong></p>"
            . "<p>Your application is currently <strong>Pending Review</strong>. You will be notified by email once a decision is made.</p>"
            . "<p>Regards,<br>{$safeCouncil}</p>";

        $text = "Dear {$toName},\n\n"
            . "Thank you for submitting your membership registration with {$council}.\n"
            . "Your application reference number is: {$regNo}\n"
            . "Status: Pending Review.\n\n"
            . "Regards,\n{$council}\n";

        return self::send($toEmail, $toName, $subject, $html, $text, $regNo);
    }

    /**
     * Send admin decision notification email (approved / rejected).
     */
    public static function sendDecisionNotification(
        string $toEmail,
        string $toName,
        string $regNo,
        string $status,
        ?string $reason = null
    ): bool {
        $council = self::sanitizeHeader((string) appConfig('council_name'));
        $label = $status === 'approved' ? 'Approved' : 'Rejected';
        $subject = self::sanitizeHeader("Registration {$label} — {$regNo} | {$council}");

        $safeName    = htmlspecialchars(self::sanitizeHeader($toName), ENT_QUOTES, 'UTF-8');
        $safeReg     = htmlspecialchars($regNo, ENT_QUOTES, 'UTF-8');
        $safeCouncil = htmlspecialchars($council, ENT_QUOTES, 'UTF-8');
        $safeReason  = $reason !== null && $reason !== ''
            ? htmlspecialchars($reason, ENT_QUOTES, 'UTF-8')
            : '';

        $reasonHtml = $safeReason !== ''
            ? "<p><strong>Remarks / Reason:</strong> {$safeReason}</p>"
            : '';
        $reasonText = $reason !== null && $reason !== ''
            ? "Remarks / Reason: {$reason}\n\n"
            : '';

        $html = "<p>Dear {$safeName},</p>"
            . "<p>Your council membership application (<strong>{$safeReg}</strong>) has been <strong>{$label}</strong>.</p>"
            . $reasonHtml
            . "<p>Regards,<br>{$safeCouncil}</p>";

        $text = "Dear {$toName},\n\n"
            . "Your council membership application ({$regNo}) has been {$label}.\n\n"
            . $reasonText
            . "Regards,\n{$council}\n";

        return self::send($toEmail, $toName, $subject, $html, $text, $regNo);
    }

    /**
     * Core PHPMailer dispatch with safe header sanitization and fallback logging.
     */
    private static function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody,
        string $ref
    ): bool {
        $toEmail = self::sanitizeHeader($toEmail);
        $toName  = self::sanitizeHeader($toName);
        $subject = self::sanitizeHeader($subject);

        $enabled = filter_var($_ENV['MAIL_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
        if (!$enabled) {
            self::logMail("SIMULATED ref={$ref} subject=\"{$subject}\" (MAIL_ENABLED=false)");
            return true;
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = (string) ($_ENV['MAIL_HOST'] ?? 'localhost');
            $mail->Port       = (int) ($_ENV['MAIL_PORT'] ?? 587);
            $mail->SMTPAuth   = ((string) ($_ENV['MAIL_USERNAME'] ?? '')) !== '';
            $mail->Username   = (string) ($_ENV['MAIL_USERNAME'] ?? '');
            $mail->Password   = (string) ($_ENV['MAIL_PASSWORD'] ?? '');
            $enc              = strtolower((string) ($_ENV['MAIL_ENCRYPTION'] ?? 'tls'));
            $mail->SMTPSecure = $enc === 'ssl'
                ? PHPMailer::ENCRYPTION_SMTPS
                : ($enc === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : '');
            $mail->Timeout    = 8;
            $mail->CharSet    = PHPMailer::CHARSET_UTF8;

            $fromAddress = self::sanitizeHeader((string) ($_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@councilportal.local'));
            $fromName    = self::sanitizeHeader((string) ($_ENV['MAIL_FROM_NAME'] ?? 'Council Portal'));

            $mail->setFrom($fromAddress, $fromName);
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $textBody;

            $mail->send();
            self::logMail("SENT ref={$ref} subject=\"{$subject}\"");
            return true;
        } catch (MailException $e) {
            appLog('WARN', "Mail failed for ref={$ref}: " . $e->getMessage());
            self::logMail("FAILED ref={$ref} subject=\"{$subject}\" error=\"" . $e->getMessage() . '"');
            return false;
        }
    }

    /**
     * Prevent email header injection by stripping CR, LF, and null bytes (SUC-05).
     */
    public static function sanitizeHeader(string $value): string
    {
        return trim(str_replace(["\r", "\n", "\0"], '', $value));
    }

    private static function logMail(string $entry): void
    {
        $line = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $entry);
        error_log($line, 3, LOG_PATH . '/mail.log');
    }
}

