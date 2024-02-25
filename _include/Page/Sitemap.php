<?php
/**
 * Creates Sitemap.
 *
 * @copyright 2021-2024 Roman Parpalak
 * @license MIT
 * @package S2
 */


use S2\Cms\Pdo\DbLayer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Page_Sitemap extends Page_Abstract implements Page_Routable
{
    /**
     * {@inheritdoc}
     */
    public function handle(Request $request): ?Response
    {
        $max_time = 0;
        $items    = '';

        // TODO Add tags pages
        foreach ($this->getItems() as $item) {
            $max_time = max($max_time, $item['modify_time'], $item['time']);

            if (!isset($item['link'])) {
                $item['link'] = s2_abs_link($item['rel_path']);
            }

            $items .= $this->renderPartial('sitemap_item', $item);
        }

        /** @var ?DbLayer $s2_db */
        $s2_db = \Container::getIfInstantiated(DbLayer::class);
        if ($s2_db) {
            $s2_db->close();
        }

        $output = $this->renderPartial('sitemap', compact('items'));

        ob_start();

        if (S2_COMPRESS) {
            ob_start('ob_gzhandler');
        }

        echo $output;

        if (S2_COMPRESS) {
            ob_end_flush();
        }

        header('Content-Length: ' . ob_get_length());
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $max_time) . ' GMT');
        header('Content-Type: text/xml; charset=utf-8');

        ob_end_flush();

        return null;
    }

    protected function getItems(): array
    {
        return Placeholder::articles_urls();
    }
}
