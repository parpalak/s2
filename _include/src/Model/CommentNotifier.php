<?php
/**
 * @copyright 2007-2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Model;

use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;

/**
 * Retrieves information about the comment and associated article and sends the comment to subscribed commentators.
 * It also generates an unsubscribe link and marks the comment as sent.
 */
readonly class CommentNotifier
{
    public function __construct(
        private DbLayer         $dbLayer,
        private ArticleProvider $articleProvider,
        private UrlBuilder      $urlBuilder,
        private string          $baseUrl,
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
        $query  = [
            'SELECT' => 'article_id, sent, shown, nick, email, text',
            'FROM'   => 'art_comments',
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

        // Getting some info about the article commented
        $query  = [
            'SELECT' => 'title, parent_id, url',
            'FROM'   => 'articles',
            'WHERE'  => 'id = ' . $comment['article_id'] . ' AND published = 1 AND commented = 1'
        ];
        $result = $this->dbLayer->buildAndQuery($query);

        $article = $this->dbLayer->fetchAssoc($result);
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
        $query  = [
            'SELECT' => 'id, nick, email, ip, time',
            'FROM'   => 'art_comments',
            'WHERE'  => 'article_id = ' . $comment['article_id'] . ' AND subscribed = 1 AND shown = 1 AND email <> \'' . $this->dbLayer->escape($comment['email']) . '\''
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
                . '&id=' . $comment['article_id']
                . '.&unsubscribe=' . base_convert(substr($hash, 0, 16), 16, 36);
            s2_mail_comment($receiver['nick'], $receiver['email'], $comment['text'], $article['title'], $link, $comment['nick'], $unsubscribeLink);
        }

        // Toggle sent mark
        $query = [
            'UPDATE' => 'art_comments',
            'SET'    => 'sent = 1',
            'WHERE'  => 'id = ' . $commentId
        ];
        $this->dbLayer->buildAndQuery($query);
    }
}
