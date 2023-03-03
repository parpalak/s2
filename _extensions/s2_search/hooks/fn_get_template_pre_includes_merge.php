<?php
/**
 * Hook fn_get_template_pre_includes_merge
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_search
 *
 * @var \S2\Cms\Asset\AssetPack $assetPack
 */

if (!defined('S2_ROOT')) {
    die;
}

$assetPack->addCss('../../_extensions/s2_search/style.css', [\S2\Cms\Asset\AssetPack::OPTION_MERGE]);

if (S2_SEARCH_QUICK) {
    $assetPack
        ->addJs('../../_extensions/s2_search/autosearch.js', [\S2\Cms\Asset\AssetPack::OPTION_MERGE])
        ->addInlineJs('<script>var s2_search_url = "' . S2_PATH . '/_extensions/s2_search";</script>')
    ;
}
