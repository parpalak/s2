<?php
/**
 * @copyright 2007-2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms\Model;

use S2\Cms\Pdo\DbLayer;

class TagsProvider
{
    // Note: Add cache invalidation in case of daemon mode
    private ?array $cachedTags = null;

    public function __construct(
        private readonly DbLayer    $dbLayer,
        private readonly UrlBuilder $urlBuilder,
        private readonly string     $tagsUrl
    ) {
    }

    /**
     * Makes tags list for the tags page and the placeholder
     *
     * @throws \S2\Cms\Pdo\DbLayerException
     */
    public function tagsList(): array
    {
        if ($this->cachedTags === null) {
            $subQuery = [
                'SELECT' => 'count(*)',
                'FROM'   => 'article_tag AS at',
                'JOINS'  => [
                    [
                        'INNER JOIN' => 'articles AS a',
                        'ON'         => 'a.id = at.article_id'
                    ]
                ],
                // Well, it's an inaccuracy because we don't check parents' "published" property
                'WHERE'  => 'a.published = 1 AND at.tag_id = t.tag_id'
            ];
            $query    = [
                'SELECT'   => 'tag_id, name, url, (' . $this->dbLayer->build($subQuery) . ') AS count',
                'FROM'     => 'tags AS t',
                'ORDER BY' => 'count DESC',
            ];
            $result   = $this->dbLayer->buildAndQuery($query);

            while ($row = $this->dbLayer->fetchAssoc($result)) {
                if ($row['count'] > 0) {
                    $this->cachedTags[] = array(
                        'title' => $row['name'],
                        'link'  => $this->urlBuilder->link('/' . rawurlencode($this->tagsUrl) . '/' . rawurlencode($row['url']) . '/'),
                        'num'   => $row['count'],
                    );
                }
            }
        }

        return $this->cachedTags;
    }

    public function getAllTags(): array
    {
        $subQuery = [
            'SELECT' => 'COUNT(*)',
            'FROM'   => 'article_tag AS at',
            'JOINS'  => [
                [
                    'INNER JOIN' => 'articles AS a',
                    'ON'         => 'a.id = at.article_id'
                ]
            ],
            // Well, it's an inaccuracy because we don't check parents' "published" property
            'WHERE'  => 'a.published = 1 AND at.tag_id = t.tag_id'
        ];
        $query    = [
            'SELECT'   => 'name, (' . $this->dbLayer->build($subQuery) . ') AS count',
            'FROM'     => 'tags AS t',
            'ORDER BY' => 'count DESC',
        ];
        $result   = $this->dbLayer->buildAndQuery($query);

        return array_column($this->dbLayer->fetchAssocAll($result), 'name');
    }
}
