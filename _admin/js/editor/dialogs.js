/**
 * Dialog helpers for editor actions in S2.
 *
 * @copyright 2007-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

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

function PopupWindow(sTitle, sHeader, sInfo, sText) {
    const wnd = window.open('about:blank', '', '');
    const color = getComputedStyle(document.body).getPropertyValue('background-color');
    const head = '<title>' + sTitle + '</title>' +
        '<style>html {height: 100%; margin: 0;} body {margin: 0 auto; padding: 9em 10% 1em; height: 100%; background: ' + color + '; font: 75% Verdana, sans-serif;} body, textarea {box-sizing: border-box;} h1 {margin: 0; padding: 0.5em 0 0;} textarea {width: 100%; height: 100%;} .text {position: absolute; top: 0; width: 80%;}</style>';
    const body = '<div class="text"><h1>' + sHeader + '</h1>' +
        '<p>' + sInfo + '</p></div><textarea readonly="readonly">' + htmlEncode(sText) + '</textarea>';

    wnd.document.open();
    wnd.document.write('<!DOCTYPE html><html><head>' + head + '</head><body>' + body + '</body></html>');
    wnd.document.close();
}

const loadPictureManager = (function () {
    let wnd = null;
    return function () {
        if (!wnd) {
            wnd = window.open('pictman.php', 'picture_frame', '');
        }
        wnd.focus();
        wnd.document.body.focus();
    };
}());

function GetImage() {
    const dialog = document.getElementById('picture_dialog');
    if (!dialog) {
        return;
    }
    dialog.showModal();
    loadPictureManager();
}

function ReturnImage(s, w, h) {
    document.dispatchEvent(new CustomEvent('return_image.s2', {detail: {file_path: s, width: w, height: h}}));
}

function ClosePictureDialog() {
    const dialog = document.getElementById('picture_dialog');
    if (dialog) {
        dialog.close();
    }
}

function showErrorDialog(sError) {
    if (typeof window.DisplayError === 'function') {
        window.DisplayError(sError);
    }
}

export {
    PopupWindow,
    GetImage,
    ReturnImage,
    ClosePictureDialog,
    showErrorDialog
};
