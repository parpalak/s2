<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   s2_blog
 */

declare(strict_types=1);

namespace s2_extensions\s2_blog\Model;

use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;

readonly class BlogCommentProvider
{
    public function __construct(private DbLayer $dbLayer)
    {
    }

    /**
     * @throws DbLayerException
     */
    public function getPendingCommentsCount(): int
    {
        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => 'COUNT(*)',
            'FROM'   => 's2_blog_comments',
            'WHERE'  => 'shown = 0 AND sent = 0',
        ]);

        return $this->dbLayer->result($result);
    }
}
