/**
 * Form for editing pages in S2.
 *
 * @copyright 2007-2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

function initHtmlTextarea(eTextarea) {
    s2_codemirror.get_instance(eTextarea);

    document.addEventListener('insert_paragraph.s2', function (event) {
        const sType = event.detail.sType;
        if (sType === 'h2' || sType === 'h3' || sType === 'h4' || sType === 'blockquote' || sType === 'pre') {
            s2_codemirror.paragraph('<' + sType + '>', '</' + sType + '>');
        } else {
            s2_codemirror.paragraph('<p' + (sType ? ' align="' + sType + '"' : '') + '>', '</p>');
        }
    });

    document.addEventListener('insert_tag.s2', function (event) {
        s2_codemirror.addTag(event.detail.sStart, event.detail.sEnd);
    })

    // Используем parentNode, чтобы обработчик был на диве-обертке текстарии и перехватывал события из CodeMirror
    eTextarea.parentNode.addEventListener('keydown', function (e) {
        function insertParagraph(sType) {
            document.dispatchEvent(new CustomEvent('insert_paragraph.s2', {detail: {sType: sType}}));
        }

        function tagSelection(sTag) {
            return insertTag('<' + sTag + '>', '</' + sTag + '>');
        }

        function insertTag(sStart, sEnd) {
            document.dispatchEvent(new CustomEvent('insert_tag.s2', {detail: {sStart: sStart, sEnd: sEnd}}));
        }

        const ch = String.fromCharCode(e.which).toLowerCase();

        if (e.ctrlKey && !e.shiftKey) {
            if (ch === 'i')
                tagSelection('em');
            else if (ch === 'b')
                tagSelection('strong');
            else if (ch === 'q')
                insertParagraph('blockquote');
            else if (ch === 'l')
                insertParagraph('');
            else if (ch === 'e')
                insertParagraph('center');
            else if (ch === 'r')
                insertParagraph('right');
            else if (ch === 'j')
                insertParagraph('justify');
            else if (ch === 'k')
                insertTag('<a href="">', '</a>');
            else if (ch === 'o')
                tagSelection('nobr');
            else if (ch === 'p')
                GetImage();
            else
                return;
            e.preventDefault();
        }
    });
}

function initArticleEditForm(eForm, statusData, sEntityName, sTextareaName) {
    const sLowerEntityName = sEntityName.toLowerCase();

    function decorateForm(statusData) {
        const urlWrapper = eForm.querySelector('.field-url');
        urlWrapper.setAttribute('data-url-status', statusData['urlStatus']);
        urlWrapper.title = statusData['urlTitle'];
        if (statusData['urlStatus'] === 'mainpage') {
            urlWrapper.querySelector('input').setAttribute('disabled', 'disabled');
        }

        const isPublished = eForm.elements['published'].checked;
        eForm.querySelector('.field-published').setAttribute('data-published-status', isPublished ? '1' : '0');

        const ePreviewLink = eForm.querySelector('#preview_link');
        ePreviewLink.href = ePreviewLink.getAttribute('data-href').replace(/\/[^\/]*$/, '/') + encodeURIComponent(eForm.elements['url'].value);
        ePreviewLink.style.display = isPublished ? 'inline' : 'none';
    }

    decorateForm(statusData);

    async function saveForm(event) {
        event.preventDefault();

        function successHandler(statusData) {
            PopupMessages.hide(sLowerEntityName + '-save');
            document.dispatchEvent(new Event('save_article_end.s2'));

            eForm.elements['revision'].value = statusData['revision'];
            decorateForm(statusData);
        }

        function errorHandler(data) {
            Array.from(data.errors).forEach(function (error) {
                // TODO array_merge
                PopupMessages.show(error, null, null, sLowerEntityName + '-save');
            });
            console.warn('Form submission failed');
        }

        function getTempCsrfToken() {
            return document.cookie
                .split('; ')
                .find((row) => row.startsWith('adminyard_temp_csrf_token='))
                ?.split('=')[1] || '';
        }

        try {
            const formData = new FormData(eForm);
            const headers = {'X-Requested-With': 'XMLHttpRequest'};
            const tempCsrfToken = getTempCsrfToken();
            if (tempCsrfToken !== '') {
                headers['X-AdminYard-CSRF-Token'] = tempCsrfToken;
            }
            const response = await fetch(eForm.action, {method: 'POST', headers: headers, body: formData});

            if (response.ok) {
                successHandler(await response.json());
            } else if (response.status === 422) {
                const data = await response.json();
                if (data.invalid_csrf_token) {
                    const response2 = await fetch(eForm.action, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-AdminYard-CSRF-Token': getTempCsrfToken()
                        },
                        body: formData
                    });

                    if (response2.ok) {
                        successHandler(await response2.json());
                    } else if (response2.status === 422) {
                        errorHandler(await response2.json());
                    }
                } else {
                    errorHandler(data);
                }
            }
        } catch (error) {
            console.warn('An error occurred:', error);
        }
    }

    eForm.addEventListener('submit', saveForm);
    document.addEventListener('save_form.s2', saveForm);

    document.addEventListener('return_image.s2', function (e) {
        let w = e.detail.width;
        let h = e.detail.height;
        let s = e.detail.file_path;

        if (isAltPressed()) {
            // For retina
            if (!isNaN(parseInt(w))) {
                w = parseInt(w) / 2;
            }
            if (!isNaN(parseInt(h))) {
                h = parseInt(h) / 2;
            }
        }
        s = encodeURI(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/'/g, '&#039;').replace(/"/g, '&quot;');

        const sOpenTag = '<img src="' + s + '" width="' + w + '" height="' + h + '" ' + 'loading="lazy" alt="',
            sCloseTag = '" />';
        document.dispatchEvent(new CustomEvent('insert_tag.s2', {detail: {sStart: sOpenTag, sEnd: sCloseTag}}));

        const dialog = document.getElementById('picture_dialog');
        dialog.close();
    })

    var Changes = (function () {
        let savedText = '', previousText = '', currentFormHash = '';

        function checkChanges() {
            document.dispatchEvent(new Event('check_changes_start.s2'));

            const currentText = eForm.elements[sTextareaName].value;

            if (previousText !== currentText) {
                Preview(eForm.elements['title'].value, eForm.elements[sTextareaName].value);
                previousText = currentText;

                if (savedText !== currentText) {
                    localStorage.setItem('s2_curr_text', currentText);
                } else {
                    localStorage.removeItem('s2_curr_text');
                }
            }
        }

        function show_recovered(sText) {
            PopupWindow(s2_lang.recovered_text_alert, s2_lang.recovered_text, s2_lang.recovered_text_info, sText);
        }

        const recoveredText = localStorage.getItem('s2_curr_text');
        setInterval(checkChanges, 5000);

        if (recoveredText) {
            PopupMessages.show(s2_lang.recovered_text_alert, [{
                name: s2_lang.recovered_open,
                action: function () {
                    show_recovered(recoveredText);
                }
            }]);
        }

        function getFormHash() {
            const formData = new FormData(eForm);
            const visibleFormData = new FormData();

            // Iterate over form elements and filter out hidden inputs
            for (let [key, value] of formData.entries()) {
                const inputElement = eForm.elements[key];
                if (inputElement.type !== 'hidden') {
                    visibleFormData.append(key, value);
                }
            }

            // Serialize visible form data
            const serializedData = Array.from(visibleFormData).map(function (pair) {
                return pair[0] + '=' + pair[1];
            }).join('&');

            return hex_md5(serializedData);
        }

        function handleChanges() {
            currentFormHash = getFormHash();

            localStorage.removeItem('s2_curr_text');
            savedText = eForm.elements[sTextareaName].value;
        }

        Preview(eForm.elements['title'].value, eForm.elements[sTextareaName].value);
        handleChanges();
        document.addEventListener('save_article_end.s2', handleChanges);

        return {
            present: function () {
                document.dispatchEvent(new Event('changes_present.s2'));

                return currentFormHash !== getFormHash();
            }
        };
    })();

    // Prevent from losing unsaved data
    window.onbeforeunload = function () {
        if (Changes.present()) {
            return s2_lang.unsaved_exit;
        }
    };
}

function initTagsAutocomplete(sInputId, aTagsList) {

    const tagsAutocomplete = new autoComplete(
        {
            selector: "#" + sInputId,
            data: {
                src: aTagsList,
                cache: true,
            },
            debounce: 100,
            query: (query) => {
                // Split query into array
                const querySplit = query.split(",");
                // Get last query value index
                const lastQuery = querySplit.length - 1;
                // Trim new query
                const newQuery = querySplit[lastQuery].trim();

                return newQuery;
            },
            events: {
                input: {
                    focus(event) {
                        tagsAutocomplete.start();
                    },
                    selection(event) {
                        const feedback = event.detail;
                        const input = document.getElementById(sInputId);
                        // Trim selected Value
                        const selection = feedback.selection.value.trim();
                        // Split query into array and trim each value
                        const query = input.value.split(",").map(item => item.trim());
                        // Remove last query
                        query.pop();
                        // Add selected value
                        query.push(selection);
                        // Replace Input value with the new query
                        input.value = query.join(", ") + ", ";
                    }
                }
            },
            threshold: 0,
            resultsList: {
                maxResults: undefined
            },
            resultItem: {
                highlight: true,
            }
        }
    )
}

function Preview(sTitle, sHtmlContent) {
    const d = window.frames['preview_frame'].document;
    let eHeader = d.getElementById('preview-header-wrapper');
    if (!eHeader) {
        const s = template
            .replaceAll('<!-- s2_text -->', '<div id="preview-text-wrapper"><script>\n' +
                'let observer = new MutationObserver(mutationRecords => {\n' +
                '  window.S2Latex && window.S2Latex.processTree(document.body);\n' +
                '});\n' +
                '\n' +
                'observer.observe(document.body, {\n' +
                '  childList: true,\n' +
                '  subtree: true\n' +
                '});\n' +
                '</script>')
            .replaceAll('<!-- s2_title -->', '<h1 id="preview-header-wrapper"></h1>')
        ;

        d.open();
        d.write(s);
        d.close();
    }

    let try_num = 30;
    var repeater = function () {
        const eText = d.getElementById('preview-text-wrapper');
        const eHeader = d.getElementById('preview-header-wrapper');

        if (try_num-- > 0 && !eText) {
            setTimeout(repeater, 30);
        } else {
            eHeader.textContent = sTitle;
            eText.innerHTML = sHtmlContent;
        }
    };
    repeater();
}

function htmlEncode(str) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
        '`': '&#96;'
    };

    return str.replace(/[&<>"'`]/g, function (match) {
        return map[match];
    });
}

function initHtmlToolbar(eToolbar) {
    eToolbar.addEventListener('click', function (e) {
        function insertParagraph(sType) {
            document.dispatchEvent(new CustomEvent('insert_paragraph.s2', {detail: {sType: sType}}));
        }

        function tagSelection(sTag) {
            return insertTag('<' + sTag + '>', '</' + sTag + '>');
        }

        function insertTag(sStart, sEnd) {
            document.dispatchEvent(new CustomEvent('insert_tag.s2', {detail: {sStart: sStart, sEnd: sEnd}}));
        }

        if (e.target.tagName === 'BUTTON') {
            const actions = {
                'b': () => tagSelection('strong'),
                'i': () => tagSelection('em'),
                'strike': () => tagSelection('s'),
                'big': () => tagSelection('big'),
                'small': () => tagSelection('small'),
                'sup': () => tagSelection('sup'),
                'sub': () => tagSelection('sub'),
                'nobr': () => tagSelection('nobr'),
                'a': () => insertTag('<a href="">', '</a>'),
                'img': () => GetImage(),
                'h2': () => insertParagraph('h2'),
                'h3': () => insertParagraph('h3'),
                'h4': () => insertParagraph('h4'),

                'left': () => insertParagraph(''),
                'center': () => insertParagraph('center'),
                'right': () => insertParagraph('right'),
                'justify': () => insertParagraph('justify'),

                'quote': () => insertParagraph('blockquote'),

                'ul': () => tagSelection('ul'),
                'ol': () => tagSelection('ol'),
                'li': () => tagSelection('li'),

                'pre': () => insertParagraph('pre'),
                'code': () => tagSelection('code'),

                'parag': () => s2_codemirror.smart(),

                'fullscreen': function () {
                    if (!document.fullscreenElement) {
                        document.getElementById('id-article-editor-block').requestFullscreen().catch((err) => {
                            console.log(
                                `Error attempting to enable fullscreen mode: ${err.message} (${err.name})`,
                            );
                        });
                    } else {
                        document.exitFullscreen();
                    }
                }
            };
            actions[e.target.className]();
        }
    });

}

function PopupWindow(sTitle, sHeader, sInfo, sText) {
    var wnd = window.open('about:blank', '', '');
    var color = getComputedStyle(document.body).getPropertyValue('background-color');
    var head = '<title>' + sTitle + '</title>' +
        '<style>html {height: 100%; margin: 0;} body {margin: 0 auto; padding: 9em 10% 1em; height: 100%; background: ' + color + '; font: 75% Verdana, sans-serif;} body, textarea {box-sizing: border-box;} h1 {margin: 0; padding: 0.5em 0 0;} textarea {width: 100%; height: 100%;} .text {position: absolute; top: 0; width: 80%;}</style>';
    var body = '<div class="text"><h1>' + sHeader + '</h1>' +
        '<p>' + sInfo + '</p></div><textarea readonly="readonly">' + htmlEncode(sText) + '</textarea>';

    wnd.document.open();
    wnd.document.write('<!DOCTYPE html><html><head>' + head + '</head><body>' + body + '</body></html>');
    wnd.document.close();
}

// NOTE: toolbar with html shortcuts for a raw textarea
// function TagSelection(eTextarea, sTag) {
//     return InsertTag(eTextarea, '<' + sTag + '>', '</' + sTag + '>');
// }
//
// function InsertTag(eTextarea, sOpenTag, sCloseTag, selection) {
//     var result = Hooks.run('fn_insert_tag_start', {openTag: sOpenTag, closeTag: sCloseTag});
//     if (result)
//         return;
//
//     if (selection == null)
//         selection = get_selection(eTextarea);
//
//     if (selection.text.substring(0, sOpenTag.length) == sOpenTag && selection.text.substring(selection.text.length - sCloseTag.length) == sCloseTag)
//         var replace_str = selection.text.substring(sOpenTag.length, selection.text.length - sCloseTag.length);
//     else
//         var replace_str = sOpenTag + selection.text + sCloseTag;
//
//     var start_pos = selection.start;
//     var end_pos = start_pos + replace_str.length;
//
//     if (eTextarea && typeof (eTextarea.scrollTop) != 'undefined')
//         var iScrollTop = eTextarea.scrollTop;
//
//     eTextarea.value = eTextarea.value.substring(0, start_pos) + replace_str + eTextarea.value.substring(selection.end);
//     set_selection(eTextarea, start_pos, end_pos);
//
//     // Buggy in Opera 11.61 build 1250
//     eTextarea.scrollTop = iScrollTop;
//
//     return false;
// }
//
//
// function get_selection(eItem) {
//     return {
//         start: eItem.selectionStart,
//         end: eItem.selectionEnd,
//         length: eItem.selectionEnd - eItem.selectionStart,
//         text: eItem.value.substring(eItem.selectionStart, eItem.selectionEnd)
//     };
// }
//
//
// function set_selection(e, start_pos, end_pos) {
//     e.focus();
//     e.selectionStart = start_pos;
//     e.selectionEnd = end_pos;
// }

//
// function InsertParagraph(eTextarea, sType) {
//     if (sType === 'h2' || sType === 'h3' || sType === 'h4' || sType === 'blockquote' || sType === 'pre')
//         var sOpenTag = '<' + sType + '>', sCloseTag = '</' + sType + '>';
//     else
//         var sOpenTag = '<p' + (sType ? ' align="' + sType + '"' : '') + '>', sCloseTag = '</p>';
//
//     var result = Hooks.run('fn_insert_paragraph_start', {openTag: sOpenTag, closeTag: sCloseTag});
//     if (result)
//         return result;
//
//     var selection = get_selection(eTextarea),
//         sText = eTextarea.value,
//         iScrollTop = eTextarea && eTextarea.scrollTop || 0;
//
//     if (selection.length) {
//         var replace_str = sOpenTag + selection.text + sCloseTag,
//             start_pos = selection.start,
//             end_pos = start_pos + replace_str.length;
//
//         eTextarea.value = sText.substring(0, start_pos) + replace_str + sText.substring(selection.end);
//         set_selection(eTextarea, start_pos, end_pos);
//     } else {
//         start_pos = sText.lastIndexOf('\r\n\r\n', selection.start - 1) + 1; // First char on the new line (incl. -1 + 1 = 0)
//         if (start_pos)
//             start_pos += 3;
//         else {
//             start_pos = sText.lastIndexOf('\n\n', selection.start - 1) + 1; // First char on the new line (incl. -1 + 1 = 0)
//             if (start_pos)
//                 start_pos++;
//         }
//
//         if (selection.start < start_pos) {
//             // Ignore empty line
//             set_selection(eTextarea, selection.start, selection.start);
//             return false;
//         }
//
//         end_pos = sText.indexOf('\r\n\r\n', selection.start);
//         if (end_pos == -1)
//             end_pos = sText.indexOf('\n\n', selection.start);
//         if (end_pos == -1)
//             end_pos = sText.length;
//
//         var sEnd = sText.substring(start_pos, end_pos),
//             old_length = sEnd.length,
//             start_len_diff = sEnd.replace(/(?:[ ]*<(?:p|blockquote|h[2-4])[^>]*>)?/, sOpenTag).length - old_length;
//
//         // Move cursor right if needed to put inside the tag
//         var new_cursor = Math.max(sOpenTag.length + start_pos, start_len_diff + selection.start);
//
//         sEnd = sEnd.replace(/(?:[ ]*<(?:p|blockquote|h[2-4])[^>]*>)?([\s\S]*?)(?:<\/(?:p|blockquote|h[2-4])>)?[ ]*$/, sOpenTag + '$1' + sCloseTag);
//
//         // Move cursor left if needed to put inside the tag
//         new_cursor = Math.min(end_pos + (sEnd.length - old_length) - sCloseTag.length, new_cursor);
//
//         eTextarea.value = sText.substring(0, start_pos) + sEnd + sText.substring(end_pos);
//
//         set_selection(eTextarea, new_cursor, new_cursor);
//     }
//
//     // Buggy in Opera 11.61 build 1250
//     eTextarea.scrollTop = iScrollTop;
//
//     return false;
// }

function SmartParagraphs(sText) {
    sText = sText.replace(/(\r\n|\r|\n)/g, '\n');
    var asParag = sText.split(/\n{2,}/); // split on empty lines

    for (var i = asParag.length; i--;) {
        // We are working with non-empty contents
        if (asParag[i].replace(/^\s+|\s+$/g, '') == '')
            continue;

        // rtrim
        asParag[i] = asParag[i].replace(/\s+$/gm, '');

        // Do not touch special tags
        if (/<\/?(?:pre|script|style|ol|ul|li|cut)[^>]*>/.test(asParag[i]))
            continue;

        // Put <br /> if there are no closing tag like </h2>

        // Remove old tag
        asParag[i] = asParag[i].replace(/<br \/>$/gm, '').
            // A hack. Otherwise, the next regex works twice.
            replace(/$/gm, '-').
            // Put new tag
            replace(/(<\/(?:blockquote|p|h[2-4])>)?-$/gm, function ($0, $1) {
                return $1 ? $1 : '<br />';
            }).
            // Remove unnecessary last tag
            replace(/(?:<br \/>)?$/g, '');

        // Put <p>...</p> tags
        if (!/<\/?(?:blockquote|h[2-4])[^>]*>/.test(asParag[i])) {
            if (!/<\/p>\s*$/.test(asParag[i]))
                asParag[i] = asParag[i].replace(/\s*$/g, '</p>');
            if (!/^\s*<p[^>]*>/.test(asParag[i]))
                asParag[i] = asParag[i].replace(/^\s*/g, '<p>');
        }
    }

    return asParag.join("\n\n");
}

function GetImage() {
    const dialog = document.getElementById('picture_dialog');
    dialog.showModal();
    loadPictureManager();
}

var loadPictureManager = (function () {
    var wnd = null;
    return function () {
        if (!wnd)
            wnd = window.open('pictman.php', 'picture_frame', '');
        wnd.focus();
        wnd.document.body.focus();
    };
}());

var isAltPressed = function () {
};

document.addEventListener('DOMContentLoaded', function () {
    var altPressed = false;

    document.addEventListener('keyup', function (e) {
        altPressed = e.altKey;
    });

    document.addEventListener('keydown', function (e) {
        altPressed = e.altKey;

        if (e.ctrlKey && !e.shiftKey && e.code === 'KeyS') {
            document.dispatchEvent(new Event('save_form.s2'));
            e.preventDefault();
        }
    });

    window.addEventListener('blur', function () {
        altPressed = false;
    });

    isAltPressed = function () {
        return altPressed;
    }
});

function uploadBlobToPictureDir(blob, name, extension, successCallback) {
    var formData = new FormData();

    var d = new Date();

    if (typeof name !== 'string') {
        name = d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + "-" + ('0' + d.getDate()).slice(-2)
            + "_" + ('0' + d.getHours()).slice(-2) + ('0' + d.getMinutes()).slice(-2) + '.' + extension;
    }

    formData.append('pictures[]', blob, name);
    formData.append('dir', '/' + d.getFullYear() + '/' + ('0' + (d.getMonth() + 1)).slice(-2));
    formData.append('ajax', '1');
    formData.append('create_dir', '1');
    formData.append('return_image_info', '1');

    fetch('ajax.php?action=upload', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(res => {
            if (res.success === true && typeof successCallback !== "undefined") {
                successCallback(res, res.image_info[0], res.image_info[1]);
            }
        })
        .catch(error => console.warn('Error:', error));
}

function optimizeAndUploadFile(file) {
    var blobs = {};

    loadingIndicator(true);

    /**
     * Experiments show that now in Chrome file.type is 'image/png' no matter how the image is pasted.
     * However, I prefer to write a general algorithm.
     */
    if (file.type === 'image/png') {
        runOptipng(file, function (optimizedBlob) {
            blobs.png = optimizedBlob;
            compareBlobs();
        });
    } else {
        imageConversion.compress(file, {
            quality: 0.9,
            type: 'image/png'
        }).then(function (pngBlob) {
            // TODO OptiPNG is also required here. But as I pointed above, for now it's a dead brunch. Let's do it later.
            blobs.png = pngBlob;
            compareBlobs();
        });
    }

    if (file.type === 'image/jpg' || file.type === 'image/jpeg') {
        blobs.jpeg = file;
    } else {
        imageConversion.compress(file, {
            quality: 0.9,
            type: 'image/jpeg'
        }).then(function (jpegBlob) {
            blobs.jpeg = jpegBlob;
            compareBlobs();
        });
    }

    function compareBlobs() {
        if (blobs.png && blobs.jpeg) {
            var successCallback = function (res, w, h) {
                ReturnImage(res.file_path, w || 'auto', h || 'auto');
                loadingIndicator(false);
            };
            if (blobs.png.size > blobs.jpeg.size) {
                // JPEG is smaller, nevertheless we keep the PNG as a losless copy but suggest to use JPEG
                uploadBlobToPictureDir(blobs.png, null, 'png');
                uploadBlobToPictureDir(blobs.jpeg, null, 'jpg', successCallback);
            } else {
                // JPEG is larger, just forget about it
                uploadBlobToPictureDir(blobs.png, null, 'png', successCallback);
            }
        }
    }
}
