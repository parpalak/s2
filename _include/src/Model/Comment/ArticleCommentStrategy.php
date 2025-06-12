<?php
/**
 * @copyright 2007-2024 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Model\Comment;

use S2\Cms\Controller\Comment\CommentDto;
use S2\Cms\Controller\Comment\CommentStrategyInterface;
use S2\Cms\Controller\Comment\TargetDto;
use S2\Cms\Controller\CommentController;
use S2\Cms\Model\ArticleProvider;
use S2\Cms\Model\CommentNotifier;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
use Symfony\Component\HttpFoundation\Request;

readonly class ArticleCommentStrategy implements CommentStrategyInterface
{
    public function __construct(
        private DbLayer         $dbLayer,
        private ArticleProvider $articleProvider,
        private CommentNotifier $commentNotifier,
    ) {
    }

    /**
     * {@inheritdoc}
     * @throws DbLayerException
     */
    public function getTargetByRequest(Request $request): ?TargetDto
    {
        $path = $request->getPathInfo();

        $article = $this->articleProvider->articleFromPath($path, true);

        if ($article === null || $article['commented'] === 0) {
            return null;
        }
        return new TargetDto($article['id'], $article['title']);
    }

    /**
     * {@inheritdoc}
     * @throws DbLayerException
     */
    public function getTargetById(int $targetId): ?TargetDto
    {
        $result = $this->dbLayer
            ->select('id', 'title')
            ->from('articles')
            ->where('id = :id')->setParameter('id', $targetId)
            ->execute()
        ;

        $article = $result->fetchAssoc();

        if (!\is_array($article)) {
            return null;
        }
        return new TargetDto($article['id'], $article['title']);
    }

    /**
     * {@inheritdoc}
     * @throws DbLayerException
     */
    public function save(int $targetId, string $name, string $email, bool $showEmail, bool $subscribed, string $text, string $ip): int
    {
        $this->dbLayer
            ->insert('art_comments')
            ->setValue('article_id', ':article_id')->setParameter('article_id', $targetId)
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
        $num = $this->articleProvider->getCommentNum($targetId, false);

        return $num > 0 ? (string)$num : null;
    }

    /**
     * {@inheritdoc}
     * @throws DbLayerException
     */
    public function getRecentComment(string $hash, string $ip): ?CommentDto
    {
        $result = $this->dbLayer
            ->select('id', 'article_id AS target_id', 'email', 'text', 'nick AS name')
            ->from('art_comments')
            ->where('ip = :ip')
            ->setParameter('ip', $ip)
            ->andWhere('shown = 0')
            ->andWhere('sent = 0')
            ->andWhere('time >= :time')
            ->setParameter('time', time() - 5 * 60) // 5 minutes
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
        $this->dbLayer->update('art_comments')
            ->set('shown', '1')
            ->where('id = :id')->setParameter('id', $commentId)
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
