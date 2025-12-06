<?php
/**
 * @copyright 2009-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Mail;

use S2\Cms\Config\StringProxy;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class CommentMailer
{
    public function __construct(
        private TranslatorInterface $translator,
        private StringProxy         $webmasterName,
        private StringProxy         $webmasterEmail,
    ) {
    }

    public function mailToSubscriber(
        string $subscriberName,
        string $subscriberEmail,
        string $text,
        string $title,
        string $url,
        string $authorName,
        string $unsubscribeLink
    ): bool {
        $messageTemplate = $this->translator->trans('Email pattern');
        $message         = str_replace(
            ['<name>', '<author>', '<title>', '<url>', '<text>', '<unsubscribe>'],
            [$subscriberName, $authorName, $title, $url, $text, $unsubscribeLink],
            $messageTemplate
        );

        // Make sure all linebreaks are CRLF in message (and strip out any NULL bytes)
        $message = str_replace(["\n", "\0"], ["\r\n", ''], $message);

        $subject = \sprintf($this->translator->trans('Email subject'), $url);
        $subject = "=?UTF-8?B?" . base64_encode($subject) . "?=";

        // Our email
        $from = $this->getWebmasterNameAndEmail();

        $headers = [
            'From'                      => $from,
            'Date'                      => gmdate('r'),
            'MIME-Version'              => '1.0',
            'Content-transfer-encoding' => '8bit',
            'Content-type'              => 'text/plain; charset=utf-8',
            'X-Mailer'                  => 'S2 Mailer',
            'List-Unsubscribe'          => '<' . $unsubscribeLink . '>',
            'Reply-To'                  => $from
        ];

        return $this->sendMail($subscriberEmail, $subject, $message, $headers);
    }

    public function mailToModerator(
        string $moderatorName,
        string $moderatorEmail,
        string $text,
        string $title,
        string $url,
        string $authorName,
        string $authorEmail,
        bool $isPublished,
        string $spamReportStatus,
    ): bool {
        $messageTemplate = $this->translator->trans('Email moderator pattern');
        $message         = str_replace(
            ['<name>', '<author>', '<title>', '<url>', '<text>', '<status>'],
            [$moderatorName, $authorName, $title, $url, $text, \sprintf(
                $this->translator->trans($isPublished ? 'Comment check passed' : 'Comment check failed'), $spamReportStatus
            )],
            $messageTemplate
        );

        // Make sure all linebreaks are CRLF in message (and strip out any NULL bytes)
        $message = str_replace(["\n", "\0"], ["\r\n", ''], $message);

        $subject = \sprintf($this->translator->trans('Email subject'), $url);
        $subject = "=?UTF-8?B?" . base64_encode($subject) . "?=";

        // Our email
        $webmaster = $this->getWebmasterNameAndEmail();

        // Author email
        $author = "=?UTF-8?B?" . base64_encode($authorName) . "?=" . ' <' . $authorEmail . '>';

        $headers = [
            'From'                      => $webmaster, // One cannot use the real author email in "From:" header due to DMARC. Use our one.
            'Sender'                    => $author, // Let's use the real author email at least here.
            'Date'                      => gmdate('r'),
            'MIME-Version'              => '1.0',
            'Content-transfer-encoding' => '8bit',
            'Content-type'              => 'text/plain; charset=utf-8',
            'X-Mailer'                  => 'S2 Mailer',
            'Reply-To'                  => $author
        ];

        return $this->sendMail($moderatorEmail, $subject, $message, $headers);
    }

    private function sendMail(string $email, string $subject, string $message, array $headers): bool
    {
        $headersFormatted = $this->formatHeaders($headers);

        if (!\defined('PHP_VERSION_ID') || PHP_VERSION_ID < 80000) {
            // Old hack for PHP < 8.0
            // Change the linebreaks used in the headers according to OS
            $os = strtoupper(substr(PHP_OS, 0, 3));
            if ($os === 'MAC') {
                $headersFormatted = str_replace("\r\n", "\r", $headersFormatted);
            } elseif ($os !== 'WIN') {
                $headersFormatted = str_replace("\r\n", "\n", $headersFormatted);
            }
        }

        return mail($email, $subject, $message, $headersFormatted);
    }

    private function formatHeaders(array $headers): string
    {
        $formatted = '';
        foreach ($headers as $key => $value) {
            $formatted .= $key . ': ' . $value . "\r\n";
        }
        return $formatted;
    }

    private function getWebmasterNameAndEmail(): string
    {
        $email = $this->webmasterEmail->get() ?: 'example@example.com';
        $name  = $this->webmasterName->get();

        if ($name) {
            return "=?UTF-8?B?" . base64_encode($name) . "?=" . ' <' . $email . '>';
        }

        return $email;
    }
}
