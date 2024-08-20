<?php
/**
 * Functions of the latex extension
 *
 * @copyright (C) 2011-2013 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_latex
 */


if (!defined('S2_ROOT')) {
    die;
}

function s2_latex_make($text): string
{
    return preg_replace_callback('#\\$\\$([^<]*?)\\$\\$#S', static function ($matches) {
        $formula = str_replace(['&nbsp;', '&lt;', '&gt;', '&amp;'], [' ', '<', '>', '&'], $matches[1]);
        return '<img border="0" style="vertical-align: middle;" src="//i.upmath.me/svg/' . s2_htmlencode(\s2_extensions\s2_latex\LatexHelper::encodeURIComponent($formula)) . '" alt="' . s2_htmlencode($formula) . '" />';
    }, $text);
}
