<?php
/**
 * Hook fn_get_template_pre_includes_merge
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_latex
 *
 * @var AssetPack $assetPack
 */

use S2\Cms\Asset\AssetPack;

if (!defined('S2_ROOT')) {
    die;
}

$assetPack->addJs('//i.upmath.me/latex.js', [AssetPack::OPTION_PRELOAD, AssetPack::OPTION_DEFER]);
