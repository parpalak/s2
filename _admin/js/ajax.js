/**
 * Basic functions: ajax, md5, popup messages.
 *
 * @copyright 2007-2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

function str_replace(from, to, str) {
    to = to.replace(/\$/g, '$$$$');
    while (str.indexOf(from) >= 0) {
        str = str.replace(from, to);
    }
    return str;
}


var SetBackground = (function () {
    var _size = 150, maxAlpha = 5.5, maxLine = 4;

    function noise() {
        if (!document.createElement('canvas').getContext)
            return '';

        var canvas = document.createElement('canvas');
        canvas.width = canvas.height = _size;

        var ctx = canvas.getContext('2d'),
            img = ctx.createImageData(_size, _size),
            repeat_num = 0, alpha = 1, x, y, idx;

        for (y = _size; y--;)
            for (x = _size, idx = (x + y * _size) * 4; x--;) {
                if (Math.random() * maxLine < repeat_num++)
                    alpha = (repeat_num = 0) + ~~(Math.random() * maxAlpha);
                idx -= 4;
                img.data[idx] = img.data[idx + 1] = img.data[idx + 2] = 0;
                img.data[idx + 3] = alpha;
            }

        ctx.putImageData(img, 0, 0);

        return 'url(' + canvas.toDataURL('image/png') + ')';
    }

    var back_img = noise(),
        head = document.getElementsByTagName('head')[0],
        style = document.createElement('style');

    style.type = 'text/css';
    head.appendChild(style);

    return function (c) {
        var css_rule = 'body {background: ' + back_img + ' ' + c + '; background-attachment: local; background-size: ' + _size * 8 + 'px ' + _size + 'px;}';

        if (style.firstChild) {
            style.removeChild(style.firstChild);
        }
        style.appendChild(document.createTextNode(css_rule));
    };
}());

//
// Ajax wrappers
//

function checkAjaxStatus(XHR) {
    XHR.s2ErrorFlag = true;

    if (XHR.status === 401) {
        const data = JSON.parse(XHR.responseText);
        if (data && data.message) {
            PopupMessages.show(data.message, null, null, 'login');
        } else {
            DisplayError(XHR.responseText);
        }
        return false;
    }

    if (XHR.status === 403) {
        const data = JSON.parse(XHR.responseText);
        if (data && data.message) {
            PopupMessages.show(data.message);
        } else if (data.errors) {
            Array.from(data.errors).forEach(function (error) {
                // TODO array_merge
                PopupMessages.show(error);
            });
        } else {
            DisplayError(XHR.responseText);
        }
        return false;
    }

    if (XHR.status !== 200) {
        UnknownError(XHR.responseText, XHR.status);
        return false;
    }

    XHR.s2ErrorFlag = false;
    return true;
}

function UnknownError(sError, iStatus) {
    if (sError.indexOf('</body>') === -1 || sError.indexOf('</html>') === -1) {
        sError = s2_lang.unknown_error + ' ' + iStatus + '<br />' +
            s2_lang.server_response + '<br />' + sError;
    }

    DisplayError(sError);
}
