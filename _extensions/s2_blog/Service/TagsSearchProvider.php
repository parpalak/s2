<?php
/**
 * @copyright 2024-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
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
    public function findBlogTags(array $words): array
    {
        $tagIsUsedSql = $this->dbLayer
            ->select('1')
            ->from('s2_blog_post_tag AS pt')
            ->innerJoin('s2_blog_posts AS p', 'p.id = pt.post_id')
            ->where('pt.tag_id = t.id')
            ->andWhere('p.published = 1')
            ->limit(1)
            ->getSql()
        ;

        $statement = $this->dbLayer
            ->select('id AS tag_id, name, url')
            ->from('tags AS t')
            ->where('(' . $tagIsUsedSql . ') IS NOT NULL')
            ->andWhere('(' . implode(' OR ', array_fill(0, 2 * \count($words), 'name LIKE ?')) . ')')
            ->execute(array_merge(
                array_map(static fn(string $word) => $word . '%', $words),
                array_map(static fn(string $word) => '% ' . $word . '%', $words),
            ))
        ;

        $result = [];
        while ($row = $statement->fetchAssoc()) {
            if ($this->similarWordsDetector->wordIsSimilarToOtherWords($row['name'], $words)) {
                $result[] = '<a href="' . $this->blogUrlBuilder->tag($row['url']) . '">' . $row['name'] . '</a>';
            }
        }

        return $result;
    }
}
