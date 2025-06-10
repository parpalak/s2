<?php
/**
 * @copyright 2024-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace s2_extensions\s2_blog\Model;

use S2\Cms\Controller\Comment\CommentDto;
use S2\Cms\Controller\Comment\CommentStrategyInterface;
use S2\Cms\Controller\Comment\TargetDto;
use S2\Cms\Controller\CommentController;
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

        $result = $this->dbLayer
            ->select('id', 'title')
            ->from('s2_blog_posts AS p')
            ->where('create_time < :end_time')
            ->setParameter('end_time', $startTime + 86400)
            ->andWhere('create_time >= :start_time')
            ->setParameter('start_time', $startTime)
            ->andWhere('url = :url')
            ->setParameter('url', $url)
            ->andWhere('published = 1')
            ->andWhere('commented = 1')
            ->execute()
        ;

        $post = $result->fetchAssoc();

        return \is_array($post) ? new TargetDto($post['id'], $post['title']) : null;
    }

    /**
     * {@inheritdoc}
     * @throws DbLayerException
     */
    public function getTargetById(int $targetId): ?TargetDto
    {
        $post = $this->dbLayer
            ->select('id', 'title')
            ->from('s2_blog_posts AS p')
            ->where('id = :id')
            ->setParameter('id', $targetId)
            ->execute()
            ->fetchAssoc()
        ;

        return \is_array($post) ? new TargetDto($post['id'], $post['title']) : null;
    }

    /**
     * {@inheritdoc}
     * @throws DbLayerException
     */
    public function save(int $targetId, string $name, string $email, bool $showEmail, bool $subscribed, string $text, string $ip): int
    {
        $this->dbLayer
            ->insert('s2_blog_comments')
            ->setValue('post_id', ':post_id')->setParameter('post_id', $targetId)
            ->setValue('time', ':time')->setParameter('time', time())
            ->setValue('ip', ':ip')->setParameter('ip', $ip)
            ->setValue('nick', ':nick')->setParameter('nick', $name)
            ->setValue('email', ':email')->setParameter('email', $email)
            ->setValue('show_email', ':show_email')->setParameter('show_email', $showEmail ? 1 : 0)
            ->setValue('subscribed', ':subscribed')->setParameter('subscribed', $subscribed ? 1 : 0)
            ->setValue('sent', '0')
            ->setValue('shown', '0')
            ->setValue('good', '0')
            ->setValue('text', ':text')->setParameter('text', $text)
            ->execute()
        ;

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
        $result = $this->dbLayer->select('COUNT(id)')
            ->from('s2_blog_comments')
            ->where('post_id = :post_id')
            ->setParameter('post_id', $targetId)
            ->andWhere('shown = 1')
            ->execute()
        ;

        $num = $result->result();

        return $num ? (string)$num : null;
    }

    /**
     * {@inheritdoc}
     * @throws DbLayerException
     */
    public function getRecentComment(string $hash, string $ip): ?CommentDto
    {
        $result = $this->dbLayer->select('id, post_id AS target_id, email, text, nick AS name')
            ->from('s2_blog_comments')
            ->where('ip = :ip')
            ->setParameter('ip', $ip)
            ->andWhere('shown = 0')
            ->andWhere('sent = 0')
            ->andWhere('time >= :time')
            ->setParameter('time', time() - 5 * 60) // 5 minutes
            ->orderBy('time DESC')
            ->execute()
        ;

        foreach ($result->fetchAssocAll() as $comment) {
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
        $this->dbLayer
            ->update('s2_blog_comments')
            ->set('shown', '1')
            ->where('id = :id')
            ->setParameter('id', $commentId)
            ->execute()
        ;
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
