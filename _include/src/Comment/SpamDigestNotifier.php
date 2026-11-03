<?php
/**
 * @copyright 2026 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Comment;

use Psr\Log\LoggerInterface;
use S2\Cms\Mail\CommentMailer;
use S2\Cms\Model\User\UserProvider;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Queue\QueueHandlerInterface;
use S2\Cms\Queue\QueuePublisher;

/**
 * Queue handlers run inside the consumer's transaction. Locking the recipient's
 * state also serializes notifications from different comment strategies.
 */
readonly class SpamDigestNotifier implements QueueHandlerInterface
{
    public const COMMENT_CODE = 'spam_comment';
    public const DIGEST_CODE = 'spam_digest';
    public const INTERVAL = 3600;
    private const MAX_SAMPLES = 5;

    public function __construct(
        private DbLayer         $dbLayer,
        private \PDO            $pdo,
        private QueuePublisher  $publisher,
        private UserProvider    $userProvider,
        private CommentMailer   $mailer,
        private string          $adminUrl,
        private LoggerInterface $logger,
    ) {
    }

    public function enqueue(string $commentKey, string $email, string $text, string $title, string $targetKey): void
    {
        $this->publisher->publish(hash('sha256', $commentKey . "\0" . $email), self::COMMENT_CODE, [
            'email' => $email,
            'sample' => [
                'hash' => hash('sha256', $targetKey . "\0" . $text),
                'title' => self::excerpt($title, 100),
                'quote' => self::excerpt($text, 150),
                'count' => 1,
            ],
        ]);
    }

    public function handle(string $id, string $code, array $payload): bool
    {
        if ($code !== self::COMMENT_CODE && $code !== self::DIGEST_CODE) {
            return false;
        }

        $email = $payload['email'];
        $moderators = $this->userProvider->getModerators([$email]);
        if ($moderators === []) {
            // Do not send queued mail after moderation permission has been revoked.
            $this->dbLayer->delete('spam_notifications')->where('email = :email')->setParameter('email', $email)->execute();
            return true;
        }

        $this->dbLayer->insert('spam_notifications')
            ->setValue('email', ':email')->setParameter('email', $email)
            ->setValue('samples', "'[]'")
            ->onConflictDoNothing('email')
            ->execute()
        ;

        $lock = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
        $state = $this->dbLayer->query(
            'SELECT * FROM ' . $this->dbLayer->getPrefix() . 'spam_notifications WHERE email = :email' . $lock,
            ['email' => $email]
        )->fetchAssoc();
        $samples = json_decode($state['samples'], true, 512, JSON_THROW_ON_ERROR);
        $count = (int)$state['pending_count'];
        $nextSendAt = (int)$state['next_send_at'];

        if ($code === self::COMMENT_CODE) {
            ++$count;
            $sample = $payload['sample'];
            $found = false;
            foreach ($samples as &$existing) {
                if ($existing['hash'] === $sample['hash']) {
                    ++$existing['count'];
                    $found = true;
                    break;
                }
            }
            unset($existing);
            if (!$found && \count($samples) < self::MAX_SAMPLES) {
                $samples[] = $sample;
            }
        }

        $now = time();
        if ($count > 0 && $now >= $nextSendAt) {
            // Schedule the next check before attempting delivery, so failed mail
            // remains pending and is retried without a new comment submission.
            $nextSendAt = $now + self::INTERVAL;
            $this->publisher->publish(bin2hex(random_bytes(16)), self::DIGEST_CODE, ['email' => $email], $nextSendAt);
            $moderator = $moderators[0];
            try {
                $sent = $this->mailer->mailSpamDigest($moderator->login, $email, $count, $samples, $this->adminUrl);
            } catch (\Throwable $e) {
                $sent = false;
                $this->logger->warning('Unable to send spam digest.', ['exception' => $e]);
            }
            if ($sent) {
                $count = 0;
                $samples = [];
            } else {
                $this->logger->warning('Spam digest remains pending for retry.', ['email' => $email]);
            }
        }

        $this->dbLayer->update('spam_notifications')
            ->set('next_send_at', ':next_send_at')->setParameter('next_send_at', $nextSendAt)
            ->set('pending_count', ':count')->setParameter('count', $count)
            ->set('samples', ':samples')->setParameter('samples', json_encode($samples, JSON_THROW_ON_ERROR))
            ->where('email = :email')->setParameter('email', $email)
            ->execute()
        ;

        return true;
    }

    private static function excerpt(string $text, int $limit): string
    {
        // Only inspect a bounded prefix: malformed markup or thousands of domain
        // labels must not exhaust PCRE's stack while preparing a short quote.
        $text = mb_substr(mb_scrub($text, 'UTF-8'), 0, 2048, 'UTF-8');
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = preg_replace('~\[/?[a-z][^\]]*\]~iu', '', $text) ?? '';
        $text = preg_replace('~(?:[a-z][a-z0-9+.-]*://|www\.)[^\s<>\[\]]+~iu', '[…]', $text) ?? '';
        $text = preg_replace('~[^\s<>\[\]]+@[^\s<>\[\]]+~u', '[…]', $text) ?? '';
        $text = preg_replace('~(?:[\p{L}\p{N}-]+\.){1,20}[\p{L}]{2,}(?::\d+)?(?:[/?#][^\s<>\[\]]*)?~u', '[…]', $text) ?? '';
        $text = trim(preg_replace('/[\s\p{C}]+/u', ' ', $text) ?? '');

        return $text === '' ? '…' : (mb_strlen($text) > $limit ? mb_substr($text, 0, $limit - 1) . '…' : $text);
    }
}
