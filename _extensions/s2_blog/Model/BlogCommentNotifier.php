<?php
/**
 * @copyright 2007-2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   s2_blog
 */

declare(strict_types=1);

namespace s2_extensions\s2_blog\Model;

use S2\Cms\Pdo\DbLayer;
use s2_extensions\s2_blog\BlogUrlBuilder;

readonly class BlogCommentNotifier
{
    public function __construct(
        private DbLayer        $dbLayer,
        private BlogUrlBuilder $blogUrlBuilder,
        private string         $baseUrl,
    ) {
    }

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
        $query  = [
            'SELECT' => 'title, create_time, url',
            'FROM'   => 's2_blog_posts',
            'WHERE'  => 'id = ' . $comment['post_id'] . ' AND published = 1 AND commented = 1'
        ];
        $result = $this->dbLayer->buildAndQuery($query);

        $post = $this->dbLayer->fetchAssoc($result);
        if (!$post) {
            return;
        }

        $link = $this->blogUrlBuilder->postFromTimestamp($post['create_time'], $post['url']);

        // Fetching receivers' names and addresses
        $query  = [
            'SELECT' => 'id, nick, email, ip, time',
            'FROM'   => 's2_blog_comments',
            'WHERE'  => 'post_id = ' . $comment['post_id'] . ' AND subscribed = 1 AND shown = 1 AND email <> \'' . $this->dbLayer->escape($comment['email']) . '\''
        ];
        $result = $this->dbLayer->buildAndQuery($query);

        $receivers = [];
        while ($receiver = $this->dbLayer->fetchAssoc($result)) {
            $receivers[$receiver['email']] = $receiver;
        }

        foreach ($receivers as $receiver) {
            $hash = md5($receiver['id'] . $receiver['ip'] . $receiver['nick'] . $receiver['email'] . $receiver['time']);

            $unsubscribeLink = $this->baseUrl
                . '/comment.php?mail=' . urlencode($receiver['email'])
                . '&id=' . $comment['post_id'] . '.s2_blog'
                . '&unsubscribe=' . base_convert(substr($hash, 0, 16), 16, 36);
            s2_mail_comment($receiver['nick'], $receiver['email'], $comment['text'], $post['title'], $link, $comment['nick'], $unsubscribeLink);
        }

        // Toggle sent mark
        $query = [
            'UPDATE' => 's2_blog_comments',
            'SET'    => 'sent = 1',
            'WHERE'  => 'id = ' . $commentId
        ];
        $this->dbLayer->buildAndQuery($query);
    }
}
