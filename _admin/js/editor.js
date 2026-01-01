/**
 * Form for editing pages in S2.
 *
 * @copyright 2007-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
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

function initArticleEditForm(eForm, statusData, sEntityName, sTextareaName, sTemplateId) {
    const sLowerEntityName = sEntityName.toLowerCase();

    function decorateForm(statusData) {
        const urlWrapper = eForm.querySelector('.field-url');
        const urlLabel = eForm.querySelector('label[for="id-url"]');
        urlWrapper.setAttribute('data-url-status', statusData['urlStatus']);
        urlWrapper.title = statusData['urlTitle'];
        urlLabel.title = statusData['urlTitle'];
        if (statusData['urlStatus'] === 'mainpage') {
            urlWrapper.querySelector('input').setAttribute('disabled', 'disabled');
        }

        const isPublished = eForm.elements['published'].checked;
        eForm.querySelector('.field-published').setAttribute('data-published-status', isPublished ? '1' : '0');

        const ePreviewLink = eForm.querySelector('#preview_link');
        ePreviewLink.href = statusData['url'];
        ePreviewLink.style.display = isPublished ? 'inline' : 'none';
    }

    decorateForm(statusData);
    initPreviewSync(eForm, sTextareaName);

    async function saveForm(event) {
        event.preventDefault();

        document.dispatchEvent(new Event('save_article_start.s2'));

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
                const absoluteUrl = new URL(eForm.action);
                const id = absoluteUrl.searchParams.get('id');
                Preview(eForm.elements['title'].value, eForm.elements[sTextareaName].value, id, sTemplateId || eForm.elements['template'].value);
                previousText = currentText;

                if (savedText !== currentText) {
                    localStorage.setItem('s2_curr_text', currentText);
                } else {
                    localStorage.removeItem('s2_curr_text');
                }
            }
        }

        function wireLivePreview() {
            const cm = s2_codemirror.get_current && s2_codemirror.get_current();
            if (!cm || !cm.on) {
                return;
            }

            const updatePreview = debounceWithMaxWait(function () {
                s2_codemirror.flip();
                checkChanges();
            }, 300, 3000);

            cm.on('change', updatePreview);
        }

        function show_recovered(sText) {
            PopupWindow(s2_lang.recovered_text_alert, s2_lang.recovered_text, s2_lang.recovered_text_info, sText);
        }

        const recoveredText = localStorage.getItem('s2_curr_text');
        setInterval(checkChanges, 5000);
        wireLivePreview();

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

        const absoluteUrl = new URL(eForm.action);
        const id = absoluteUrl.searchParams.get('id');
        Preview(eForm.elements['title'].value, eForm.elements[sTextareaName].value, id, sTemplateId || eForm.elements['template'].value);
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

function debounceWithMaxWait(fn, wait, maxWait) {
    let timerId = null;
    let lastInvoke = 0;

    return function () {
        const now = Date.now();
        const elapsed = now - lastInvoke;

        if (maxWait && elapsed >= maxWait) {
            lastInvoke = now;
            if (timerId) {
                clearTimeout(timerId);
                timerId = null;
            }
            fn();
            return;
        }

        if (timerId) {
            clearTimeout(timerId);
        }

        timerId = setTimeout(function () {
            lastInvoke = Date.now();
            timerId = null;
            fn();
        }, wait);
    };
}

function countNewlines(str) {
    let count = 0;
    let pos = str.indexOf('\n');
    while (pos !== -1) {
        count++;
        pos = str.indexOf('\n', pos + 1);
    }
    return count;
}

function collectBlockLineNumbers(html) {
    const blockTags = new Set(['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'ul', 'ol', 'li', 'table', 'tr', 'td', 'th', 'div', 'section', 'article', 'header', 'footer']);
    const voidTags = new Set(['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr']);
    const lines = [];
    let line = 0;
    let pos = 0;
    let depth = 0;

    while (pos < html.length) {
        const lt = html.indexOf('<', pos);
        if (lt === -1) {
            line += countNewlines(html.slice(pos));
            break;
        }

        line += countNewlines(html.slice(pos, lt));

        if (html.startsWith('<!--', lt)) {
            const endComment = html.indexOf('-->', lt + 4);
            if (endComment === -1) {
                break;
            }
            line += countNewlines(html.slice(lt, endComment + 3));
            pos = endComment + 3;
            continue;
        }

        const gt = html.indexOf('>', lt + 1);
        if (gt === -1) {
            break;
        }

        const raw = html.slice(lt + 1, gt).trim();
        if (!raw) {
            pos = gt + 1;
            continue;
        }

        const isClosing = raw[0] === '/';
        const nameToken = isClosing ? raw.slice(1) : raw;
        const tagName = nameToken.split(/\s+/)[0].toLowerCase();
        const isSelfClosing = !isClosing && (raw.endsWith('/') || voidTags.has(tagName));

        if (!isClosing) {
            if (depth === 0 && blockTags.has(tagName)) {
                lines.push(line);
            }
            if (!isSelfClosing) {
                depth++;
            }
        } else if (depth > 0) {
            depth--;
        }

        pos = gt + 1;
    }

    return lines;
}

function applyLineMarkers(doc, wrapper, html) {
    if (!doc || !wrapper) {
        return;
    }

    const lineNumbers = collectBlockLineNumbers(html);
    const existing = wrapper.querySelectorAll('[data-line]');
    existing.forEach(function (node) {
        node.removeAttribute('data-line');
        node.classList && node.classList.remove('line');
    });

    if (!lineNumbers.length) {
        return;
    }

    const blockTags = new Set(['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'BLOCKQUOTE', 'PRE', 'UL', 'OL', 'LI', 'TABLE', 'TR', 'TD', 'TH', 'DIV', 'SECTION', 'ARTICLE', 'HEADER', 'FOOTER']);
    const blocks = Array.from(wrapper.children).filter(function (node) {
        return blockTags.has(node.tagName);
    });

    const total = Math.min(blocks.length, lineNumbers.length);
    for (let i = 0; i < total; i++) {
        blocks[i].setAttribute('data-line', String(lineNumbers[i]));
        blocks[i].classList && blocks[i].classList.add('line');
    }
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function renderPreviewError(doc, message) {
    if (!doc) {
        return;
    }
    const html = '<!doctype html>' +
        '<html><head><meta charset="utf-8">' +
        '<style>' +
        'body{margin:0 0 0 1em;padding:0;color:#000;font:16px/1.4 system-ui,-apple-system,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;}' +
        '.s2-preview-error{border:1px solid #aaa; border-radius: 1px; background:#fffffb;padding:12px 16px;}' +
        '</style></head><body>' +
        '<div class="s2-preview-error">' + escapeHtml(message) + '</div>' +
        '</body></html>';
    doc.open();
    doc.write(html);
    doc.close();
}

const Preview = (function () {
    let lastTemplateId = null;
    let template = '';

    return async function (sTitle, sHtmlContent, iArticleId, sTemplateId) {
        const d = window.frames['preview_frame'].document;
        let eHeader, eText;

        if (sTemplateId !== lastTemplateId) {
            let response;
            try {
                response = await fetch(sUrl + 'action=load_template&template_id=' + encodeURIComponent(sTemplateId) + '&article_id=' + encodeURIComponent(iArticleId));
            } catch (error) {
                console.warn('Failed to load template preview:', error);
                renderPreviewError(d, s2_lang.unknown_error);
                return;
            }
            if (!response.ok) {
                console.warn('Failed to load template preview:', response.status);
                renderPreviewError(d, s2_lang.unknown_error);
                return;
            }
            const data = await response.json();
            if (!data || data.success !== true || !data.template) {
                console.warn('Template preview is unavailable:', data && data.preview_message ? data.preview_message : 'Unknown error');
                renderPreviewError(d, (data && data.preview_message) ? data.preview_message : s2_lang.unknown_error);
                return;
            }
            template = data.template;
            lastTemplateId = sTemplateId;
        } else {
            eHeader = d.getElementById('preview-header-wrapper');
            eText = d.getElementById('preview-text-wrapper');
        }

        if (!eHeader && !eText) {
            const s = template
                .replaceAll('<!-- s2_text -->', '<div id="preview-text-wrapper" data-template-name=""></div>')
                .replaceAll('<!-- s2_title -->', '<h1 id="preview-header-wrapper"></h1>');

            d.open();
            d.write(s);
            d.close();
        }

        let try_num = 30;
        let repeater = function () {
            const eText = d.getElementById('preview-text-wrapper');
            const eHeader = d.getElementById('preview-header-wrapper');

            if (try_num-- > 0 && !eText && !eHeader) {
                setTimeout(repeater, 30);
            } else {
                if (eHeader) {
                    eHeader.textContent = sTitle;
                }
                if (eText) {
                    const wrapper = d.createElement('div');
                    wrapper.innerHTML = sHtmlContent;

                    if (window.morphdom) {
                        window.morphdom(eText, wrapper, {childrenOnly: true});
                    } else {
                        eText.innerHTML = sHtmlContent;
                    }

                    applyLineMarkers(d, eText, sHtmlContent);

                    document.dispatchEvent(new CustomEvent('preview_updated.s2', {
                        detail: {
                            document: d,
                            wrapper: eText,
                            html: sHtmlContent
                        }
                    }));
                }
            }
        };
        repeater();
    }
})();

function getScrollElement(doc) {
    const candidates = [];
    if (doc.scrollingElement) {
        candidates.push(doc.scrollingElement);
    }
    if (doc.documentElement && doc.documentElement !== doc.scrollingElement) {
        candidates.push(doc.documentElement);
    }
    if (doc.body && doc.body !== doc.scrollingElement && doc.body !== doc.documentElement) {
        candidates.push(doc.body);
    }

    if (!candidates.length) {
        return doc.scrollingElement || doc.documentElement || doc.body;
    }

    let best = candidates[0];
    let bestScrollSpace = (best.scrollHeight || 0) - (best.clientHeight || 0);
    for (let i = 1; i < candidates.length; i += 1) {
        const candidate = candidates[i];
        const scrollSpace = (candidate.scrollHeight || 0) - (candidate.clientHeight || 0);
        if (scrollSpace > bestScrollSpace) {
            best = candidate;
            bestScrollSpace = scrollSpace;
        }
    }

    return best;
}

function getNodeScrollTop(node, scrollElement) {
    let offset = 0;
    let current = node;

    while (current) {
        if (typeof current.offsetTop === 'number') {
            offset += current.offsetTop;
        }
        current = current.offsetParent;
    }

    return offset;
}

function initPreviewSync(eForm, sTextareaName) {
    const eTextarea = eForm.elements[sTextareaName];
    if (!eTextarea) {
        return null;
    }

    const previewFrame = document.getElementById(eTextarea.id + '-preview-frame');
    if (!previewFrame) {
        return null;
    }

    const scrollMap = new ScrollMap(function () {
        const cm = s2_codemirror.get_current && s2_codemirror.get_current();
        const doc = previewFrame.contentDocument;
        if (!cm || !doc) {
            return [[0], [0]];
        }

        const scrollElement = getScrollElement(doc);
        const resultNodes = doc.querySelectorAll('#preview-text-wrapper .line[data-line]');
        const mapSrc = [0];
        const mapResult = [0];
        const mapLines = [0];
        const seen = new Set();

        if (!resultNodes.length) {
            const srcScroller = cm.getScrollerElement();
            mapSrc.push(srcScroller.scrollHeight);
            mapResult.push(scrollElement.scrollHeight);
            mapLines.push(0);
            return [mapSrc, mapResult, mapLines];
        }

        resultNodes.forEach(function (node) {
            const line = parseInt(node.getAttribute('data-line'), 10);
            if (Number.isNaN(line) || seen.has(line)) {
                return;
            }
            seen.add(line);

            const safeLine = Math.max(0, Math.min(line, cm.lineCount() - 1));
            const srcTop = cm.heightAtLine
                ? cm.heightAtLine(safeLine, 'local')
                : cm.charCoords({line: safeLine, ch: 0}, 'local').top;
            const resultTop = getNodeScrollTop(node, scrollElement);

            mapSrc.push(Math.round(srcTop));
            mapResult.push(Math.round(resultTop));
            mapLines.push(safeLine);
        });

        const srcScroller = cm.getScrollerElement();
        const srcScrollHeight = srcScroller.scrollHeight;
        const lastSrcElemPos = mapSrc[mapSrc.length - 1];
        const allowedHeight = 5;

        const lastLine = mapLines[mapLines.length - 1] || 0;
        mapSrc.push(srcScrollHeight - allowedHeight > lastSrcElemPos ? srcScrollHeight - allowedHeight : lastSrcElemPos);
        mapResult.push(scrollElement.scrollHeight);
        mapLines.push(lastLine);

        return [mapSrc, mapResult, mapLines];
    });

    let syncScroll = null;
    let layoutResetRaf = null;
    const observedDocs = new WeakSet();
    const observedTextWrappers = new WeakSet();
    const observedInputTargets = new WeakSet();

    function scheduleLayoutReset() {
        if (layoutResetRaf) {
            cancelAnimationFrame(layoutResetRaf);
        }
        layoutResetRaf = requestAnimationFrame(function () {
            layoutResetRaf = null;
            scrollMap.reset();
            if (syncScroll) {
                syncScroll.scrollToBottomIfRequired();
            }
        });
    }

    function bindPreviewLayoutObservers(doc) {
        if (!doc || observedDocs.has(doc)) {
            return;
        }
        observedDocs.add(doc);

        doc.addEventListener('load', function (event) {
            const target = event.target;
            if (target && target.tagName === 'IMG') {
                scheduleLayoutReset();
            }
        }, true);

        doc.addEventListener('error', function (event) {
            const target = event.target;
            if (target && target.tagName === 'IMG') {
                scheduleLayoutReset();
            }
        }, true);

        const textWrapper = doc.getElementById('preview-text-wrapper');
        if (textWrapper && !observedTextWrappers.has(textWrapper)) {
            observedTextWrappers.add(textWrapper);
            const ViewResizeObserver = doc.defaultView && doc.defaultView.ResizeObserver;
            if (ViewResizeObserver) {
                const resizeObserver = new ViewResizeObserver(function () {
                    scheduleLayoutReset();
                });
                resizeObserver.observe(textWrapper);
            }
        }
    }

    function ensureSync() {
        if (syncScroll) {
            return;
        }

        const cm = s2_codemirror.get_current && s2_codemirror.get_current();
        const doc = previewFrame.contentDocument;
        if (!cm || !doc) {
            return;
        }

        const scrollElement = getScrollElement(doc);
        const srcScroller = cm.getScrollerElement();

        const previewScrollTargets = [
            scrollElement,
            doc,
            doc.defaultView,
            doc.documentElement,
            doc.body
        ].filter(Boolean);

        syncScroll = new SyncScroll(
            scrollMap,
            new Animator(function () {
                return cm.getScrollInfo().top;
            }, function (y) {
                cm.scrollTo(null, y);
            }),
            new Animator(function () {
                return scrollElement.scrollTop;
            }, function (y) {
                scrollElement.scrollTop = y;
            }),
            srcScroller,
            scrollElement,
            previewScrollTargets
        );

        srcScroller.addEventListener('wheel', syncScroll.switchScrollToSrc, {passive: true});
        srcScroller.addEventListener('mousedown', syncScroll.switchScrollToSrc);
        srcScroller.addEventListener('touchstart', syncScroll.switchScrollToSrc, {passive: true});

        scrollElement.addEventListener('wheel', syncScroll.switchScrollToResult, {passive: true});
        scrollElement.addEventListener('mousedown', syncScroll.switchScrollToResult);
        scrollElement.addEventListener('touchstart', syncScroll.switchScrollToResult, {passive: true});

        syncScroll.switchScrollToSrc();
    }

    function bindPreviewInputTargets(doc) {
        if (!doc || !syncScroll) {
            return;
        }

        const targets = [
            doc,
            doc.defaultView,
            doc.documentElement,
            doc.body,
            previewFrame
        ].filter(Boolean);

        targets.forEach(function (target) {
            if (observedInputTargets.has(target)) {
                return;
            }
            observedInputTargets.add(target);

            target.addEventListener('wheel', syncScroll.switchScrollToResult, {passive: true, capture: true});
            target.addEventListener('mousedown', syncScroll.switchScrollToResult, {capture: true});
            target.addEventListener('touchstart', syncScroll.switchScrollToResult, {passive: true, capture: true});
        });
    }

    function handlePreviewUpdated(event) {
        if (!event.detail || event.detail.document !== previewFrame.contentDocument) {
            return;
        }

        ensureSync();
        bindPreviewLayoutObservers(event.detail.document);
        bindPreviewInputTargets(event.detail.document);
        scheduleLayoutReset();

        if (syncScroll) {
            syncScroll.scrollToBottomIfRequired();
        }
    }

    document.addEventListener('preview_updated.s2', handlePreviewUpdated);
    document.addEventListener('preview_layout_changed.s2', function () {
        scheduleLayoutReset();
    });
    window.addEventListener('resize', function () {
        scheduleLayoutReset();
    });
    previewFrame.addEventListener('load', function () {
        if (!previewFrame.contentDocument) {
            return;
        }
        bindPreviewLayoutObservers(previewFrame.contentDocument);
        scheduleLayoutReset();
    });

    return {
        reset: function () {
            scrollMap.reset();
        }
    };
}

/**
 * Realistic animation module based on one-dimensional physical model.
 *
 * @param positionGetter
 * @param positionSetter
 * @constructor
 */
function Animator(positionGetter, positionSetter) {
    let x = 0,
        x1 = 0,
        x2 = 0,
        v = 0,
        animationTime = 200,
        timerId,
        startedAt = null;

    const loop = function (timestamp) {
        if (startedAt === null) {
            startedAt = timestamp;
        }

        const moveTime = timestamp - startedAt;

        if (moveTime < moveDuration) {
            x = x2 + A * (Math.cos(omega * (moveTime - moveDuration)) - 1);
            v = A * omega * (Math.sin(omega * (moveDuration - moveTime)));

            positionSetter(x);

            timerId = (window.requestAnimationFrame || window.webkitRequestAnimationFrame || window.mozRequestAnimationFrame || function (cb) {
                return window.setTimeout(function () {
                    cb(Date.now());
                }, 16);
            })(loop);

            if (isReInit) {
                initMotion(reInitPosition, x);
                isReInit = false;
                startedAt = timestamp;
            }
        } else {
            startedAt = null;

            v = 0;
            positionSetter(x2);
            cancelAnimationFrame(timerId);

            if (isReInit) {
                isReInit = false;
            }
        }
    };

    let moveDuration;
    let A, omega;
    let isReInit = false;
    let reInitPosition;

    function initMotion(newPosition, oldPosition) {
        let k;
        x2 = newPosition;
        x1 = oldPosition;

        if (Math.abs(v) < 0.00001) {
            k = Math.PI;
            moveDuration = animationTime;
        } else {
            const alpha = (x2 - x1) / v / animationTime;
            if (alpha < 0 || alpha > 0.5) {
                k = Math.PI * Math.sqrt(1 - 0.5 / alpha);
            } else {
                k = 0.1;
            }

            const alpha1 = (1 - Math.cos(k)) / k / Math.sin(k);
            moveDuration = (x2 - x1) / alpha1 / v;
        }

        omega = k / moveDuration;
        A = (x2 - x1) / (1 - Math.cos(k));
    }

    this.setPos = function (nextPos) {
        isReInit = (startedAt !== null);
        if (!isReInit) {
            x = positionGetter();
            initMotion(nextPos, x);
            timerId = (window.requestAnimationFrame || window.webkitRequestAnimationFrame || window.mozRequestAnimationFrame || function (cb) {
                return window.setTimeout(function () {
                    cb(Date.now());
                }, 16);
            })(loop);
        } else {
            reInitPosition = nextPos;
        }
    };

    this.stop = function () {
        startedAt = null;
        v = 0;
        cancelAnimationFrame(timerId);
        isReInit = false;
    };
}

/**
 * Find the index of a maximum value in values array
 * which is less than maxValue.
 *
 * @param maxValue
 * @param values
 *
 * @returns {object}
 */
function findBisect(maxValue, values) {
    let a = 0;
    let b = values.length - 1;
    let fA = values[a];

    if (fA >= maxValue) {
        return {val: a, part: 0};
    }

    let fB = values[b];
    if (fB < maxValue) {
        return {val: b, part: 0};
    }

    while (b - a > 1) {
        const c = a + Math.round((b - a) / 2);
        const fC = values[c];

        if (fC >= maxValue) {
            b = c;
            fB = fC;
        } else {
            a = c;
            fA = fC;
        }
    }

    return {val: a, part: (maxValue - fA) / (fB - fA)};
}

/**
 * Access to the map between blocks in sync scroll.
 *
 * @param mapBuilder
 * @constructor
 */
function ScrollMap(mapBuilder) {
    let map = [null, null, null];

    this.reset = function () {
        map = [null, null, null];
    };

    this.getPosition = function (eBlockNode, fromIndex, toIndex) {
        const offsetHeight = eBlockNode.clientHeight || eBlockNode.offsetHeight;
        const scrollTop = eBlockNode.scrollTop;

        if (scrollTop === 0) {
            return 0;
        }

        if (map[fromIndex] === null) {
            map = mapBuilder();
        }

        const maxMapIndex = map[fromIndex].length - 1;
        if (map[fromIndex][maxMapIndex] <= scrollTop + offsetHeight) {
            return map[toIndex][maxMapIndex] - offsetHeight;
        }

        const scrollShift = offsetHeight / 2;
        const scrollLevel = scrollTop + scrollShift;
        const blockIndex = findBisect(scrollLevel, map[fromIndex]);
        let srcScrollLevel = parseFloat(map[toIndex][blockIndex.val] * (1 - blockIndex.part));

        if (map[toIndex][blockIndex.val + 1]) {
            srcScrollLevel += parseFloat(map[toIndex][blockIndex.val + 1] * blockIndex.part);
        }

        return srcScrollLevel - scrollShift;
    };

    this.getAlignedInfo = function (eBlockNode, fromIndex, toIndex) {
        if (map[fromIndex] === null) {
            map = mapBuilder();
        }

        if (!map[2] || !map[2].length) {
            return null;
        }

        const offsetHeight = eBlockNode.clientHeight || eBlockNode.offsetHeight;
        const scrollTop = eBlockNode.scrollTop;

        if (scrollTop === 0) {
            return {
                line: map[2][0] || 0,
                srcTop: map[0][0] || 0,
                resultTop: map[1][0] || 0
            };
        }

        const maxMapIndex = map[fromIndex].length - 1;
        if (map[fromIndex][maxMapIndex] <= scrollTop + offsetHeight) {
            return {
                line: map[2][maxMapIndex] || map[2][map[2].length - 1] || 0,
                srcTop: map[0][maxMapIndex] || map[0][map[0].length - 1] || 0,
                resultTop: map[1][maxMapIndex] || map[1][map[1].length - 1] || 0
            };
        }

        const scrollShift = offsetHeight / 2;
        const scrollLevel = scrollTop + scrollShift;
        const blockIndex = findBisect(scrollLevel, map[fromIndex]);
        const lineA = map[2][blockIndex.val];
        const lineB = map[2][blockIndex.val + 1];
        const srcA = map[toIndex][blockIndex.val];
        const srcB = map[toIndex][blockIndex.val + 1];
        const otherIndex = toIndex === 0 ? 1 : 0;
        const otherA = map[otherIndex][blockIndex.val];
        const otherB = map[otherIndex][blockIndex.val + 1];

        return {
            line: Number.isFinite(lineA) && Number.isFinite(lineB)
                ? Math.round(lineA + (lineB - lineA) * blockIndex.part)
                : (Number.isFinite(lineA) ? lineA : null),
            srcTop: Number.isFinite(srcA) && Number.isFinite(srcB)
                ? Math.round(srcA + (srcB - srcA) * blockIndex.part)
                : (Number.isFinite(srcA) ? srcA : null),
            resultTop: Number.isFinite(otherA) && Number.isFinite(otherB)
                ? Math.round(otherA + (otherB - otherA) * blockIndex.part)
                : (Number.isFinite(otherA) ? otherA : null)
        };
    };

}

/**
 * Controls sync scroll of the source and preview blocks
 *
 * @param scrollMap
 * @param animatorSrc
 * @param animatorResult
 * @param eSrc
 * @param eResult
 * @constructor
 */
function SyncScroll(scrollMap, animatorSrc, animatorResult, eSrc, eResult, previewScrollTargets) {
    const syncResultScroll = function () {
        animatorResult.setPos(scrollMap.getPosition(eSrc, 0, 1));
    };

    const syncSrcScroll = function () {
        animatorSrc.setPos(scrollMap.getPosition(eResult, 1, 0));
    };

    function addListener(target, handler) {
        target.addEventListener('scroll', handler, {passive: true});
    }

    function removeListener(target, handler) {
        target.removeEventListener('scroll', handler);
    }

    function addPreviewListeners() {
        if (!previewScrollTargets || !previewScrollTargets.length) {
            eResult.addEventListener('scroll', syncSrcScroll);
            return;
        }
        previewScrollTargets.forEach(function (target) {
            addListener(target, syncSrcScroll);
        });
    }

    function removePreviewListeners() {
        if (!previewScrollTargets || !previewScrollTargets.length) {
            eResult.removeEventListener('scroll', syncSrcScroll);
            return;
        }
        previewScrollTargets.forEach(function (target) {
            removeListener(target, syncSrcScroll);
        });
    }

    this.scrollToBottomIfRequired = function () {
        const srcViewport = eSrc.clientHeight || eSrc.offsetHeight || 0;
        if (eSrc.scrollHeight >= srcViewport && eSrc.scrollHeight - srcViewport - eSrc.scrollTop < 5) {
            const resultViewport = eResult.clientHeight || eResult.offsetHeight || 0;
            animatorResult.setPos(Math.max(0, eResult.scrollHeight - resultViewport));
        }
    };

    this.switchScrollToSrc = function (event) {
        removePreviewListeners();
        eSrc.removeEventListener('scroll', syncResultScroll);
        eSrc.addEventListener('scroll', syncResultScroll);
    };

    this.switchScrollToResult = function () {
        eSrc.removeEventListener('scroll', syncResultScroll);
        removePreviewListeners();
        addPreviewListeners();
    };
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

var pictureFolderCsrfTokens = {};
var pendingImageMap = new Map();
var lastPreviewWrapper = null;
var activeImageOperations = 0;

function requestPictureCsrfToken(path) {
    if (pictureFolderCsrfTokens[path]) {
        return Promise.resolve(pictureFolderCsrfTokens[path]);
    }

    const params = new URLSearchParams();
    params.append('path', path);

    return fetch('ajax.php?action=picture_csrf_token', {
        method: 'POST',
        body: params
    })
        .then(response => response.json())
        .then(data => {
            if (!data || !data.success) {
                throw new Error((data && data.message) ? data.message : 'Unable to fetch CSRF token.');
            }
            pictureFolderCsrfTokens[path] = data.csrf_token;
            return data.csrf_token;
        });
}

function reservePictureName(dir, name, csrfToken) {
    const params = new URLSearchParams();
    params.append('dir', dir);
    params.append('name', name);
    params.append('csrf_token', csrfToken);

    return fetch('ajax.php?action=reserve_image', {
        method: 'POST',
        body: params
    })
        .then(response => response.json())
        .then(function (data) {
            syncImageLoadingIndicator();
            return data;
        });
}

function getImageDimensions(file) {
    return new Promise(function (resolve) {
        if (!file) {
            resolve({width: 'auto', height: 'auto'});
            return;
        }

        const img = new Image();
        const url = URL.createObjectURL(file);
        img.onload = function () {
            URL.revokeObjectURL(url);
            resolve({width: img.naturalWidth || 'auto', height: img.naturalHeight || 'auto'});
        };
        img.onerror = function () {
            URL.revokeObjectURL(url);
            resolve({width: 'auto', height: 'auto'});
        };
        img.src = url;
    });
}

function sanitizeImageSrc(src) {
    return encodeURI(src)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/'/g, '&#039;')
        .replace(/"/g, '&quot;');
}

function insertImageTag(src, width, height, altPressed) {
    let w = width;
    let h = height;

    if (altPressed) {
        if (!isNaN(parseInt(w))) {
            w = parseInt(w) / 2;
        }
        if (!isNaN(parseInt(h))) {
            h = parseInt(h) / 2;
        }
    }

    const safeSrc = sanitizeImageSrc(src);
    const sOpenTag = '<img src="' + safeSrc + '" width="' + (w || 'auto') + '" height="' + (h || 'auto') + '" ' + 'loading="lazy" alt="',
        sCloseTag = '" />';
    document.dispatchEvent(new CustomEvent('insert_tag.s2', {detail: {sStart: sOpenTag, sEnd: sCloseTag}}));
}

function replaceImageSrcInEditor(oldSrc, newSrc) {
    const cm = s2_codemirror.get_current && s2_codemirror.get_current();
    if (!cm || !cm.getSearchCursor) {
        return;
    }

    cm.operation(function () {
        const safeOld = sanitizeImageSrc(oldSrc);
        const safeNew = sanitizeImageSrc(newSrc);
        const cursor = cm.getSearchCursor(safeOld, {line: 0, ch: 0});
        while (cursor.findNext()) {
            cursor.replace(safeNew);
        }
    });
}

function applyPendingImages(wrapper) {
    if (!wrapper || pendingImageMap.size === 0) {
        return;
    }

    const images = wrapper.querySelectorAll('img[src]');
    images.forEach(function (img) {
        const src = img.getAttribute('src');
        if (pendingImageMap.has(src)) {
            img.setAttribute('data-pending-src', src);
            img.setAttribute('src', pendingImageMap.get(src));
            img.style.filter = 'blur(2px)';
            img.style.opacity = '0.75';
        }
    });
}

function updatePendingImageKey(oldSrc, newSrc) {
    if (!pendingImageMap.has(oldSrc)) {
        return;
    }

    const blobUrl = pendingImageMap.get(oldSrc);
    pendingImageMap.delete(oldSrc);
    pendingImageMap.set(newSrc, blobUrl);

    if (lastPreviewWrapper) {
        const images = lastPreviewWrapper.querySelectorAll('img[data-pending-src="' + oldSrc + '"]');
        images.forEach(function (img) {
            img.setAttribute('data-pending-src', newSrc);
            img.setAttribute('src', blobUrl);
        });
    }
}

function finalizePendingImage(filePath, blobUrl) {
    if (pendingImageMap.has(filePath)) {
        pendingImageMap.delete(filePath);
    }

    if (lastPreviewWrapper) {
        const images = lastPreviewWrapper.querySelectorAll('img');
        images.forEach(function (img) {
            if (img.getAttribute('data-pending-src') === filePath || img.getAttribute('src') === blobUrl) {
                img.setAttribute('src', filePath);
                img.removeAttribute('data-pending-src');
                img.style.filter = '';
                img.style.opacity = '';
            }
        });
    }

    if (blobUrl) {
        URL.revokeObjectURL(blobUrl);
    }
}

function markImageOperation(delta) {
    activeImageOperations = Math.max(0, activeImageOperations + delta);
    syncImageLoadingIndicator();
}

function syncImageLoadingIndicator() {
    loadingIndicator(activeImageOperations > 0);
}

document.addEventListener('preview_updated.s2', function (event) {
    if (!event.detail || !event.detail.wrapper) {
        return;
    }

    lastPreviewWrapper = event.detail.wrapper;
    applyPendingImages(lastPreviewWrapper);
});

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

function uploadBlobToPictureDir(blob, name, extension, successCallback, options) {
    var d = new Date();
    var opts = options || {};
    var dir = opts.dir || ('/' + d.getFullYear() + '/' + ('0' + (d.getMonth() + 1)).slice(-2));

    if (typeof name !== 'string') {
        name = d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + "-" + ('0' + d.getDate()).slice(-2)
            + "_" + ('0' + d.getHours()).slice(-2) + ('0' + d.getMinutes()).slice(-2) + '.' + extension;
    }

    requestPictureCsrfToken(dir)
        .then(function (csrfToken) {
            var formData = new FormData();
            formData.append('pictures[]', blob, name);
            formData.append('dir', dir);
            formData.append('ajax', '1');
            formData.append('create_dir', '1');
            formData.append('return_image_info', '1');
            formData.append('csrf_token', csrfToken);
            if (opts.token) {
                formData.append('token', opts.token);
                formData.append('name', name);
            }

            return fetch('ajax.php?action=upload', {
                method: 'POST',
                body: formData
            });
        })
        .then(response => response.json())
        .then(res => {
            if (res.success === true && typeof successCallback === 'function' && res.image_info) {
                successCallback(res, res.image_info[0], res.image_info[1]);
            } else if (res.success !== true && res.message) {
                if (typeof opts.onError === 'function') {
                    opts.onError(res);
                }
                console.warn('Upload error:', res.message);
            }
            if (typeof opts.onFinally === 'function') {
                opts.onFinally(res);
            }
            syncImageLoadingIndicator();
        })
        .catch(error => {
            if (typeof opts.onError === 'function') {
                opts.onError({message: error});
            }
            if (typeof opts.onFinally === 'function') {
                opts.onFinally();
            }
            console.warn('Error:', error);
            syncImageLoadingIndicator();
        });
}

var imageFormatSelection = {
    compareMaxSize: 512,
    jpegQuality: 0.9,
    jpegMinSsim: 0.96,
    png8MinSsim: 0.98,
    ssimTileSize: 64,
    ssimTileWeight: 0.3,
    png8MinPsnr: 40,
    png24OptLevel: 2,
    png8OptLevel: 2
};

function selectBestImageCandidate(hasAlpha, candidates, policy) {
    // Selection policy: png24 is lossless; jpeg/png8 only pass when SSIM meets the threshold, then pick the smallest.
    if (!candidates) {
        return null;
    }

    if (hasAlpha) {
        if (candidates.png8 && candidates.png8.ssim >= policy.png8MinSsim && (!candidates.png24 || candidates.png8.size < candidates.png24.size)) {
            return {type: 'png8', candidate: candidates.png8};
        }
        return candidates.png24 ? {type: 'png24', candidate: candidates.png24} : null;
    }

    var allowed = [];
    if (candidates.png24) {
        allowed.push({type: 'png24', candidate: candidates.png24});
    }
    if (candidates.png8 && candidates.png8.ssim >= policy.png8MinSsim) {
        allowed.push({type: 'png8', candidate: candidates.png8});
    }
    if (candidates.jpeg && candidates.jpeg.ssim >= policy.jpegMinSsim) {
        allowed.push({type: 'jpeg', candidate: candidates.jpeg});
    }

    if (allowed.length === 0) {
        return null;
    }

    var best = allowed[0];
    for (var i = 1; i < allowed.length; i++) {
        if (allowed[i].candidate.size < best.candidate.size) {
            best = allowed[i];
        }
    }

    return best;
}

function logImageCandidateDecision(choice, candidates, hasAlpha, policy) {
    var summary = {
        alpha: !!hasAlpha,
        choice: choice ? choice.type : 'none',
        png24: candidates.png24 ? {size: candidates.png24.size} : null,
        png8: candidates.png8 ? {size: candidates.png8.size, ssim: candidates.png8.ssim, ssimDownscale: candidates.png8.ssimDownscale, ssimTiles: candidates.png8.ssimTiles} : null,
        jpeg: candidates.jpeg ? {size: candidates.jpeg.size, ssim: candidates.jpeg.ssim, ssimDownscale: candidates.jpeg.ssimDownscale, ssimTiles: candidates.jpeg.ssimTiles} : null,
        thresholds: {
            jpegMinSsim: policy.jpegMinSsim,
            png8MinSsim: policy.png8MinSsim,
            ssimTileWeight: policy.ssimTileWeight
        }
    };
    console.log('Image optimization choice', summary);
}

function computeCandidateSsimScore(blob, analysisInfo, policy) {
    if (!analysisInfo || !analysisInfo.data) {
        return Promise.resolve({score: 0, downscale: 0, tiles: []});
    }

    return imageUtils.fileToImage(blob)
        .then(function (img) {
            var ssimLabel = 'SSIM score ' + (blob && blob.type ? blob.type : 'blob');
            console.time(ssimLabel);
            var downscale = imageUtils.getImageDataFromImage(img, {
                width: analysisInfo.width,
                height: analysisInfo.height
            });
            var downscaleScore = imageUtils.calculateSsim(analysisInfo.data, downscale.data, analysisInfo.width, analysisInfo.height);
            var tileScores = [];

            if (analysisInfo.tileData && analysisInfo.tileData.length) {
                for (var i = 0; i < analysisInfo.tileData.length; i++) {
                    var tile = analysisInfo.tiles[i];
                    var refTile = analysisInfo.tileData[i];
                    var candTile = imageUtils.getImageDataFromImage(img, {
                        sx: tile.sourceX,
                        sy: tile.sourceY,
                        sw: tile.sourceW,
                        sh: tile.sourceH,
                        width: refTile.width,
                        height: refTile.height
                    });
                    tileScores.push(imageUtils.calculateSsim(refTile.data, candTile.data, refTile.width, refTile.height));
                }
            }

            console.timeEnd(ssimLabel);
            return {
                score: imageUtils.aggregateSsimScore(downscaleScore, tileScores, policy.ssimTileWeight),
                downscale: downscaleScore,
                tiles: tileScores
            };
        })
        .catch(function () {
            return {score: 0, downscale: 0, tiles: []};
        });
}

function optimizeAndUploadFile(file, altPressed) {
    console.time('optimizeAndUploadFile');
    var uploadStarted = false;
    var reserveInfo = null;
    var reserveFailed = false;
    var blobUrl = URL.createObjectURL(file);
    var now = new Date();
    var dir = '/' + now.getFullYear() + '/' + ('0' + (now.getMonth() + 1)).slice(-2);
    var suggestedName = now.getFullYear() + '-' + ('0' + (now.getMonth() + 1)).slice(-2) + "-" + ('0' + now.getDate()).slice(-2)
        + "_" + ('0' + now.getHours()).slice(-2) + ('0' + now.getMinutes()).slice(-2) + '.png';
    var candidates = {
        png24: null,
        png8: null,
        jpeg: null
    };
    var candidateReady = {
        png24: false,
        png8: false,
        jpeg: false
    };
    var analysisInfo = null;
    var reserveDone = false;
    console.time('image analysis');
    var analysisPromise = imageUtils.getImageData(file, imageFormatSelection.compareMaxSize)
        .then(function (info) {
            analysisInfo = info;
            analysisInfo.tiles = imageUtils.selectSsimTiles(info, imageFormatSelection.ssimTileSize);
            analysisInfo.tileData = analysisInfo.tiles.map(function (tile) {
                var tileData = imageUtils.getImageDataFromImage(info.image, {
                    sx: tile.sourceX,
                    sy: tile.sourceY,
                    sw: tile.sourceW,
                    sh: tile.sourceH,
                    width: tile.width,
                    height: tile.height
                });
                return {
                    data: tileData.data,
                    width: tileData.width,
                    height: tileData.height
                };
            });
            console.timeEnd('image analysis');
            maybeStartUpload();
            return info;
        })
        .catch(function () {
            // TODO: Analysis failures force hasAlpha=false and can allow JPEG selection for PNGs; decide on a safer fallback.
            analysisInfo = {hasAlpha: false, width: 0, height: 0, data: null, tiles: [], tileData: []};
            console.timeEnd('image analysis');
            maybeStartUpload();
            return analysisInfo;
        });
    var pngSourcePromise = (file.type === 'image/png')
        ? Promise.resolve(file)
        : imageUtils.compressToPng(file, true);

    markImageOperation(1);

    /**
     * Experiments show that now in Chrome file.type is 'image/png' no matter how the image is pasted.
     * However, I prefer to write a general algorithm.
     */
    pngSourcePromise.then(function (pngFile) {
        console.time('png24 optipng');
        runOptipng(pngFile, function (optimizedBlob) {
            var candidate = optimizedBlob || pngFile;
            candidates.png24 = {
                blob: candidate,
                size: candidate.size
            };
            console.timeEnd('png24 optipng');
            candidateReady.png24 = true;
            maybeStartUpload();
        }, {
            quantize: false,
            optLevel: imageFormatSelection.png24OptLevel
        });

        console.time('png8 optipng');
        runOptipng(pngFile, function (optimizedBlob, meta) {
            var quantResult = meta && meta.quantResult ? meta.quantResult : null;
            if (optimizedBlob && quantResult && quantResult.accepted) {
                analysisPromise.then(function (info) {
                    return computeCandidateSsimScore(optimizedBlob, info, imageFormatSelection);
                }).then(function (score) {
                    candidates.png8 = {
                        blob: optimizedBlob,
                        size: optimizedBlob.size,
                        ssim: score.score,
                        ssimDownscale: score.downscale,
                        ssimTiles: score.tiles
                    };
                    console.timeEnd('png8 optipng');
                    candidateReady.png8 = true;
                    maybeStartUpload();
                }).catch(function () {
                    candidates.png8 = {
                        blob: optimizedBlob,
                        size: optimizedBlob.size,
                        ssim: 0,
                        ssimDownscale: 0,
                        ssimTiles: []
                    };
                    console.timeEnd('png8 optipng');
                    candidateReady.png8 = true;
                    maybeStartUpload();
                });
                return;
            }
            console.timeEnd('png8 optipng');
            candidateReady.png8 = true;
            maybeStartUpload();
        }, {
            quantize: true,
            minPsnr: imageFormatSelection.png8MinPsnr,
            optLevel: imageFormatSelection.png8OptLevel,
            requireQuantized: true
        });
    }).catch(function () {
        console.timeEnd('png24 optipng');
        console.timeEnd('png8 optipng');
        if (file.type === 'image/png') {
            candidates.png24 = {
                blob: file,
                size: file.size
            };
        }
        candidateReady.png24 = true;
        candidateReady.png8 = true;
        maybeStartUpload();
    });

    console.time('jpeg encode');
    imageUtils.compressToJpeg(file, imageFormatSelection.jpegQuality, '#ffffff', true)
        .then(function (jpegBlob) {
            analysisPromise
                .then(function (info) {
                    if (!info || !info.data || info.hasAlpha) {
                        console.timeEnd('jpeg encode');
                        candidateReady.jpeg = true;
                        maybeStartUpload();
                        return;
                    }

                    computeCandidateSsimScore(jpegBlob, info, imageFormatSelection).then(function (score) {
                        candidates.jpeg = {
                            blob: jpegBlob,
                            size: jpegBlob.size,
                            ssim: score.score,
                            ssimDownscale: score.downscale,
                            ssimTiles: score.tiles
                        };
                        console.timeEnd('jpeg encode');
                        candidateReady.jpeg = true;
                        maybeStartUpload();
                    }).catch(function () {
                        candidates.jpeg = {
                            blob: jpegBlob,
                            size: jpegBlob.size,
                            ssim: 0,
                            ssimDownscale: 0,
                            ssimTiles: []
                        };
                        console.timeEnd('jpeg encode');
                        candidateReady.jpeg = true;
                        maybeStartUpload();
                    });
                })
                .catch(function () {
                    console.timeEnd('jpeg encode');
                    candidateReady.jpeg = true;
                    maybeStartUpload();
                });
        })
        .catch(function () {
            console.timeEnd('jpeg encode');
            candidateReady.jpeg = true;
            maybeStartUpload();
        });

    Promise.all([
        requestPictureCsrfToken(dir).then(function (csrfToken) {
            return reservePictureName(dir, suggestedName, csrfToken);
        }),
        getImageDimensions(file)
    ])
        .then(function (results) {
            reserveInfo = results[0];
            var dimensions = results[1];

            if (!reserveInfo || reserveInfo.success === false || !reserveInfo.file_path) {
                throw new Error((reserveInfo && reserveInfo.message) ? reserveInfo.message : 'Unable to reserve image name.');
            }

            pendingImageMap.set(reserveInfo.file_path, blobUrl);
            applyPendingImages(lastPreviewWrapper);
            insertImageTag(reserveInfo.file_path, dimensions.width || 'auto', dimensions.height || 'auto', altPressed);
            reserveDone = true;
            maybeStartUpload();
        })
        .catch(function (error) {
            console.warn('Reserve error:', error);
            URL.revokeObjectURL(blobUrl);
            reserveFailed = true;
            reserveDone = true;
            maybeStartUpload();
        });

    function maybeStartUpload() {
        if (uploadStarted || !reserveDone || !analysisInfo) {
            return;
        }
        if (!candidateReady.png24 || !candidateReady.png8 || !candidateReady.jpeg) {
            return;
        }

        var choice = selectBestImageCandidate(analysisInfo.hasAlpha, candidates, imageFormatSelection);
        if (!choice || !choice.candidate || !choice.candidate.blob) {
            if (!analysisInfo.hasAlpha && candidates.jpeg) {
                choice = {type: 'jpeg', candidate: candidates.jpeg};
            } else if (candidates.png8) {
                choice = {type: 'png8', candidate: candidates.png8};
            } else if (candidates.png24) {
                choice = {type: 'png24', candidate: candidates.png24};
            } else {
                markImageOperation(-1);
                console.timeEnd('optimizeAndUploadFile');
                return;
            }
        }

        logImageCandidateDecision(choice, candidates, analysisInfo.hasAlpha, imageFormatSelection);

        uploadStarted = true;

        if (reserveInfo) {
            var uploadPng24Backup = function () {
                if (candidates.png24 && candidates.png24.blob) {
                    uploadBlobToPictureDir(candidates.png24.blob, reserveInfo.name, null, null, {
                        dir: reserveInfo.dir,
                        token: reserveInfo.token
                    });
                }
            };

            if (choice.type === 'jpeg') {
                uploadPng24Backup();
                var jpgName = reserveInfo.name.replace(/\.png$/i, '.jpg');
                uploadBlobToPictureDir(choice.candidate.blob, jpgName, null, function (res) {
                    var newPath = res.file_path;
                    replaceImageSrcInEditor(reserveInfo.file_path, newPath);
                    updatePendingImageKey(reserveInfo.file_path, newPath);
                    finalizePendingImage(newPath, blobUrl);
                    markImageOperation(-1);
                    console.timeEnd('optimizeAndUploadFile');
                }, {
                    dir: reserveInfo.dir,
                    onError: function () {
                        finalizePendingImage(reserveInfo.file_path, blobUrl);
                        markImageOperation(-1);
                        console.timeEnd('optimizeAndUploadFile');
                    }
                });
            } else if (choice.type === 'png8') {
                uploadPng24Backup();
                var png8Name = reserveInfo.name.replace(/\.png$/i, '-8.png');
                if (png8Name === reserveInfo.name) {
                    png8Name = reserveInfo.name + '-8.png';
                }
                uploadBlobToPictureDir(choice.candidate.blob, png8Name, null, function (res) {
                    var newPath = res.file_path;
                    replaceImageSrcInEditor(reserveInfo.file_path, newPath);
                    updatePendingImageKey(reserveInfo.file_path, newPath);
                    finalizePendingImage(newPath, blobUrl);
                    markImageOperation(-1);
                    console.timeEnd('optimizeAndUploadFile');
                }, {
                    dir: reserveInfo.dir,
                    onError: function () {
                        finalizePendingImage(reserveInfo.file_path, blobUrl);
                        markImageOperation(-1);
                        console.timeEnd('optimizeAndUploadFile');
                    }
                });
            } else {
                uploadBlobToPictureDir(choice.candidate.blob, reserveInfo.name, null, function (res) {
                    finalizePendingImage(res.file_path, blobUrl);
                    markImageOperation(-1);
                    console.timeEnd('optimizeAndUploadFile');
                }, {
                    dir: reserveInfo.dir,
                    token: reserveInfo.token,
                    onError: function () {
                        finalizePendingImage(reserveInfo.file_path, blobUrl);
                        markImageOperation(-1);
                        console.timeEnd('optimizeAndUploadFile');
                    }
                });
            }
            return;
        }

        if (!reserveFailed) {
            return;
        }

        var fallbackSuccess = function (res, w, h) {
            ReturnImage(res.file_path, w || 'auto', h || 'auto');
            markImageOperation(-1);
            console.timeEnd('optimizeAndUploadFile');
        };
        var fallbackError = function () {
            markImageOperation(-1);
            console.timeEnd('optimizeAndUploadFile');
        };

        var fallbackExtension = choice.type === 'jpeg' ? 'jpg' : 'png';
        uploadBlobToPictureDir(choice.candidate.blob, null, fallbackExtension, fallbackSuccess, {
            onError: fallbackError
        });
    }
}
