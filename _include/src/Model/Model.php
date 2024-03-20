<?php

namespace S2\Cms\Model;

use S2\Cms\Pdo\DbLayer;

/**
 * Helper functions for handling pages stored in DB.
 *
 * @copyright (C) 2007-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */
class Model
{
    const ROOT_ID = 0;
    //
    // Get URLs for some articles as if there is only one.
    // Returns an array containing full URLs, keys are preserved.
    // If somewhere is a hidden parent, the URL is removed from the returning array.
    //
    // Actually it's one of the best things in S2! :)
    //
    public static function get_group_url($parent_ids, $urls)
    {
        /** @var DbLayer $s2_db */
        $s2_db = \Container::get(DbLayer::class);

        if (!S2_USE_HIERARCHY) {
            // Flat urls
            foreach ($urls as $k => $url)
                $urls[$k] = '/' . $url;

            return $urls;
        }

        while (count($parent_ids)) {
            $flags = array();
            foreach ($parent_ids as $k => $v)
                $flags[$k] = 1;

            $query = array(
                'SELECT' => 'id, parent_id, url',
                'FROM'   => 'articles',
                'WHERE'  => 'id IN (' . implode(', ', array_unique($parent_ids)) . ') AND published = 1'
            );
            ($hook = s2_hook('fn_get_cascade_urls_loop_pre_query')) ? eval($hook) : null;
            $result = $s2_db->buildAndQuery($query);

            while ($row = $s2_db->fetchAssoc($result))
                // So, the loop may seem not pretty much.
                // But $parent_ids values don't have to be unique.
                foreach ($parent_ids as $k => $v)
                    if ($parent_ids[$k] == $row['id'] && $flags[$k]) {
                        $parent_ids[$k] = $row['parent_id'];
                        $urls[$k]       = urlencode($row['url']) . '/' . $urls[$k];
                        $flags[$k]      = 0;
                        if ($row['parent_id'] == self::ROOT_ID)
                            // Thread finished - we are at the root.
                            unset($parent_ids[$k]);
                    }

            // Thread was cut (published = 0). Remove the entry in $url.
            foreach ($flags as $k => $flag)
                if ($flag) {
                    unset($urls[$k]);
                    unset($parent_ids[$k]);
                }
        }

        return $urls;
    }

    //
    // Returns the full path for an article
    //
    public static function path_from_id($id, $visible_for_all = false)
    {
        /** @var DbLayer $s2_db */
        $s2_db = \Container::get(DbLayer::class);

        if ($id < 0)
            return false;

        if ($id == self::ROOT_ID)
            return '';

        $query = array(
            'SELECT' => 'url, parent_id',
            'FROM'   => 'articles',
            'WHERE'  => 'id = ' . $id . ($visible_for_all ? ' AND published = 1' : '')
        );
        ($hook = s2_hook('fn_path_from_id_pre_qr')) ? eval($hook) : null;
        $result = $s2_db->buildAndQuery($query);

        $row = $s2_db->fetchRow($result);
        if (!$row)
            return false;

        if ($row[1] == self::ROOT_ID)
            return '';

        if (S2_USE_HIERARCHY) {
            $prefix = self::path_from_id($row[1], $visible_for_all);
            if ($prefix === false)
                return false;
        } else
            $prefix = '';

        return $prefix . '/' . urlencode($row[0]);
    }

    //
    // Returns the title of the main page
    // TODO cache
    //
    public static function main_page_title()
    {
        /** @var DbLayer $s2_db */
        $s2_db = \Container::get(DbLayer::class);

        $query = array(
            'SELECT' => 'title',
            'FROM'   => 'articles',
            'WHERE'  => 'parent_id = ' . self::ROOT_ID,
        );

        ($hook = s2_hook('fn_s2_main_page_title_qr')) ? eval($hook) : null;

        $result = $s2_db->buildAndQuery($query);
        return $s2_db->result($result);
    }
}
