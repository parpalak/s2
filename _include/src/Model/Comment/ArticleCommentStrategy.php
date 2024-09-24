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

        if ($article !== null && $article['commented'] === 0) {
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
        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => 'id, title',
            'FROM'   => 'articles',
            'WHERE'  => 'id = :id',
        ], ['id' => $targetId]);

        $article = $this->dbLayer->fetchAssoc($result);

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
        $this->dbLayer->buildAndQuery([
            'INSERT' => 'article_id, time, ip, nick, email, show_email, subscribed, sent, shown, good, text',
            'INTO'   => 'art_comments',
            'VALUES' => ':article_id, :time, :ip, :nick, :email, :show_email, :subscribed, :sent, :shown, 0, :text'
        ], [
            'article_id' => $targetId,
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
        $num = $this->articleProvider->getCommentNum($targetId, false);

        return $num > 0 ? (string)$num : null;
    }

    /**
     * {@inheritdoc}
     * @throws DbLayerException
     */
    public function getRecentComment(string $hash, string $ip): ?CommentDto
    {
        $result = $this->dbLayer->buildAndQuery([
            'SELECT'   => 'id, article_id AS target_id, email, text, nick AS name',
            'FROM'     => 'art_comments',
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
            'UPDATE' => 'art_comments',
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
