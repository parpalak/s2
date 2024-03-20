<?php
/**
 * Hook fn_get_template_start
 *
 * @copyright 2023-2024 Roman Parpalak
 * @license MIT
 * @package s2_tpl_edit
 *
 * @var $templateId
 */

if (!defined('S2_ROOT')) {
    die;
}

$s2_tpl_edit_cached = false;
if (file_exists($this->cacheDir . 's2_tpl_edit_' . $this->styleName . '_' . $templateId)) {
    $path = $this->cacheDir . 's2_tpl_edit_' . $this->styleName . '_' . $templateId;

    $s2_tpl_edit_cached = true;
}
