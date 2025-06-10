<?php
/**
 * @copyright 2024-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
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
        $result = $this->dbLayer->select('COUNT(*)')
            ->from('s2_blog_comments')
            ->where('shown = 0 AND sent = 0')
            ->execute()
        ;

        return $result->result();
    }
}
