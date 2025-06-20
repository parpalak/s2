<?php
/**
 * Creates Sitemap.
 *
 * @copyright 2021-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Controller;

use S2\Cms\Framework\ControllerInterface;
use S2\Cms\Model\ArticleProvider;
use S2\Cms\Model\UrlBuilder;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
use S2\Cms\Template\Viewer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Sitemap implements ControllerInterface
{
    public function __construct(
        protected DbLayer         $dbLayer,
        protected ArticleProvider $articleProvider,
        protected UrlBuilder      $urlBuilder,
        protected Viewer          $viewer,
        protected bool            $useHierarchy,
    ) {
    }

    /**
     * {@inheritdoc}
     * @throws DbLayerException
     */
    public function handle(Request $request): Response
    {
        $maxContentTime = 0;
        $items          = '';

        // TODO Add tags pages
        foreach ($this->getItems() as $item) {
            $maxContentTime = max($maxContentTime, $item['modify_time'], $item['time']);

            if (!isset($item['link'])) {
                $item['link'] = $this->urlBuilder->absLink($item['rel_path']);
            }

            $items .= $this->viewer->render('sitemap_item', $item);
        }

        $output = $this->viewer->render('sitemap', compact('items'));

        $response = new Response($output);
        $response->headers->set('Content-Length', (string)\strlen($output));
        $response->headers->set('Content-Type', 'text/xml; charset=utf-8');
        $response->setLastModified(new \DateTimeImmutable('@' . $maxContentTime));

        return $response;
    }

    /**
     * @throws DbLayerException
     */
    protected function getItems(): array
    {
        $rawQuery = $this->dbLayer
            ->select('1')
            ->from('articles AS a2')
            ->where('a2.parent_id = a.id')
            ->andWhere('a2.published = 1')
            ->limit(1)
            ->getSql()
        ;

        $result = $this->dbLayer
            ->select('a.id, a.title, a.create_time, a.modify_time, a.url, a.parent_id, (' . $rawQuery . ') IS NOT NULL AS children_exist')
            ->from('articles AS a')
            ->where('(a.create_time <> 0 OR a.modify_time <> 0)')
            ->andWhere('a.published = 1')
            ->execute()
        ;

        $articles = $urls = $parentIds = [];
        for ($i = 0; $row = $result->fetchAssoc(); $i++) {
            $urls[$i] = rawurlencode($row['url']) . ($this->useHierarchy && $row['children_exist'] ? '/' : '');

            $parentIds[$i] = $row['parent_id'];

            $articles[$i]['time']        = $row['create_time'];
            $articles[$i]['modify_time'] = $row['modify_time'];
        }

        $urls = $this->articleProvider->getFullUrlsForArticles($parentIds, $urls);

        foreach ($articles as $k => $v) {
            if (isset($urls[$k])) {
                $articles[$k]['rel_path'] = $urls[$k];
            } else {
                unset($articles[$k]);
            }
        }

        return $articles;
    }
}
