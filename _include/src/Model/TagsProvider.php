<?php
/**
 * @copyright 2007-2025 Roman Parpalak
 * @license   https://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Model;

use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;

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
     * @throws DbLayerException
     */
    public function tagsList(): array
    {
        if ($this->cachedTags === null) {
            $result = $this->dbLayer
                ->select('id AS tag_id, name, url, (' . $this->dbLayer
                        ->select('COUNT(*)')
                        ->from('article_tag AS at')
                        ->innerJoin('articles AS a', 'a.id = at.article_id')
                        // Well, it's an inaccuracy because we don't check parents' "published" property
                        ->where('a.published = 1')
                        ->andWhere('at.tag_id = t.id')
                        ->getSql()
                    . ') AS count')
                ->from('tags AS t')
                ->orderBy('count DESC')
                ->execute()
            ;

            while ($row = $result->fetchAssoc()) {
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

    /**
     * @throws DbLayerException
     */
    public function getAllTags(): array
    {
        $result = $this->dbLayer
            ->select('name, (' . $this->dbLayer
                    ->select('COUNT(*)')
                    ->from('article_tag AS at')
                    ->innerJoin('articles AS a', 'a.id = at.article_id')
                    // Well, it's an inaccuracy because we don't check parents' "published" property
                    ->where('a.published = 1')
                    ->andWhere('at.tag_id = t.id')
                    ->getSql()
                . ') AS count'
            )
            ->from('tags AS t')
            ->orderBy('count DESC')
            ->execute()
        ;

        return $result->fetchColumn();
    }
}
