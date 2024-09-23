<?php
/**
 * @copyright 2007-2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   s2_blog
 */

declare(strict_types=1);

namespace s2_extensions\s2_blog\Model;

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
         * Also we need the comment if the pre-moderation is turned on.
         */
        $query  = [
            'SELECT' => 'post_id, sent, shown, nick, email, text',
            'FROM'   => 's2_blog_comments',
            'WHERE'  => 'id = ' . $commentId
        ];
        $result = $this->dbLayer->buildAndQuery($query);

        $comment = $this->dbLayer->fetchAssoc($result);
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

        if (!defined('S2_COMMENTS_FUNCTIONS_LOADED')) {
            require S2_ROOT . '_include/comments.php';
        }

        // Getting some info about the post commented
        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => 'title, create_time, url',
            'FROM'   => 's2_blog_posts',
            'WHERE'  => 'id = :post_id AND published = 1 AND commented = 1'
        ], [
            'post_id' => $comment['post_id']
        ]);

        $post = $this->dbLayer->fetchAssoc($result);
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

        $message = s2_bbcode_to_mail($comment['text']);

        foreach ($receivers as $receiver) {
            $unsubscribeLink = $this->urlBuilder->rawAbsLink('/comment_unsubscribe', [
                'mail=' . urlencode($receiver['email']),
                'id=' . $comment['post_id'],
                'code=' . $receiver['hash'],
            ]);

            s2_mail_comment($receiver['nick'], $receiver['email'], $message, $post['title'], $link, $comment['nick'], $unsubscribeLink);
        }

        // Toggle sent mark
        $query = [
            'UPDATE' => 's2_blog_comments',
            'SET'    => 'sent = 1',
            'WHERE'  => 'id = ' . $commentId
        ];
        $this->dbLayer->buildAndQuery($query);
    }

    /**
     * @throws DbLayerException
     */
    public function unsubscribe(int $postId, string $email, string $code): bool
    {
        $receivers = $this->getCommentReceivers($postId, $email, '=');

        foreach ($receivers as $receiver) {
            if ($code === $receiver['hash']) {
                $this->dbLayer->buildAndQuery([
                    'UPDATE' => 's2_blog_comments',
                    'SET'    => 'subscribed = 0',
                    'WHERE'  => 'post_id = :post_id and subscribed = 1 and email = :email'
                ], [
                    'post_id' => $postId,
                    'email'   => $email,
                ]);

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
            throw new \InvalidArgumentException(sprintf('Invalid operation "%s".', $operation));
        }

        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => 'id, nick, email, ip, time',
            'FROM'   => 's2_blog_comments',
            'WHERE'  => 'post_id = :post_id AND subscribed = 1 AND shown = 1 AND email ' . $operation . ' :email'
        ], [
            'post_id' => $postId,
            'email'   => $email,
        ]);

        $receivers = $this->dbLayer->fetchAssocAll($result);
        foreach ($receivers as &$receiver) {
            $receiver['hash'] = substr(base_convert(md5('s2_blog_comments' . serialize($receiver)), 16, 36), 0, 13);
        }
        unset($receiver);

        return $receivers;
    }
}
