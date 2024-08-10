<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   s2_blog
 */

declare(strict_types=1);

namespace s2_extensions\s2_blog\Service;

use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
use s2_extensions\s2_blog\BlogUrlBuilder;
use s2_extensions\s2_search\Service\SimilarWordsDetector;

readonly class TagsSearchProvider
{
    public function __construct(
        private DbLayer              $dbLayer,
        private SimilarWordsDetector $similarWordsDetector,
        private BlogUrlBuilder       $blogUrlBuilder,
    ) {
    }

    /**
     * @throws DbLayerException
     */
    public function findBlogTags(array $where, array $words): array
    {
        $tagIsUsedSql = $this->dbLayer->build([
            'SELECT' => '1',
            'FROM'   => 's2_blog_post_tag AS pt',
            'JOINS'  => [
                [
                    'INNER JOIN' => 's2_blog_posts AS p',
                    'ON'         => 'p.id = pt.post_id'
                ]
            ],
            'WHERE'  => 'pt.tag_id = t.id AND p.published = 1',
            'LIMIT'  => '1'
        ]);

        $statement = $this->dbLayer->buildAndQuery([
            'SELECT' => 'id AS tag_id, name, url, (' . $tagIsUsedSql . ') AS used',
            'FROM'   => 'tags AS t',
            'WHERE'  => implode(' OR ', $where),
        ]);

        $result = [];
        while ($row = $this->dbLayer->fetchAssoc($statement)) {
            if ($row['used'] && $this->similarWordsDetector->wordIsSimilarToOtherWords($row['name'], $words)) {
                $result[] = '<a href="' . $this->blogUrlBuilder->tag($row['url']) . '">' . $row['name'] . '</a>';
            }
        }

        return $result;
    }
}
