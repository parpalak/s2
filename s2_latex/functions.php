<?php
/**
 * Functions of the latex extension
 *
 * @copyright (C) 2011 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_latex
 */

function s2_latex_image ($matches)
{
	global $ext_info;
	$formula = $matches[1];
	return '<img class="s2_latex" border="0" style="vertical-align: middle;" src="'.S2_BASE_URL.'/_extensions/s2_latex/latex.php?type=gif&amp;latex='.rawurlencode($formula).'" alt="'.$formula.'" />';
}

function s2_latex_make ($text)
{
	return preg_replace_callback('#\$\$([^<>\$]*)\$\$#Ss', 's2_latex_image', $text);
}

define('S2_LATEX_FUNCTIONS_LOADED', 1);