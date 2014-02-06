<?php
/**
 * Functions of the latex extension
 *
 * @copyright (C) 2011-2013 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_latex
 */


if (!defined('S2_ROOT'))
	die;

function s2_encodeURIComponent($str)
{
	$revert = array('%21'=>'!', '%2A'=>'*', '%27'=>"'", '%28'=>'(', '%29'=>')');
	return strtr(rawurlencode($str), $revert);
}

function s2_latex_image ($matches)
{
	$formula = str_replace(array('&nbsp;', '&lt;', '&gt;', '&amp;'), array(' ', '<', '>', '&'), $matches[1]);
	return '<img border="0" style="vertical-align: middle;" src="http://tex.s2cms.ru/png/'.s2_htmlencode(s2_encodeURIComponent($formula)).'" alt="'.s2_htmlencode($formula).'" />';
}

function s2_latex_make ($text)
{
	return preg_replace_callback('#\\$\\$([^<>\\$]*)\\$\\$#Ss', 's2_latex_image', $text);
}

define('S2_LATEX_FUNCTIONS_LOADED', 1);