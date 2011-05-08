<?php
/**
 * Functions for spoiler
 *
 * @copyright (C) 2011 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_spoiler
 */


function s2_spoiler_store ($matches)
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

function s2_spoiler_markup ($matches)
{
	global $s2_spoiler_num, $lang_s2_spoiler;

	$s2_spoiler_num++;

	$header = trim($matches[1]);
	if ($header == '')
		$header = $lang_s2_spoiler['Hidden text'];

	return '<div class="s2_spoiler" id="s2_spoiler_'.$s2_spoiler_num.'"><div class="s2_spoiler_head" onclick="s2_spoiler_flip(this);">'.$header.'</div><div class="s2_spoiler_body">'.$matches[2].'</div></div>';
}

function s2_spoiler_make ($contents, $soft = 0)
{
	global $s2_spoiler_num;

	$contents = preg_replace_callback('#<(script|style|textarea|pre|code|kbd).*?</\\1>#s', 's2_spoiler_store', $contents);

	$s2_spoiler_num = 0;
	$contents = preg_replace_callback('#<spoiler(?:\\s+title="([^"]*)")?\\s*>(.*?)</spoiler>#s', 's2_spoiler_markup', $contents);
	$contents = preg_replace_callback('#\\[spoiler(?:\\s+title="([^"]*)")?\s*\\](.*?)\\[/spoiler\\]#s', 's2_spoiler_markup', $contents);

	while (preg_match('#¬ (\d*) ¬#S', $contents))
		$contents = preg_replace_callback ('#(¬) (\d*) ¬#S', 's2_spoiler_store', $contents);

	if ($s2_spoiler_num)
	{
		ob_start();
?>
<script type="text/javascript">
function s2_spoiler_get_height (eItem)
{
	var rect = eItem.getBoundingClientRect();
	return rect ? (rect.bottom - rect.top) : eItem.offsetHeight;
}

function s2_spoiler_animate (eItem, sProperty, from, to, duration, fFinalize)
{
	var start = new Date().getTime();
	setTimeout(function() {
		var progress = ((new Date().getTime()) - start) / duration;
		eItem.style['height'] = parseInt((to - from) * 0.5 * (1 - Math.cos(progress * Math.PI)) + from) + "px";
		if (progress < 1)
			setTimeout(arguments.callee, 10);
		else
			fFinalize();
	}, 10);
}

function s2_spoiler_flip (eItem)
{
	var eParent = eItem.parentNode;
	if (eItem.className == 's2_spoiler_head')
	{
		eItem.className = 's2_spoiler_head_expand';
		var from = s2_spoiler_get_height(eItem);
		eParent.style.height = from + 'px';
		eParent.style.overflow = 'hidden';
		eItem.nextSibling.style.display = 'block';
		var to = from + s2_spoiler_get_height(eItem.nextSibling);
		var finalize = function ()
		{
			eParent.style.height = eParent.style.overflow = 'auto';
		}
	}
	else
	{
		eItem.className = 's2_spoiler_head';
		var from = s2_spoiler_get_height(eParent);
		var to = s2_spoiler_get_height(eItem);
		eParent.style.height = from + 'px';
		eParent.style.overflow = 'hidden';
		var finalize = function ()
		{
			eItem.nextSibling.style.display = 'none';
			eParent.style.height = eParent.style.overflow = 'auto';
		}
	}
	s2_spoiler_animate(eParent, 'height', from, to, 200, finalize);
}

(function () {
	var head = document.getElementsByTagName('head')[0],
		style = document.createElement('style'),
		rules = '.s2_spoiler {margin: 1em 0;} .s2_spoiler_body {display: none;} .s2_spoiler_head, .s2_spoiler_head_expand {display: inline-block; border-bottom: 1px dashed; cursor: pointer;} .s2_spoiler_head:before, .s2_spoiler_head_expand:before {position: absolute; margin-left: -14px; margin-top: 6px; display: inline-block; content: "+"; border: 1px solid #999; color: #999; background: #fff; line-height: 8px; font-size: 14px; width: 8px; text-align: center; cursor: pointer; } .s2_spoiler_head_expand:before {content: \'\\2212\'}';

	style.type = 'text/css';
	if (style.styleSheet)
		style.styleSheet.cssText = rules;
	else
		style.appendChild(document.createTextNode(rules));
	head.appendChild(style);
})();
</script>
<?
		$script = ob_get_clean();
		$contents = str_replace('</body>', $script.'</body>', $contents);
	}

	return $contents;
}
