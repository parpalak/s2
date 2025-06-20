<?php
/**
 * @copyright 2007-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Model;

use S2\Cms\Helper\StringHelper;
use S2\Cms\Mail\CommentMailer;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;

/**
 * 1. Sends notifications on new comments:
 *    - Retrieves information about the comment and associated article.
 *    - Sends the comment to commentators who subscribed to this article.
 *    - Generates an unsubscribe link.
 *    - Marks the comment as sent.
 *
 * 2. Unsubscribes commentators by parameters from the unsubscribe links.
 */
readonly class CommentNotifier
{
    public function __construct(
        private DbLayer         $dbLayer,
        private ArticleProvider $articleProvider,
        private UrlBuilder      $urlBuilder,
        private CommentMailer   $commentMailer,
    ) {
    }

    /**
     * @throws DbLayerException
     */
    public function notify(int $commentId): void
    {
        /**
         * Checking if the comment exists.
         * We need article_id for displaying comments.
         * Also, we need the comment if the pre-moderation is turned on.
         */
        $result  = $this->dbLayer
            ->select('article_id, sent, shown, nick, email, text')
            ->from('art_comments')
            ->where('id = :id')
            ->setParameter('id', $commentId)
            ->execute()
        ;
        $comment = $result->fetchAssoc();
        if (!$comment) {
            return;
        }

        if ($comment['shown'] || $comment['sent']) {
            // Comment has already been checked by the moderator
            return;
        }

        /**
         * $comment['sent'] === 0 as pre-moderation was enabled when the comment was added.
         * We have to send the comment to subscribed commentators.
         */

        // Getting some info about the article commented
        $result = $this->dbLayer
            ->select('title, parent_id, url')
            ->from('articles')
            ->where('id = :article_id')
            ->setParameter('article_id', $comment['article_id'])
            ->andWhere('published = 1')
            ->andWhere('commented = 1')
            ->execute()
        ;
        $article = $result->fetchAssoc();
        if (!$article) {
            return;
        }

        $path = $this->articleProvider->pathFromId($article['parent_id'], true);
        if ($path === null) {
            // Article is hidden via parent sections.
            return;
        }

        $link = $this->urlBuilder->absLink($path . '/' . rawurlencode($article['url']));

        // Fetching receivers' names and addresses
        $allReceivers = $this->getCommentReceivers($comment['article_id'], $comment['email'], '<>');

        // Group by email, taking last records
        $receivers = [];
         foreach ($allReceivers as $receiver) {
            $receivers[$receiver['email']] = $receiver;
        }

        $message = StringHelper::bbcodeToMail($comment['text']);

        foreach ($receivers as $receiver) {
            $unsubscribeLink = $this->urlBuilder->rawAbsLink('/comment_unsubscribe', [
                'mail=' . urlencode($receiver['email']),
                'id=' . $comment['article_id'],
                'code=' . $receiver['hash'],
            ]);

            $this->commentMailer->mailToSubscriber($receiver['nick'], $receiver['email'], $message, $article['title'], $link, $comment['nick'], $unsubscribeLink);
        }

        // Toggle sent mark
        $this->dbLayer->update('art_comments')
            ->set('sent', '1')
            ->where('id = :id')
            ->setParameter('id', $commentId)
            ->execute()
        ;
    }

    /**
     * @throws DbLayerException
     */
    public function unsubscribe(int $articleId, string $email, string $code): bool
    {
        $receivers = $this->getCommentReceivers($articleId, $email, '=');

        foreach ($receivers as $receiver) {
            if ($code === $receiver['hash']) {
                $this->dbLayer
                    ->update('art_comments')
                    ->set('subscribed', '0')
                    ->where('article_id = :article_id')
                    ->setParameter('article_id', $articleId)
                    ->andWhere('subscribed = 1')
                    ->andWhere('email = :email')
                    ->setParameter('email', $email)
                    ->execute()
                ;

                return true;
            }
        }

        return false;
    }

    /**
     * @throws DbLayerException
     */
    private function getCommentReceivers(int $articleId, string $email, string $operation): array
    {
        if (!\in_array($operation, ['=', '<>'], true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid operation "%s".', $operation));
        }

        $result = $this->dbLayer
            ->select('id, nick, email, ip, time')
            ->from('art_comments')
            ->where('article_id = :article_id')
            ->setParameter('article_id', $articleId)
            ->andWhere('subscribed = 1')
            ->andWhere('shown = 1')
            ->andWhere('email ' . $operation . ' :email')
            ->setParameter('email', $email)
            ->execute()
        ;

        $receivers = $result->fetchAssocAll();
        foreach ($receivers as &$receiver) {
            $receiver['hash'] = substr(base_convert(md5('art_comments' . serialize($receiver)), 16, 36), 0, 13);
        }
        unset($receiver);

        return $receivers;
    }
}
