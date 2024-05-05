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
