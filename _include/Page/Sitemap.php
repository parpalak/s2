<?php

use S2\Cms\Pdo\DbLayer;

/**
 * Creates Sitemap.
 *
 * @copyright (C) 2021 Roman Parpalak
 * @license       http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package       S2
 */


class Page_Sitemap extends Page_Abstract implements Page_Routable
{
    /**
     * {@inheritdoc}
     */
    public function render()
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
    }

    protected function getItems(): array
    {
        return Placeholder::articles_urls();
    }
}
