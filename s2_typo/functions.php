<?php
/**
 * Functions for Russian typography
 *
 * Converts '""' quotation marks to '«»' and '„“' and puts non-breaking space
 * characters according to Russian typography conventions.
 *
 * @copyright (C) 2010-2012 Roman Parpalak, based on code (C) by Dmitry Smirnov
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_typo
 */


if (!defined('S2_ROOT'))
	die;

function s2_typo_store ($matches)
{
	static $stack = array();
	static $i = 0;

	if (count($matches) < 3)
	{
		$stack[$i] = $matches[0];
		return '¬ '.($i++).' ¬';
	}
	else
	{
		$temp = $stack[$matches[2]];
		unset($stack[$matches[2]]);
		return $temp;
	}
}

function s2_typo_replace_q ($matches)
{
	return str_replace(array('"', '&quot;'), array('¬', "\xc0"), $matches[0]);
}

//
// This function makes everything :)
//
// The code written by Dmitry Smirnov is used here.
// See http://spectator.ru/technology/php/quotation_marks_stike_back for details.
//
function s2_typo_make ($contents, $soft = 0)
{
	$nbsp = $soft ? "\xc2\xa0" : '&nbsp;';

	$contents = str_replace("\xc0", '', $contents);

	// Escape sensitive data
	$contents = preg_replace_callback('#<(script|style|textarea|pre|code|kbd).*?</\\1>#s', 's2_typo_store', $contents);
	$contents = preg_replace_callback('#<[^>]*>#sS', 's2_typo_replace_q', $contents); 

	$contents = "\n".str_replace(array('&quot;', "\xc0"), array('"', '&quot;'), $contents);

	// Qutation marks
	$contents = preg_replace ('#(?<=[(\s">]|¬|^)"([^"]*[^\s"(])"#S', '«\\1»', $contents);

	// Nested quotation marks
	if (strpos($contents, '"') !== false)
	{
		$contents = preg_replace('#(?<=[(\s">]|¬|^)"([^"]*[^\s"(])"#S', '«\\1»', $contents);
		while (preg_match('#«([^«»]*)«([^»]*)»#u', $contents, $regs))
			$contents = str_replace ($regs[0], '«'.$regs[1].'„'.$regs[2].'“', $contents);
	}

	$replace = array(
		// Some special chars
		'...'	=> '…',
		'(tm)'	=> '™',
		'(TM)'	=> '™',
		'(c)'	=> '©',
		'(C)'	=> '©',

		// XHTML fixes
		'<br>'	=> '<br />',
		'<hr>'	=> '<hr />',
		'<s>'	=> '<span style="text-decoration: line-through;">',
		'</s>'	=> '</span>',

		// '-' to em-dash
		"\n- "	=> "\n— ",
		' - '	=> $nbsp.'— ',
		' — '	=> $nbsp.'— ',
		'¬- '	=> '¬— ',
		'>- '	=> '>— ',
	);

	// Particles
	foreach (array('ли', 'ль', 'же', 'ж', 'бы', 'б') as $particle)
		foreach (array(' ', '.', ',', ';', ')', ':') as $end)
			$replace[' '.$particle.$end] = $nbsp.$particle.$end;

	// This preg_replace is too slow :(
	//$contents = preg_replace('#(?<=\s|\()\S+?-\S+#S', '<nobr>\\0</nobr>', $contents);

	// Prepositions starting sentences
	foreach (array('Не', 'Ни', 'Но', 'По', 'Ко', 'К', 'За', 'Со', 'С', 'У', 'Из', 'И', 'А', 'О', 'Об', 'От', 'До', 'В', 'Во', 'На') as $preposition)
		$replace[$preposition.' '] = $preposition.$nbsp;

	$contents = strtr($contents, $replace);

	$replace = array();

	// Prepositions inside sentences
	foreach (array('к', 'с', 'у', 'и', 'а', 'о', 'в') as $preposition)
		foreach (array('(', ' ', $soft ? "\xc2\xa0" : ';') as $start)
			$replace[$start.$preposition.' '] = $start.$preposition.$nbsp;
	$contents = str_replace(array_keys($replace), array_values($replace), $contents);

	// Put sensitive data back
	while (preg_match('#¬ (\d*) ¬#S', $contents))
		$contents = preg_replace_callback ('#(¬) (\d*) ¬#S', 's2_typo_store', $contents);

	$contents = str_replace ('¬', '"', $contents); 

	// Move quotation marks outside links
	$contents = preg_replace(
		'#<a ([^>]*)>\\s*«([^<]*?)»\\s*</a>#Ss',
		'«<a \\1>\\2</a>»',
		$contents
		);

	return trim($contents);
}
