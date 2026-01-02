/**
 * Editor toolbar and keyboard shortcuts for S2.
 *
 * @copyright 2007-2026 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

import {GetImage} from './dialogs.js';
import {s2_codemirror} from './codemirror.js';

export function initHtmlTextarea(eTextarea) {
    if (!eTextarea) {
        return;
    }
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
    });

    // Use parentNode to catch events from CodeMirror.
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

export function initHtmlToolbar(eToolbar) {
    if (!eToolbar) {
        return;
    }
    function insertParagraph(sType) {
        document.dispatchEvent(new CustomEvent('insert_paragraph.s2', {detail: {sType: sType}}));
    }

    function insertTag(sStart, sEnd) {
        document.dispatchEvent(new CustomEvent('insert_tag.s2', {detail: {sStart: sStart, sEnd: sEnd}}));
    }

    function tagSelection(sTag) {
        return insertTag('<' + sTag + '>', '</' + sTag + '>');
    }

    eToolbar.addEventListener('click', function (e) {
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
                            console.warn(
                                `Error attempting to enable fullscreen mode: ${err.message} (${err.name})`
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

document.addEventListener('DOMContentLoaded', function () {
    document.addEventListener('keydown', function (e) {
        if (e.ctrlKey && !e.shiftKey && e.code === 'KeyS') {
            document.dispatchEvent(new Event('save_form.s2'));
            e.preventDefault();
        }
    });
});
