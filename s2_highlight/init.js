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
	var instance, scrolltop = null;

	return (
	{
		init: function ()
		{
			instance = CodeMirror.fromTextArea(document.forms['artform'].elements['page[text]'],
				{mode: "text/html", smartIndent: false, indentUnit: 4, indentWithTabs: true, lineWrapping: true});
		},

		close: function ()
		{
			if (instance)
			{
				instance.toTextArea();
				instance = null;
			}
		},

		beforeswitch: function (sType)
		{
			if (!instance)
				return;

			if (sType != 'edit_tab')
			{
				var eScrollableItem = instance.getScrollerElement();
				if (typeof(eScrollableItem.scrollTop) != 'undefined')
					scrolltop = eScrollableItem.scrollTop;
			}
		},

		tabswitch: function (sType)
		{
			if (instance && sType == 'edit_tab' && scrolltop)
				instance.getScrollerElement().scrollTop = scrolltop;
		},

		flip: function ()
		{
			if (instance)
				instance.save();
		},

		addtag: function (data)
		{
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
			instance.setValue(SmartParagraphs(instance.getValue()));
			return true;
		},

		paragraph: function (data)
		{
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

if (typeof(tinyMCE) == 'undefined')
{
	Hooks.add('request_article_start', s2_highlight.close);
	Hooks.add('request_article_end', s2_highlight.init);

	Hooks.add('fn_before_switch_start', s2_highlight.beforeswitch);
	Hooks.add('fn_tab_switch_start', s2_highlight.tabswitch);

	Hooks.add('fn_insert_paragraph_start', s2_highlight.paragraph);
	Hooks.add('fn_insert_tag_start', s2_highlight.addtag);
	Hooks.add('fn_paragraph_start', s2_highlight.smart);

	Hooks.add('fn_preview_start', s2_highlight.flip);
	Hooks.add('fn_changes_present', s2_highlight.flip);
	Hooks.add('fn_save_article_start', s2_highlight.flip);
	Hooks.add('fn_check_changes_start', s2_highlight.flip);
}
