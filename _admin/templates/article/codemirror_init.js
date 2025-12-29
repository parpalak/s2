/**
 * HTML code highlighting
 *
 * CodeMirror initialization and helper functions.
 *
 * @copyright (C) 2012-2024 Roman Parpalak
 * @license MIT
 * @package S2
 */
const s2_codemirror = (function () {
    let instance, scrollTop = null;

    /** Duplicate a current line in CodeMirror doc */
    function cmDuplicateLine(cm) {
        // get a position of a current cursor in a current cell
        const currentCursor = cm.doc.getCursor();

        // read a content from a line where is the current cursor
        const lineContent = cm.doc.getLine(currentCursor.line);

        // go to the end the current line
        CodeMirror.commands.goLineEnd(cm);

        // make a break for a new line
        CodeMirror.commands.newlineAndIndent(cm);

        // move caret to the left position
        CodeMirror.commands.goLineStart(cm);

        // filled a content of the new line content with line above it
        cm.doc.replaceSelection(lineContent);

        // restore position cursor on the new line
        cm.doc.setCursor(currentCursor.line + 1, currentCursor.ch);
    }

    // from https://gist.github.com/Boorj/eb020e14487329431bdabc9141ee7ca1
    CodeMirror.keyMap.pcDefault["Ctrl-D"] = cmDuplicateLine;
    CodeMirror.keyMap.pcDefault["Ctrl-Y"] = CodeMirror.commands.deleteLine;

//    CodeMirror.keyMap.pcDefault["Ctrl-Y"] = CodeMirror.commands.findPersistent;

    const api = {
        get_instance: function (eTextarea) {
            scrollTop = eTextarea.scrollTop;

            instance = CodeMirror.fromTextArea(eTextarea, {
                extraKeys: {
                    "Ctrl-F": "findPersistent",
                    "F3": "findNext",
                    "Shift-F3": "findPrev",
                    "Ctrl-H": "replace"
                },
                mode: "text/html",
                smartIndent: false,
                indentUnit: 4,
                indentWithTabs: true,
                lineWrapping: true,
                spellcheck: true,
                inputStyle: "contenteditable",
                // Render all lines to keep accurate height mapping for sync scroll.
                viewportMargin: Infinity,
                foldGutter: true,
                gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"],
                selectionPointer: true
            });

            instance.on('paste', function (inst, event) {
                var items = (event.clipboardData || event.originalEvent.clipboardData).items,
                    hasImage = false;

                for (var i = 0; i < items.length; i++) {
                    var item = items[i];
                    if (item.type.indexOf("image") !== -1) {
                        optimizeAndUploadFile(item.getAsFile());
                        hasImage = true;
                    }
                }

                if (hasImage) {
                    event.preventDefault();
                }
                return !hasImage;
            });

            instance.on('drop', function (cm, e) {
                var dt = e.dataTransfer;
                if (!dt || !dt.files) {
                    return;
                }

                var files = dt.files, processed = false;
                for (var i = files.length; i--;) {
                    if (
                        files[i].type === 'image/jpeg'
                        || files[i].type === 'image/png'
                    ) {
                        processed = true;
                        uploadBlobToPictureDir(files[i], files[i].name, null, function (res, w, h) {
                            document.dispatchEvent(new CustomEvent('return_image.s2', {
                                detail: {
                                    file_path: res.file_path,
                                    width: w || 'auto',
                                    height: h || 'auto'
                                }
                            }));
                        });
                    }
                }

                if (processed) {
                    // Move cursor to a new position where a file was drpopped
                    cm.setSelection(cm.coordsChar({
                        left: e.x,
                        top: e.y
                    }));
                    e.preventDefault();
                }
            });

            api.restore_scroll();

            return instance;
        },

        close: function () {
            if (instance) {
                api.store_scroll();

                var eText = instance.getTextArea();
                instance.toTextArea();
                instance = null;
                if (scrollTop)
                    eText.scrollTop = scrollTop;
            }
        },

        store_scroll: function () {
            if (!instance)
                return;

            var eScroll = instance.getScrollerElement();
            if (typeof eScroll.scrollTop != 'undefined')
                scrollTop = eScroll.scrollTop;
        },

        restore_scroll: function () {
            if (instance && scrollTop)
                instance.getScrollerElement().scrollTop = scrollTop;
        },
        get_current: function () {
            return instance;
        },

        flip: function () {
            if (instance)
                instance.save();
        },

        addTag: function (sOpenTag, sCloseTag) {
            if (!instance) {
                return false;
            }

            var selections = instance.listSelections();
            var newSelections = [];
            var totalOffset = 0;

            instance.operation(function () {
                selections.forEach(function (selection) {
                    var anchor = selection.anchor;
                    var head = selection.head;

                    // Вычисляем начало и конец выделения
                    var start = anchor.line < head.line || (anchor.line === head.line && anchor.ch < head.ch) ? anchor : head;
                    var end = anchor.line > head.line || (anchor.line === head.line && anchor.ch > head.ch) ? anchor : head;

                    start = {line: start.line, ch: start.ch + totalOffset};
                    end = {line: end.line, ch: end.ch + totalOffset};
                    var text = instance.getRange(start, end);

                    if (text.substring(0, sOpenTag.length) === sOpenTag && text.substring(text.length - sCloseTag.length) === sCloseTag) {
                        text = text.substring(sOpenTag.length, text.length - sCloseTag.length);
                        instance.replaceRange(text, start, end);
                        totalOffset -= (sOpenTag.length + sCloseTag.length);
                        newSelections.push({anchor: start, head: {line: start.line, ch: start.ch + text.length}});
                    } else {
                        var newText = sOpenTag + text + sCloseTag;
                        instance.replaceRange(newText, start, end);
                        totalOffset += (sOpenTag.length + sCloseTag.length);
                        newSelections.push({
                            anchor: start,
                            head: {line: end.line, ch: end.ch + sOpenTag.length + sCloseTag.length}
                        });
                    }
                });
            });

            instance.setSelections(newSelections);
            instance.focus();
            return true;
        },

        smart: function () {
            if (!instance)
                return false;

            instance.setValue(SmartParagraphs(instance.getValue()));
            return true;
        },

        paragraph: function (sOpenTag, sCloseTag) {
            if (!instance)
                return false;

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

                    // Process first line and add to buffer
                    firstLine = sOpenTag + firstLine.replace(/^[ ]*<(?:p|blockquote|h[2-4])[^>]*>/, '');
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

                    // Process last line and replace in stored buffer
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
                    if (cursor.line === startLineIndex) {
                        cursor.ch += firstLine.length - firstLineOldLength;
                        if (cursor.ch < sOpenTag.length) {
                            cursor.ch = sOpenTag.length;
                        }
                        instance.setCursor(cursor, '*replaceparagraph');
                    } else if (cursor.line === i) {
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
    };

    return api;
}());

document.addEventListener('check_changes_start.s2', s2_codemirror.flip);
document.addEventListener('save_article_start.s2', s2_codemirror.flip);
document.addEventListener('changes_present.s2', s2_codemirror.flip);
