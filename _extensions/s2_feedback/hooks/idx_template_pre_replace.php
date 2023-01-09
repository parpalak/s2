<?php
/**
 * Hook idx_template_pre_replace
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_feedback
 */

 if (!defined('S2_ROOT')) {
     die;
}

if (strpos($template, '<!-- s2_feedback -->') !== false)
{
	Lang::load('s2_feedback', function () use ($ext_info)
	{
		if (file_exists(S2_ROOT.'/_extensions/s2_feedback'.'/lang/'.S2_LANGUAGE.'.php'))
			return require S2_ROOT.'/_extensions/s2_feedback'.'/lang/'.S2_LANGUAGE.'.php';
		else
			return require S2_ROOT.'/_extensions/s2_feedback'.'/lang/English.php';
	});
	include S2_ROOT.'/_extensions/s2_feedback'.'/functions.php';
	$replace['<!-- s2_feedback -->'] = s2_feedback_form(S2_PATH.'/_extensions/s2_feedback'.'/feedback.php');
}
