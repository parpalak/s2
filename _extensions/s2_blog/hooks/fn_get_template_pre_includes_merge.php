<?php
/**
 * Hook fn_get_template_pre_includes_merge
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 *
 * @var \S2\Cms\Asset\AssetPack $assetPack
 */

if (!defined('S2_ROOT')) {
    die;
}

$assetPack->addCss('../../_extensions/s2_blog/style.css', [\S2\Cms\Asset\AssetPack::OPTION_MERGE]);
