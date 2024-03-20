<?php
/**
 * Creates Sitemap.
 *
 * @copyright 2021-2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms\Controller;

use S2\Cms\Framework\ControllerInterface;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Template\Viewer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Sitemap implements ControllerInterface
{
    public function __construct(
        protected DbLayer $dbLayer,
        protected Viewer $viewer,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request): Response
    {
        $maxContentTime = 0;
        $items          = '';

        // TODO Add tags pages
        foreach ($this->getItems() as $item) {
            $maxContentTime = max($maxContentTime, $item['modify_time'], $item['time']);

            if (!isset($item['link'])) {
                $item['link'] = s2_abs_link($item['rel_path']);
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

    protected function getItems(): array
    {
        $subquery = [
            'SELECT'	=> '1',
            'FROM'		=> 'articles AS a2',
            'WHERE'		=> 'a2.parent_id = a.id AND a2.published = 1',
            'LIMIT'		=> '1'
        ];
        $raw_query_child_num = $this->dbLayer->build($subquery);

        $query = [
            'SELECT'	=> 'a.id, a.title, a.create_time, a.modify_time, a.url, a.parent_id, ('.$raw_query_child_num.') IS NOT NULL AS children_exist',
            'FROM'		=> 'articles AS a',
            'WHERE'		=> '(a.create_time <> 0 OR a.modify_time <> 0) AND a.published = 1',
        ];

        $result = $this->dbLayer->buildAndQuery($query);

        $articles = $urls = $parent_ids = [];
        for ($i = 0; $row = $this->dbLayer->fetchAssoc($result); $i++)
        {
            $urls[$i] = urlencode($row['url']).(S2_USE_HIERARCHY && $row['children_exist'] ? '/' : '');

            $parent_ids[$i] = $row['parent_id'];

            $articles[$i]['time'] = $row['create_time'];
            $articles[$i]['modify_time'] = $row['modify_time'];
        }

        $urls = \S2\Cms\Model\Model::get_group_url($parent_ids, $urls);

        foreach ($articles as $k => $v) {
            if (isset($urls[$k])) {
                $articles[$k]['rel_path'] = $urls[$k];
            }
            else {
                unset($articles[$k]);
            }
        }

        return $articles;
    }
}
