<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace s2_extensions\s2_blog\Model;

use S2\Cms\Controller\CommentController;
use S2\Cms\Model\Comment\CommentDto;
use S2\Cms\Model\Comment\CommentStrategyInterface;
use S2\Cms\Model\Comment\TargetDto;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
use Symfony\Component\HttpFoundation\Request;

readonly class BlogCommentStrategy implements CommentStrategyInterface
{
    public function __construct(
        private DbLayer             $dbLayer,
        private BlogCommentNotifier $commentNotifier,
    ) {
    }

    /**
     * {@inheritdoc}
     * @throws DbLayerException
     */
    public function getTargetByRequest(Request $request): ?TargetDto
    {
        $year  = (int)($request->attributes->get('year'));
        $month = (int)($request->attributes->get('month')); // Note: "01" is not parsed with getInt() correctly
        $day   = (int)($request->attributes->get('day'));
        $url   = $request->attributes->get('url');

        $startTime = mktime(0, 0, 0, $month, $day, $year);
        $result    = $this->dbLayer->buildAndQuery([
            'SELECT' => 'id, title',
            'FROM'   => 's2_blog_posts AS p',
            'WHERE'  => 'create_time < :end_time AND create_time >= :start_time AND url = :url AND published = 1 AND commented = 1',
        ], [
            'end_time'   => $startTime + 86400,
            'start_time' => $startTime,
            'url'        => $url,
        ]);

        $post = $this->dbLayer->fetchAssoc($result);
        if (\is_array($post)) {
            return new TargetDto($post['id'], $post['title']);
        }
        return null;
    }

    /**
     * {@inheritdoc}
     * @throws DbLayerException
     */
    public function getTargetById(int $targetId): ?TargetDto
    {
        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => 'id, title',
            'FROM'   => 's2_blog_posts AS p',
            'WHERE'  => 'id = :id',
        ], [
            'id' => $targetId,
        ]);

        $post = $this->dbLayer->fetchAssoc($result);
        if (\is_array($post)) {
            return new TargetDto($post['id'], $post['title']);
        }
        return null;
    }

    /**
     * {@inheritdoc}
     * @throws DbLayerException
     */
    public function save(int $targetId, string $name, string $email, bool $showEmail, bool $subscribed, string $text, string $ip): int
    {
        $this->dbLayer->buildAndQuery([
            'INSERT' => 'post_id, time, ip, nick, email, show_email, subscribed, sent, shown, good, text',
            'INTO'   => 's2_blog_comments',
            'VALUES' => ':post_id, :time, :ip, :nick, :email, :show_email, :subscribed, :sent, :shown, 0, :text'
        ], [
            'post_id'    => $targetId,
            'time'       => time(),
            'ip'         => $ip,
            'nick'       => $name,
            'email'      => $email,
            'show_email' => $showEmail ? 1 : 0,
            'subscribed' => $subscribed ? 1 : 0,
            'sent'       => 0,
            'shown'      => 0,
            'text'       => $text,
        ]);

        return (int)$this->dbLayer->insertId();
    }

    /**
     * {@inheritdoc}
     * @throws DbLayerException
     */
    public function notifySubscribers(int $commentId): void
    {
        $this->commentNotifier->notify($commentId);
    }

    /**
     * {@inheritdoc}
     * @throws DbLayerException
     */
    public function getHashForPublishedComment(int $targetId): ?string
    {
        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => 'COUNT(id)',
            'FROM'   => 's2_blog_comments',
            'WHERE'  => 'post_id = ' . $targetId . ' AND shown = 1'
        ]);

        $num = $this->dbLayer->result($result);

        return $num ? (string)$num : null;
    }

    /**
     * {@inheritdoc}
     * @throws DbLayerException
     */
    public function getRecentComment(string $hash, string $ip): ?CommentDto
    {
        $result = $this->dbLayer->buildAndQuery([
            'SELECT'   => 'id, post_id AS target_id, email, text, nick AS name',
            'FROM'     => 's2_blog_comments',
            'WHERE'    => 'ip = :ip AND shown = 0 AND sent = 0 AND time >= :time',
            'ORDER BY' => 'time DESC',
        ], [
            'ip'   => $ip,
            'time' => time() - 5 * 60, // 5 minutes
        ]);

        foreach ($this->dbLayer->fetchAssocAll($result) as $comment) {
            if ($hash === CommentController::commentHash($comment['id'], $comment['target_id'], $comment['email'], $ip, \get_class($this))) {
                return new CommentDto($comment['id'], $comment['target_id'], $comment['name'], $comment['email'], $comment['text']);
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     * @throws DbLayerException
     */
    public function publishComment(int $commentId): void
    {
        $this->dbLayer->buildAndQuery([
            'UPDATE' => 's2_blog_comments',
            'SET'    => 'shown = 1',
            'WHERE'  => 'id = :id',
        ], ['id' => $commentId]);
    }

    /**
     * {@inheritdoc}
     * @throws DbLayerException
     */
    public function unsubscribe(int $targetId, string $email, string $code): bool
    {
        return $this->commentNotifier->unsubscribe($targetId, $email, $code);
    }
}
