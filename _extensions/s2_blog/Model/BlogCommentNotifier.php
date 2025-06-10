<?php
/**
 * @copyright 2007-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   s2_blog
 */

declare(strict_types=1);

namespace s2_extensions\s2_blog\Model;

use S2\Cms\Helper\StringHelper;
use S2\Cms\Mail\CommentMailer;
use S2\Cms\Model\UrlBuilder;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
use s2_extensions\s2_blog\BlogUrlBuilder;

/**
 * 1. Sends notifications on new comments:
 *    - Retrieves information about the comment and associated post.
 *    - Sends the comment to commentators who subscribed to this post.
 *    - Generates an unsubscribe link.
 *    - Marks the comment as sent.
 *
 * 2. Unsubscribes commentators by parameters from the unsubscribe links.
 */
readonly class BlogCommentNotifier
{
    public function __construct(
        private DbLayer        $dbLayer,
        private UrlBuilder     $urlBuilder,
        private BlogUrlBuilder $blogUrlBuilder,
        private CommentMailer  $commentMailer,
    ) {
    }

    /**
     * @throws DbLayerException
     */
    public function notify(int $commentId): void
    {
        /**
         * Checking if the comment exists.
         * We need post_id for displaying comments.
         * Also, we need the comment if the pre-moderation is turned on.
         */
        $result  = $this->dbLayer
            ->select('post_id, sent, shown, nick, email, text')
            ->from('s2_blog_comments')
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

        // Getting some info about the post commented
        $result = $this->dbLayer
            ->select('title, create_time, url')
            ->from('s2_blog_posts')
            ->where('id = :id')
            ->setParameter('id', $comment['post_id'])
            ->andWhere('published = 1')
            ->andWhere('commented = 1')
            ->execute()
        ;
        $post   = $result->fetchAssoc();
        if (!$post) {
            return;
        }

        $link = $this->blogUrlBuilder->absPostFromTimestamp($post['create_time'], $post['url']);

        // Fetching receivers' names and addresses
        $allReceivers = $this->getCommentReceivers($comment['post_id'], $comment['email'], '<>');

        // Group by email, taking last records
        $receivers = [];
        foreach ($allReceivers as $receiver) {
            $receivers[$receiver['email']] = $receiver;
        }

        $message = StringHelper::bbcodeToMail($comment['text']);

        foreach ($receivers as $receiver) {
            $unsubscribeLink = $this->urlBuilder->rawAbsLink('/comment_unsubscribe', [
                'mail=' . urlencode($receiver['email']),
                'id=' . $comment['post_id'],
                'code=' . $receiver['hash'],
            ]);

            $this->commentMailer->mailToSubscriber($receiver['nick'], $receiver['email'], $message, $post['title'], $link, $comment['nick'], $unsubscribeLink);
        }

        // Toggle sent mark
        $this->dbLayer
            ->update('s2_blog_comments')
            ->set('sent', '1')
            ->where('id = :id')
            ->setParameter('id', $commentId)
            ->execute()
        ;
    }

    /**
     * @throws DbLayerException
     */
    public function unsubscribe(int $postId, string $email, string $code): bool
    {
        $receivers = $this->getCommentReceivers($postId, $email, '=');

        foreach ($receivers as $receiver) {
            if ($code === $receiver['hash']) {
                $this->dbLayer->update('s2_blog_comments')
                    ->set('subscribed', '0')
                    ->where('post_id = :post_id')
                    ->setParameter('post_id', $postId)
                    ->andWhere('email = :email')
                    ->setParameter('email', $email)
                    ->andWhere('subscribed = 1')
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
    private function getCommentReceivers(int $postId, string $email, string $operation): array
    {
        if (!\in_array($operation, ['=', '<>'], true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid operation "%s".', $operation));
        }

        $result = $this->dbLayer
            ->select('id', 'nick', 'email', 'ip', 'time')
            ->from('s2_blog_comments')
            ->where('post_id = :post_id')
            ->setParameter('post_id', $postId)
            ->andWhere('subscribed = 1')
            ->andWhere('shown = 1')
            ->andWhere('email ' . $operation . ' :email')
            ->setParameter('email', $email)
            ->execute()
        ;

        $receivers = $result->fetchAssocAll();
        foreach ($receivers as &$receiver) {
            $receiver['hash'] = substr(base_convert(md5('s2_blog_comments' . serialize($receiver)), 16, 36), 0, 13);
        }
        unset($receiver);

        return $receivers;
    }
}
