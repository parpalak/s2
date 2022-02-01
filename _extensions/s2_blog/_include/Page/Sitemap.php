<?php
/**
 * Sitemap for blog.
 *
 * @copyright (C) 2022 Roman Parpalak
 * @license       http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package       s2_blog
 */

namespace s2_extensions\s2_blog;


class Page_Sitemap extends \Page_Sitemap
{
    /**
     * {@inheritdoc}
     */
    protected function getItems(): array
    {
        global $s2_db;

        // Obtaining posts
        $query  = [
            'SELECT' => 'p.create_time AS time, p.modify_time, p.url',
            'FROM'   => 's2_blog_posts AS p',
            'WHERE'  => 'p.published = 1',
        ];
        $result = $s2_db->query_build($query);

        $posts = [];
        while ($row = $s2_db->fetch_assoc($result)) {
            $row['rel_path'] = S2_BLOG_PATH . date('Y/m/d/', $row['time']) . urlencode($row['url']);
            $posts[]         = $row;
        }

        return $posts;
    }
}
