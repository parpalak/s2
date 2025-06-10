<?php
/**
 * Sitemap for blog.
 *
 * @copyright 2022-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   s2_blog
 */

declare(strict_types=1);

namespace s2_extensions\s2_blog\Controller;

use S2\Cms\Model\UrlBuilder;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Template\Viewer;
use s2_extensions\s2_blog\BlogUrlBuilder;

class Sitemap extends \S2\Cms\Controller\Sitemap
{
    public function __construct(
        protected DbLayer        $dbLayer,
        protected BlogUrlBuilder $blogUrlBuilder,
        protected UrlBuilder     $urlBuilder,
        protected Viewer         $viewer,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    protected function getItems(): array
    {
        // Obtaining posts
        $result = $this->dbLayer
            ->select('p.create_time AS time, p.modify_time, p.url')
            ->from('s2_blog_posts AS p')
            ->where('p.published = 1')
            ->execute()
        ;

        $posts = [];
        while ($row = $result->fetchAssoc()) {
            $row['rel_path'] = $this->blogUrlBuilder->postFromTimestamp($row['time'], $row['url']);
            $posts[]         = $row;
        }

        return $posts;
    }
}
