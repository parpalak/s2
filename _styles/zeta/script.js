(function () {
    // Saving comments to localstorage
    function initStorage() {
        var frm = document.forms['post_comment'];
        frm = frm && frm.elements;

        function save() {
            var text = frm['text'].value,
                id = 'comment_text_' + frm['id'].value;

            if (text) {
                localStorage.setItem(id, text);
            } else {
                localStorage.removeItem(id);
            }
            localStorage.setItem('comment_name', frm['name'].value);
            localStorage.setItem('comment_email', frm['email'].value);
            localStorage.setItem('comment_showemail', frm['show_email'].checked + 0);
        }

        try {
            if (!('localStorage' in window) || window['localStorage'] === null) {
                return;
            }

            if (document.cookie.indexOf('comment_form_sent=') != -1) {
                var id = document.cookie.replace(/(?:(?:^|.*;\s*)comment_form_sent\s*\=\s*([^;]*).*$)|^.*$/, "$1");
                document.cookie = 'comment_form_sent=0; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=/';
                localStorage.removeItem('comment_text_' + id);
            } else {
                frm['text'].value = frm['text'].value || localStorage.getItem('comment_text_' + frm['id'].value) || '';
            }

            if (!frm) {
                return;
            }

            frm['name'].addEventListener('change', save, false);
            frm['email'].addEventListener('change', save, false);
            frm['show_email'].addEventListener('change', save, false);
            frm['text'].addEventListener('change', save, false);
            document.forms['post_comment'].addEventListener('submit', save, false);

            frm['name'].value = frm['name'].value || localStorage.getItem('comment_name');
            frm['email'].value = frm['email'].value || localStorage.getItem('comment_email');
            frm['show_email'].checked = frm['show_email'].checked || !!(localStorage.getItem('comment_showemail') - 0);

            save();
            setInterval(save, 5000);
        } catch (e) {
        }
    }

    // Ctrl + arrows navigation
    var inpFocused = false, upLink, nextLink, prevLink;

    function focus() {
        inpFocused = true;
    }

    function blur() {
        inpFocused = false;
    }

    function navigateThrough(e) {
        if (!(e.ctrlKey || e.metaKey) || inpFocused) {
            return;
        }

        var link = false;
        switch (e.code || e.keyCode || e.which) {
            case 'ArrowRight':
            case 0x27:
                link = nextLink;
                break;
            case 'ArrowLeft':
            case 0x25:
                link = prevLink;
                break;
            case 'ArrowUp':
            case 0x26:
                link = upLink;
                break;
        }

        if (link) {
            document.location = link;
        }
    }

    function initNavigate() {
        if (!document.getElementsByTagName) {
            return;
        }

        var e, i, ae = document.getElementsByTagName('LINK');
        for (i = ae.length; i--;) {
            e = ae[i];
            if (e.rel === 'next') {
                nextLink = e.href;
            } else if (e.rel === 'prev') {
                prevLink = e.href;
            } else if (e.rel === 'up') {
                upLink = e.href;
            }
        }

        document.addEventListener('keydown', navigateThrough, true);

        ae = document.getElementsByTagName('INPUT');
        for (i = ae.length; i--;) {
            e = ae[i];
            if (e.type === 'text' || e.type === 'password' || e.type === 'search') {
                e.addEventListener('focus', focus, true);
                e.addEventListener('blur', blur, true);
            }
        }

        ae = document.getElementsByTagName('TEXTAREA');
        for (i = ae.length; i--;) {
            e = ae[i];
            e.addEventListener('focus', focus, true);
            e.addEventListener('blur', blur, true);
        }
    }

    var started = false;

    function Init() {
        if (started) {
            return;
        }

        started = true;

        initNavigate();
        initStorage();
    }

    document.addEventListener('DOMContentLoaded', Init, false);
    addEventListener('load', Init, true);
})();
