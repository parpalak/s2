/**
 * HTML code highlighting
 *
 * CodeMirror initialization and helper functions.
 *
 * @copyright (C) 2012-2013 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_highlight
 */

var s2_highlight = (function () {
    var instance, scrolltop = null,
        enabled = is_local_storage && localStorage.getItem('s2_highlight_on') === '1';

    return (
        {
            get_instance: function () {
                var eText = document.forms['artform'].elements['page[text]'];
                scrolltop = eText.scrollTop;

                instance = CodeMirror.fromTextArea(eText, {
                    mode: "text/html",
                    smartIndent: false,
                    indentUnit: 4,
                    indentWithTabs: true,
                    lineWrapping: true,
                    spellcheck: true,
                    inputStyle: "contenteditable",
                    foldGutter: true,
                    gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"],
                    selectionPointer: true
                });

                instance.on('paste', function (inst, event) {
                    var items = (event.clipboardData || event.originalEvent.clipboardData).items,
                        hasImage = false;

                    for (var i = 0; i < items.length; i++) {
                        var item = items[i];
                        if (item.type.indexOf("image") != -1) {
                            optimizeAndUploadFile(item.getAsFile());
                            hasImage = true;
                        }
                    }

                    if (hasImage) {
                        event.preventDefault();
                    }
                    return !hasImage;
                });
                s2_highlight.restore_scroll();
            },

            init: function () {
                var $button = $('#s2_highlight_toggle_button').click(function () {
                    $(this).toggleClass('pressed');
                    if (enabled = !enabled)
                        s2_highlight.get_instance();
                    else
                        s2_highlight.close();
                    is_local_storage && localStorage.setItem('s2_highlight_on', enabled ? '1' : '0');
                });

                if (enabled) {
                    $button.addClass('pressed');
                    s2_highlight.get_instance();
                }
            },

            close: function () {
                if (instance) {
                    s2_highlight.store_scroll();

                    var eText = instance.getTextArea();
                    instance.toTextArea();
                    instance = null;
                    if (scrolltop)
                        eText.scrollTop = scrolltop;
                }
            },

            store_scroll: function () {
                if (!instance)
                    return;

                var eScroll = instance.getScrollerElement();
                if (typeof eScroll.scrollTop != 'undefined')
                    scrolltop = eScroll.scrollTop;
            },

            restore_scroll: function () {
                if (instance && scrolltop)
                    instance.getScrollerElement().scrollTop = scrolltop;
            },

            flip: function () {
                if (instance)
                    instance.save();
            },

            addtag: function (data) {
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

            smart: function () {
                if (!instance)
                    return false;

                instance.setValue(SmartParagraphs(instance.getValue()));
                return true;
            },

            paragraph: function (data) {
                if (!instance)
                    return false;

                var sOpenTag = data.openTag, sCloseTag = data.closeTag;

                if (instance.somethingSelected()) {
                    instance.replaceSelection(instance.getSelection().replace(
                        /^(?:[ ]*<(?:p|blockquote|h[2-4])[^>]*>)?([\s\S]*?)(?:<\/(?:p|blockquote|h[2-4])>)?[ ]*$/,
                        sOpenTag + '$1' + sCloseTag
                    ));
                } else {
                    var cursor = instance.getCursor(),
                        totalLineNum = instance.lineCount(),
                        currentLine = instance.getLine(cursor.line);

                    if (currentLine.replace(/^\s+|\s+$/g, '') === '') {
                        // Empty line
                        if ((totalLineNum <= cursor.line + 1 || instance.getLine(cursor.line + 1).replace(/^\s+|\s+$/g, '') === '') &&
                            (cursor.line <= 0 || instance.getLine(cursor.line - 1).replace(/^\s+|\s+$/g, '') === '')) {
                            // surrounded by empty lines
                            instance.replaceRange(
                                sOpenTag + sCloseTag,
                                {line: cursor.line, ch: 0},
                                {line: cursor.line, ch: 0}
                            );
                            instance.setCursor(cursor.line, sOpenTag.length);
                        }
                    } else {
                        // Cursor is on a non-empty line.
                        // Find non-empty lines before this line.
                        for (var i = cursor.line; i--;) {
                            if (instance.getLine(i).trim() === '') {
                                break;
                            }
                        }
                        i++;

                        var newLinesBuffer = [],
                            firstLine = instance.getLine(i),
                            startLineIndex = i,
                            firstLineOldLength = firstLine.length;

                        // Proces first line and add to buffer
                        firstLine = sOpenTag + firstLine.replace(/^(?:[ ]*<(?:p|blockquote|h[2-4])[^>]*>)/, '');
                        newLinesBuffer.push(firstLine);

                        // Find all non-empty lines after.
                        for (i++; i < totalLineNum; i++) {
                            var line = instance.getLine(i);
                            if (line.trim() === '') {
                                break;
                            }
                            // Add middle lines to buffer as is
                            newLinesBuffer.push(line);
                        }
                        i--;

                        var lastLine = newLinesBuffer[newLinesBuffer.length - 1],
                            lastLineLength = (i === startLineIndex ? firstLineOldLength : lastLine.length);

                        // Proces last line and replace in stored buffer
                        lastLine = lastLine.replace(/(?:<\/(?:p|blockquote|h[2-4])>)?[ ]*$/, '') + sCloseTag;
                        newLinesBuffer[newLinesBuffer.length - 1] = lastLine;

                        // We know the positions of old text and the new text
                        instance.replaceRange(
                            newLinesBuffer.join("\n"),
                            {line: startLineIndex, ch: 0},
                            {line: i, ch: lastLineLength},
                            '*replaceparagraph'
                        );

                        // Restore position of cursor inside shifted text
                        if (cursor.line == startLineIndex) {
                            cursor.ch += firstLine.length - firstLineOldLength;
                            if (cursor.ch < sOpenTag.length) {
                                cursor.ch = sOpenTag.length;
                            }
                            instance.setCursor(cursor, '*replaceparagraph');
                        } else if (cursor.line == i) {
                            if (cursor.ch > lastLine.length - sCloseTag.length) {
                                cursor.ch = lastLine.length - sCloseTag.length;
                            }
                            instance.setCursor(cursor, '*replaceparagraph');
                        }
                    }
                }

                instance.focus();

                return true;
            }
        });
}());

if (typeof tinyMCE == 'undefined') {
    $(document)
        .on('request_article_start.s2', s2_highlight.close)
        .on('request_article_end.s2', s2_highlight.init)
        .on('check_changes_start.s2 changes_present.s2 preview_start.s2 save_article_start.s2', s2_highlight.flip)
        .on('tab_switch_start.s2', function (e, sType) {
            if (sType == 'edit_tab')
                s2_highlight.restore_scroll();
        })
        .on('before_switch_start.s2', function (e, sType) {
            if (sType != 'edit_tab')
                s2_highlight.store_scroll();
        });

    Hooks.add('fn_insert_paragraph_start', s2_highlight.paragraph);
    Hooks.add('fn_insert_tag_start', s2_highlight.addtag);
    Hooks.add('fn_paragraph_start', s2_highlight.smart);
}
