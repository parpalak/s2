/**
 * HTML code highlighting
 *
 * CodeMirror initialization and helper functions.
 *
 * @copyright (C) 2012 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_highlight
 */

var s2_highlight = (function ()
{
	var instance, scrolltop = null,
		enabled = is_local_storage && localStorage.getItem('s2_highlight_on') == '1';

	return (
	{
		get_instance: function ()
		{
			var eText = document.forms['artform'].elements['page[text]'];
			scrolltop = eText.scrollTop;
			instance = CodeMirror.fromTextArea(eText,
				{mode: "text/html", smartIndent: false, indentUnit: 4, indentWithTabs: true, lineWrapping: true});
			s2_highlight.restore_scroll();
		},

		init: function ()
		{
			var $button = $('#s2_highlight_toggle_button').click(function ()
			{
				$(this).toggleClass('pressed');
				if (enabled = !enabled)
					s2_highlight.get_instance();
				else
					s2_highlight.close();
				is_local_storage && localStorage.setItem('s2_highlight_on', enabled ? '1' : '0');
			});

			if (enabled)
			{
				$button.addClass('pressed');
				s2_highlight.get_instance();
			}
		},

		close: function ()
		{
			if (instance)
			{
				s2_highlight.store_scroll();

				var eText = instance.getTextArea();
				instance.toTextArea();
				instance = null;
				if (scrolltop)
					eText.scrollTop = scrolltop;
			}
		},

		store_scroll: function ()
		{
			if (!instance)
				return;

			var eScroll = instance.getScrollerElement();
			if (typeof eScroll.scrollTop != 'undefined')
				scrolltop = eScroll.scrollTop;
		},

		restore_scroll: function ()
		{
			if (instance && scrolltop)
				instance.getScrollerElement().scrollTop = scrolltop;
		},

		flip: function ()
		{
			if (instance)
				instance.save();
		},

		addtag: function (data)
		{
			if (!instance)
				return false;

			var sOpenTag = data.openTag, sCloseTag = data.closeTag,
				text = instance.getSelection();

			if (text.substring(0, sOpenTag.length) == sOpenTag && text.substring(text.length - sCloseTag.length) == sCloseTag)
				text = text.substring(sOpenTag.length, text.length - sCloseTag.length);
			else
				text = sOpenTag + text + sCloseTag;

			instance.replaceSelection(text);
			instance.focus();
			return true;
		},

		smart: function ()
		{
			if (!instance)
				return false;

			instance.setValue(SmartParagraphs(instance.getValue()));
			return true;
		},

		paragraph: function (data)
		{
			if (!instance)
				return false;

			var sOpenTag = data.openTag, sCloseTag = data.closeTag;

			if (instance.somethingSelected())
				instance.replaceSelection(instance.getSelection().replace(/^(?:[ ]*<(?:p|blockquote|h[2-4])[^>]*>)?([\s\S]*?)(?:<\/(?:p|blockquote|h[2-4])>)?[ ]*$/, sOpenTag + '$1' + sCloseTag));
			else
			{
				var cursor = instance.getCursor(), line_num = instance.lineCount();

				if (instance.getLine(cursor.line).replace(/^\s+|\s+$/g, '') == '')
				{
					// Empty line
					if ((line_num <= cursor.line + 1 || instance.getLine(cursor.line + 1).replace(/^\s+|\s+$/g, '') == '') &&
						(cursor.line <= 0 || instance.getLine(cursor.line - 1).replace(/^\s+|\s+$/g, '') == ''))
					{
						// surrounded by empty lines
						instance.setLine(cursor.line, sOpenTag + sCloseTag);
						instance.setCursor(cursor.line, sOpenTag.length);
					}
				}
				else
				{
					// Look for empty lines before
					for (var i = cursor.line; i-- ;)
						if (instance.getLine(i).replace(/^\s+|\s+$/g, '') == '')
							break;

					i++;
					var text = instance.getLine(i),
						old_length = text.length;

					text = text.replace(/^(?:[ ]*<(?:p|blockquote|h[2-4])[^>]*>)/, '');
					instance.setLine(i, sOpenTag + text);

					if (cursor.line == i)
					{
						cursor.ch += text.length - old_length;
						if (cursor.ch < 0)
							cursor.ch = 0;
						cursor.ch += sOpenTag.length;
						instance.setCursor(cursor);
					}

					// Look for empty lines after
					for (i = cursor.line + 1; i < line_num; i++)
						if (instance.getLine(i).replace(/^\s+|\s+$/g, '') == '')
							break;

					i--;
					text = instance.getLine(i);
					old_length = text.length;

					text = text.replace(/(?:<\/(?:p|blockquote|h[2-4])>)?[ ]*$/, '');
					instance.setLine(i, text + sCloseTag);

					if (cursor.line == i)
					{
						if (cursor.ch > text.length)
							cursor.ch = text.length;
						instance.setCursor(cursor);
					}
				}
			}

			instance.focus();

			return true;
		}
	});
}());

if (typeof tinyMCE == 'undefined')
{
	Hooks.add('request_article_start', s2_highlight.close);
	Hooks.add('request_article_end', s2_highlight.init);

	Hooks.add('fn_before_switch_start', function (sType)
	{
		if (sType != 'edit_tab')
			s2_highlight.store_scroll();
	});
	Hooks.add('fn_tab_switch_start', function (sType)
	{
		if (sType == 'edit_tab')
			s2_highlight.restore_scroll();
	});

	Hooks.add('fn_insert_paragraph_start', s2_highlight.paragraph);
	Hooks.add('fn_insert_tag_start', s2_highlight.addtag);
	Hooks.add('fn_paragraph_start', s2_highlight.smart);

	Hooks.add('fn_preview_start', s2_highlight.flip);
	Hooks.add('fn_changes_present', s2_highlight.flip);
	Hooks.add('fn_save_article_start', s2_highlight.flip);
	Hooks.add('fn_check_changes_start', s2_highlight.flip);
}
