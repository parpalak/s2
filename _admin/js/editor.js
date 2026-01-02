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
                            console.warn(
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

var pictureFolderCsrfTokens = {};
var pendingImageMap = new Map();
var lastPreviewWrapper = null;
var activeImageOperations = 0;
var pasteImageJobs = new Map();
var pasteImageBySrc = new Map();
var pasteImageCounter = 0;
var previewOverlayStylesId = 's2-image-overlay-styles';
var sizeOptions = [1024, 1200, 1600, Infinity];
var imagePolicyConfig = {
    base: {
        maxUploadEdge: 1600,
        jpegQuality: 0.95,
        jpegMinQuality: 0.75,
        jpegQualitySearchSteps: 6,
        png8MinSsim: 0.98,
        png8MinPsnr: 40,
        png24OptLevel: 2,
        png8OptLevel: 2
    },
    modes: {
        '1x': {
            physicalPixelScale: 1,
            policy: {
                jpegMinSsim: 0.985
            }
        },
        '2x': {
            physicalPixelScale: 2,
            policy: {
                jpegMinSsim: 0.97
            },
            resizeOptions: {
                evenDimensions: true,
                evenIfNoResize: true
            }
        }
    }
};

function humanFileSize(bytes) {
    if (typeof bytes !== 'number' || !isFinite(bytes)) {
        return '-';
    }
    if (bytes < 1024) {
        return bytes + ' B';
    }
    var exp = Math.floor(Math.log(bytes) / Math.log(1024));
    var value = bytes / Math.pow(1024, exp);
    var unit = 'KMGTPEZY'[exp - 1] + 'B';
    return value.toFixed(value >= 10 || exp === 1 ? 1 : 2) + ' ' + unit;
}

function formatDimensionValue(value) {
    if (typeof value !== 'number' || !isFinite(value)) {
        return 'auto';
    }
    return String(Math.round(value));
}

function formatDimensions(width, height) {
    return formatDimensionValue(width) + 'x' + formatDimensionValue(height);
}

function getModeConfig(mode) {
    return imagePolicyConfig.modes[mode] || imagePolicyConfig.modes['1x'];
}

function getModePolicy(mode, sizeChoice) {
    var modeConfig = getModeConfig(mode);
    var policy = Object.assign({}, imagePolicyConfig.base, modeConfig.policy || {});
    var maxEdge = (typeof sizeChoice === 'number' && isFinite(sizeChoice))
        ? sizeChoice
        : imagePolicyConfig.base.maxUploadEdge;
    if (sizeChoice === Infinity) {
        policy.maxUploadEdge = Infinity;
    } else {
    policy.maxUploadEdge = maxEdge * modeConfig.physicalPixelScale;
    }
    return policy;
}

function getResizeOptionsForMode(mode, sizeChoice) {
    var modeConfig = getModeConfig(mode);
    if (modeConfig.resizeOptions) {
        var options = Object.assign({}, modeConfig.resizeOptions);
        var baseEdge = (typeof sizeChoice === 'number' && isFinite(sizeChoice))
            ? sizeChoice
            : imagePolicyConfig.base.maxUploadEdge;
        options.baseEdge = baseEdge;
        return options;
    }
    return {};
}

function getDisplayDimensionsForMode(mode, info) {
    if (!info || typeof info.width !== 'number' || typeof info.height !== 'number') {
        return {width: 'auto', height: 'auto'};
    }
    var modeConfig = getModeConfig(mode);
    var scale = modeConfig.physicalPixelScale;
    if (typeof scale === 'number' && isFinite(scale) && scale !== 1) {
        return {width: Math.round(info.width / scale), height: Math.round(info.height / scale)};
    }
    return {width: info.width, height: info.height};
}

function shouldPreferJpegOnly(width, height) {
    var maxDim = Math.max(width || 0, height || 0);
    return maxDim > 1600;
}

function setJobSrc(job, newSrc) {
    if (job.src && pasteImageBySrc.get(job.src) === job) {
        pasteImageBySrc.delete(job.src);
    }
    job.src = newSrc;
    if (newSrc) {
        pasteImageBySrc.set(newSrc, job);
    }
}

function findImageJobForPreview(img) {
    if (!img) {
        return null;
    }
    var key = img.getAttribute('data-pending-src') || img.getAttribute('src');
    if (!key) {
        return null;
    }
    return pasteImageBySrc.get(key) || null;
}

function findJobOverlayContainer(job) {
    if (!job || !job.overlay || !job.overlay.overlay) {
        return null;
    }
    var overlay = job.overlay.overlay;
    return overlay.closest('.s2-image-overlay-wrap');
}

function detachJobOverlay(job) {
    var container = findJobOverlayContainer(job);
    if (!container) {
        return;
    }
    var img = container.querySelector('img');
    if (img && container.parentNode) {
        container.parentNode.insertBefore(img, container);
    }
    container.remove();
}

function releaseBlobUrl(blobUrl) {
    if (!blobUrl) {
        return;
    }
    if (isActiveBlobUrl(blobUrl)) {
        return;
    }
    var stillUsed = false;
    pendingImageMap.forEach(function (value) {
        if (value === blobUrl) {
            stillUsed = true;
        }
    });
    if (!stillUsed) {
        URL.revokeObjectURL(blobUrl);
    }
}

function closeImageJob(job) {
    if (!job || job.closed) {
        return;
    }
    job.closed = true;
    detachJobOverlay(job);

    if (job.src) {
        finalizePendingImage(job.src, job.blobUrl);
    } else {
        releaseBlobUrl(job.blobUrl);
    }

    if (job.src && pasteImageBySrc.get(job.src) === job) {
        pasteImageBySrc.delete(job.src);
    }
    pasteImageJobs.delete(job.id);

    Object.keys(job.modes).forEach(function (mode) {
        var state = job.modes[mode];
        if (!state) {
            return;
        }
        if (state.activeRun) {
            state.activeRun = false;
            markImageOperation(-1);
        }
        state.runId += 1;
        state.candidates = {jpeg: null, png8: null, png24: null};
        state.candidateReady = {jpeg: true, png8: true, png24: true};
        state.started = {jpeg: false, png8: false, png24: false};
        state.selectedType = null;
        state.status = 'idle';
        state.statusLabel = 'Idle';
        state.reserveInfo = null;
        state.reservePromise = null;
        state.reserveDone = false;
        state.reserveFailed = false;
        state.sourceInfo = null;
        state.sourcePromise = null;
        state.analysisInfo = null;
        state.analysisPromise = null;
        state.pngSourcePromise = null;
        state.displayWidth = null;
        state.displayHeight = null;
        state.cache = null;
        state.sizeCaches = {};
        state.uploaded = {jpeg: null, png8: null, png24: null};
        state.uploadInProgress = false;
    });
}

function findPreviewImageForJob(job) {
    if (!job || !lastPreviewWrapper) {
        return null;
    }
    var images = lastPreviewWrapper.querySelectorAll('img');
    for (var i = 0; i < images.length; i += 1) {
        var img = images[i];
        var key = img.getAttribute('data-pending-src') || img.getAttribute('src');
        if (!key) {
            continue;
        }
        if (job.src && key === job.src) {
            return img;
        }
        if (job.blobUrl && key === job.blobUrl) {
            return img;
        }
    }
    return null;
}

function ensurePreviewOverlayStyles(doc) {
    if (!doc || doc.getElementById(previewOverlayStylesId)) {
        return;
    }
    var style = doc.createElement('style');
    style.id = previewOverlayStylesId;
    style.textContent = '' +
        '.s2-image-overlay-wrap{position:relative;display:inline-block;max-width:100%;}' +
        '.s2-image-overlay-wrap>img{display:block;max-width:100%;height:auto;}' +
        '.s2-image-overlay{position:absolute;left:8px;top:8px;max-width:92%;min-width:210px;background:rgba(20,22,28,0.9);color:#f5f5f5;font:12px/1.35 \"Trebuchet MS\",Verdana,sans-serif;padding:8px 10px;border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,0.25);}' +
        '.s2-image-overlay[data-status=\"done\"]{background:rgba(16,28,18,0.88);}' +
        '.s2-image-overlay[data-status=\"uploading\"]{background:rgba(32,24,12,0.9);}' +
        '.s2-image-overlay-controls{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:6px 0;flex-wrap:wrap;}' +
        '.s2-image-overlay-group{display:flex;gap:4px;}' +
        '.s2-image-overlay-controls button{border:1px solid rgba(255,255,255,0.3);background:transparent;color:#fff;padding:2px 6px;border-radius:3px;font-size:11px;cursor:pointer;}' +
        '.s2-image-overlay-controls button.is-active{background:#f5d66c;color:#1e1e1e;border-color:#f5d66c;}' +
        '.s2-image-overlay-line{margin:2px 0;white-space:nowrap;}' +
        '.s2-image-overlay-formats{display:grid;row-gap:4px;}' +
        '.s2-image-format{display:grid;grid-template-columns:16px 44px max-content 1fr;align-items:center;column-gap:6px;font-size:11px;}' +
        '.s2-image-format input{margin:0 2px 0 0;}' +
        '.s2-image-format .s2-format-name{width:44px;text-transform:uppercase;letter-spacing:0.04em;}' +
        '.s2-image-format .s2-format-size{text-align:right;}' +
        '.s2-image-format .s2-format-info{opacity:0.75;}' +
        '.s2-image-format.is-best{color:#f5d66c;}' +
        '.s2-image-overlay-close{position:absolute;right:6px;top:6px;border:1px solid rgba(255,255,255,0.3);background:transparent;color:#fff;padding:0 5px;line-height:16px;border-radius:3px;cursor:pointer;}';
    doc.head.appendChild(style);
}

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

function insertImageTag(src, width, height) {
    var w = width;
    var h = height;
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

function replaceImageTagInEditor(oldSrc, newSrc, width, height) {
    const cm = s2_codemirror.get_current && s2_codemirror.get_current();
    if (!cm || !cm.getSearchCursor || !oldSrc) {
        return;
    }

    const doc = cm.getDoc();
    const safeOld = sanitizeImageSrc(oldSrc);
    const content = doc.getValue();
    const index = content.indexOf(safeOld);
    if (index === -1) {
        return;
    }

    const start = content.lastIndexOf('<img', index);
    const end = content.indexOf('>', index);
    if (start === -1 || end === -1) {
        return;
    }

    const tag = content.slice(start, end + 1);
    const safeNew = sanitizeImageSrc(newSrc || oldSrc);
    var updated = tag.replace(/src=\"[^\"]*\"/i, 'src="' + safeNew + '"');
    var widthValue = formatDimensionValue(width);
    var heightValue = formatDimensionValue(height);

    function ensureAttr(markup, name, value) {
        var re = new RegExp(name + '=\"[^\"]*\"', 'i');
        if (re.test(markup)) {
            return markup.replace(re, name + '="' + value + '"');
        }
        return markup.replace(/<img\s*/i, '<img ' + name + '="' + value + '" ');
    }

    updated = ensureAttr(updated, 'width', widthValue);
    updated = ensureAttr(updated, 'height', heightValue);

    doc.replaceRange(updated, doc.posFromIndex(start), doc.posFromIndex(end + 1));
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
    var job = pasteImageBySrc.get(oldSrc);
    if (job) {
        setJobSrc(job, newSrc);
    }

    if (lastPreviewWrapper) {
        const images = lastPreviewWrapper.querySelectorAll('img[data-pending-src="' + oldSrc + '"]');
        images.forEach(function (img) {
            img.setAttribute('data-pending-src', newSrc);
            img.setAttribute('src', blobUrl);
        });
    }
}

function isActiveBlobUrl(blobUrl) {
    if (!blobUrl) {
        return false;
    }
    var active = false;
    pasteImageJobs.forEach(function (job) {
        if (!active && job && !job.closed && job.blobUrl === blobUrl) {
            active = true;
        }
    });
    return active;
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
        var stillUsed = false;
        pendingImageMap.forEach(function (value) {
            if (value === blobUrl) {
                stillUsed = true;
            }
        });
        if (!stillUsed && !isActiveBlobUrl(blobUrl)) {
            URL.revokeObjectURL(blobUrl);
        }
    }
}

function markImageOperation(delta) {
    activeImageOperations = Math.max(0, activeImageOperations + delta);
    syncImageLoadingIndicator();
}

function syncImageLoadingIndicator() {
    loadingIndicator(activeImageOperations > 0);
}

function renderImageOverlay(img, job, wrapper) {
    if (!img || !job || job.closed) {
        return;
    }

    var doc = img.ownerDocument;
    ensurePreviewOverlayStyles(doc);
    var container = img.closest('.s2-image-overlay-wrap');
    if (!container || container.getAttribute('data-job-id') !== String(job.id)) {
        container = doc.createElement('span');
        container.className = 's2-image-overlay-wrap';
        container.setAttribute('data-job-id', String(job.id));
        img.parentNode.insertBefore(container, img);
        container.appendChild(img);
    }

    var overlay = container.querySelector('.s2-image-overlay');
    if (!overlay) {
        overlay = doc.createElement('div');
        overlay.className = 's2-image-overlay';
        overlay.innerHTML =
            '<div class="s2-image-overlay-line s2-image-overlay-dims">-</div>' +
            '<div class="s2-image-overlay-line s2-image-overlay-sizes">-</div>' +
            '<div class="s2-image-overlay-controls">' +
            '<div class="s2-image-overlay-group s2-image-overlay-mode">' +
            '<button type="button" data-mode="1x">1x</button>' +
            '<button type="button" data-mode="2x">2x</button>' +
            '</div>' +
            '</div>' +
            '<div class="s2-image-overlay-formats">' +
            '<label class="s2-image-format" data-format="jpeg"><input type="checkbox" data-format="jpeg"><span class="s2-format-name">jpg</span><span class="s2-format-size">-</span><span class="s2-format-info"></span></label>' +
            '<label class="s2-image-format" data-format="png8"><input type="checkbox" data-format="png8"><span class="s2-format-name">png8</span><span class="s2-format-size">-</span><span class="s2-format-info"></span></label>' +
            '<label class="s2-image-format" data-format="png24"><input type="checkbox" data-format="png24"><span class="s2-format-name">png24</span><span class="s2-format-size">-</span><span class="s2-format-info"></span></label>' +
            '</div>';
        container.appendChild(overlay);

        var closeButton = doc.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 's2-image-overlay-close';
        closeButton.innerHTML = '&times;';
        closeButton.addEventListener('click', function () {
            closeImageJob(job);
        });
        overlay.appendChild(closeButton);

        overlay.querySelectorAll('button[data-mode]').forEach(function (button) {
            button.addEventListener('click', function () {
                var mode = button.getAttribute('data-mode');
                if (mode) {
                    switchImageJobMode(job, mode);
                }
            });
        });

        overlay.querySelectorAll('input[type="checkbox"][data-format]').forEach(function (input) {
            input.addEventListener('change', function () {
                var format = input.getAttribute('data-format');
                if (format) {
                    toggleImageJobFormat(job, format, input.checked);
                }
            });
        });

        var sizeGroup = doc.createElement('div');
        sizeGroup.className = 's2-image-overlay-group s2-image-overlay-size';
        sizeOptions.forEach(function (sizeOption) {
            var button = doc.createElement('button');
            button.type = 'button';
            button.setAttribute('data-size', sizeOption === Infinity ? 'inf' : String(sizeOption));
            button.innerHTML = sizeOption === Infinity ? '&infin;' : String(sizeOption);
            button.addEventListener('click', function () {
                var value = button.getAttribute('data-size');
                if (value) {
                    switchImageJobSize(job, value);
                }
            });
            sizeGroup.appendChild(button);
        });
        var controls = overlay.querySelector('.s2-image-overlay-controls');
        if (controls) {
            controls.appendChild(sizeGroup);
        }
    }

    job.overlay = {
        overlay: overlay,
        dims: overlay.querySelector('.s2-image-overlay-dims'),
        sizes: overlay.querySelector('.s2-image-overlay-sizes'),
        modeButtons: overlay.querySelectorAll('button[data-mode]'),
        sizeButtons: overlay.querySelectorAll('button[data-size]'),
        formatRows: {
            jpeg: overlay.querySelector('.s2-image-format[data-format="jpeg"]'),
            png8: overlay.querySelector('.s2-image-format[data-format="png8"]'),
            png24: overlay.querySelector('.s2-image-format[data-format="png24"]')
        }
    };

    updateImageJobOverlay(job);
}

function updateImageJobOverlay(job) {
    if (!job || job.closed) {
        return;
    }
    if (!job.overlay || !job.overlay.overlay || !job.overlay.overlay.isConnected) {
        var img = findPreviewImageForJob(job);
        if (img) {
            renderImageOverlay(img, job, lastPreviewWrapper);
        }
    }
    if (!job.overlay || !job.overlay.overlay || !job.overlay.overlay.isConnected) {
        return;
    }
    var state = job.modes[job.currentMode];
    var overlay = job.overlay;
    var status = state && state.status ? state.status : 'idle';
    overlay.overlay.setAttribute('data-status', status);

    var bestSize = null;
    if (state) {
        ['jpeg', 'png8', 'png24'].forEach(function (format) {
            if (!state.formatEnabled[format]) {
                return;
            }
            var candidate = state.candidates[format];
            if (candidate && typeof candidate.size === 'number') {
                if (bestSize === null || candidate.size < bestSize) {
                    bestSize = candidate.size;
                }
            }
        });
    }

    var dimText = '-';
    if (job.original.width && job.original.height) {
        dimText = formatDimensions(job.original.width, job.original.height);
        if (state && state.sourceInfo && typeof state.sourceInfo.width === 'number') {
            if (state.sourceInfo.resized || state.sourceInfo.cropped) {
                dimText += ' &rarr; ' + formatDimensions(state.sourceInfo.width, state.sourceInfo.height);
            }
        }
    }
    overlay.dims.innerHTML = dimText;

    var sizeText = humanFileSize(job.original.size);
    if (bestSize !== null) {
        sizeText += ' &rarr; ' + humanFileSize(bestSize);
    } else {
        sizeText += ' &rarr; -';
    }
    overlay.sizes.innerHTML = sizeText;

    overlay.modeButtons.forEach(function (button) {
        var mode = button.getAttribute('data-mode');
        if (mode === job.currentMode) {
            button.classList.add('is-active');
        } else {
            button.classList.remove('is-active');
        }
    });

    overlay.sizeButtons.forEach(function (button) {
        var value = button.getAttribute('data-size');
        var sizeValue = value === 'inf' ? Infinity : parseInt(value, 10);
        if (state && state.sizeChoice === sizeValue) {
            button.classList.add('is-active');
        } else {
            button.classList.remove('is-active');
        }
    });

    ['jpeg', 'png8', 'png24'].forEach(function (format) {
        var row = overlay.formatRows[format];
        if (!row || !state) {
            return;
        }
        var input = row.querySelector('input');
        var size = row.querySelector('.s2-format-size');
        var info = row.querySelector('.s2-format-info');
        if (input) {
            input.checked = !!state.formatEnabled[format];
        }
        if (state.candidates[format] && typeof state.candidates[format].size === 'number') {
            size.textContent = humanFileSize(state.candidates[format].size);
        } else {
            size.textContent = state.candidateReady[format] ? '-' : '...';
        }
        var infoText = '';
        if (format === 'jpeg' && state.candidates.jpeg && typeof state.candidates.jpeg.quality === 'number') {
            infoText = 'q ' + Math.round(state.candidates.jpeg.quality * 100) + '%';
        } else if (format === 'png8' && state.candidates.png8 && typeof state.candidates.png8.colors === 'number') {
            infoText = 'colors ' + state.candidates.png8.colors;
        }
        if (infoText && !state.candidateReady[format]) {
            infoText += '...';
        }
        info.textContent = infoText;
        if (state.selectedType === format) {
            row.classList.add('is-best');
        } else {
            row.classList.remove('is-best');
        }
    });
}

document.addEventListener('preview_updated.s2', function (event) {
    if (!event.detail || !event.detail.wrapper) {
        return;
    }

    lastPreviewWrapper = event.detail.wrapper;
    applyPendingImages(lastPreviewWrapper);

    var images = lastPreviewWrapper.querySelectorAll('img');
    images.forEach(function (img) {
        var job = findImageJobForPreview(img);
        if (job) {
            renderImageOverlay(img, job, lastPreviewWrapper);
        }
    });
});

document.addEventListener('DOMContentLoaded', function () {
    document.addEventListener('keydown', function (e) {
        if (e.ctrlKey && !e.shiftKey && e.code === 'KeyS') {
            document.dispatchEvent(new Event('save_form.s2'));
            e.preventDefault();
        }
    });
});

function uploadBlobToPictureDir(blob, name, extension, dir, token) {
    var d = new Date();
    dir = dir || ('/' + d.getFullYear() + '/' + ('0' + (d.getMonth() + 1)).slice(-2));

    if (typeof name !== 'string') {
        name = d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + "-" + ('0' + d.getDate()).slice(-2)
            + "_" + ('0' + d.getHours()).slice(-2) + ('0' + d.getMinutes()).slice(-2) + '.' + extension;
    }

    return requestPictureCsrfToken(dir)
        .then(function (csrfToken) {
            var formData = new FormData();
            formData.append('pictures[]', blob, name);
            formData.append('dir', dir);
            formData.append('ajax', '1');
            formData.append('create_dir', '1');
            formData.append('return_image_info', '1');
            formData.append('csrf_token', csrfToken);
            if (token) {
                formData.append('token', token);
                formData.append('name', name);
            }

            return fetch('ajax.php?action=upload', {
                method: 'POST',
                body: formData
            });
        })
        .then(response => response.json())
        .then(res => {
            if (res.success === true && res.image_info) {
                return {res: res, width: res.image_info[0], height: res.image_info[1]};
            }
            if (res.success !== true && res.message) {
                console.warn('Upload error:', res.message);
            }
            var err = new Error((res && res.message) ? res.message : 'Upload error');
            err.res = res;
            throw err;
        })
        .finally(syncImageLoadingIndicator);
}

function createImageJob(file) {
    var now = new Date();
    var job = {
        id: ++pasteImageCounter,
        file: file,
        blobUrl: URL.createObjectURL(file),
        dir: '/' + now.getFullYear() + '/' + ('0' + (now.getMonth() + 1)).slice(-2),
        suggestedName: now.getFullYear() + '-' + ('0' + (now.getMonth() + 1)).slice(-2) + "-" + ('0' + now.getDate()).slice(-2)
            + "_" + ('0' + now.getHours()).slice(-2) + ('0' + now.getMinutes()).slice(-2) + '.png',
        currentMode: '1x',
        src: null,
        original: {width: null, height: null, size: file ? file.size : null},
        completedModes: { '1x': false, '2x': false },
        modes: {},
        overlay: null,
        closed: false
    };
    job.modes['1x'] = createModeState('1x');
    job.modes['2x'] = createModeState('2x');
    return job;
}

function createModeState(mode) {
    return {
        mode: mode,
        policy: getModePolicy(mode, 1600),
        sizeChoice: 1600,
        formatEnabled: {jpeg: true, png8: true, png24: true},
        formatInitialized: false,
        candidates: {jpeg: null, png8: null, png24: null},
        candidateReady: {jpeg: false, png8: false, png24: false},
        started: {jpeg: false, png8: false, png24: false},
        uploaded: {jpeg: null, png8: null, png24: null},
        selectedType: null,
        status: 'idle',
        statusLabel: 'Waiting',
        runId: 0,
        reserveInfo: null,
        reservePromise: null,
        reserveDone: false,
        reserveFailed: false,
        sourceInfo: null,
        sourcePromise: null,
        analysisInfo: null,
        analysisPromise: null,
        pngSourcePromise: null,
        displayWidth: null,
        displayHeight: null,
        ignoreCache: false,
        cache: null,
        sizeCaches: {},
        uploadInProgress: false,
        activeRun: false,
        summaryLogged: false
    };
}

function resetModeState(state, ignoreCache) {
    if (state.activeRun) {
        state.activeRun = false;
        markImageOperation(-1);
    }
    state.runId += 1;
    state.policy = getModePolicy(state.mode, state.sizeChoice);
    state.candidates = {jpeg: null, png8: null, png24: null};
    state.candidateReady = {jpeg: false, png8: false, png24: false};
    state.started = {jpeg: false, png8: false, png24: false};
    state.selectedType = null;
    state.status = 'starting';
    state.statusLabel = 'Preparing';
    state.reserveInfo = null;
    state.reservePromise = null;
    state.reserveDone = false;
    state.reserveFailed = false;
    state.sourceInfo = null;
    state.sourcePromise = null;
    state.analysisInfo = null;
    state.analysisPromise = null;
    state.pngSourcePromise = null;
    state.displayWidth = null;
    state.displayHeight = null;
    state.uploadInProgress = false;
    state.activeRun = true;
    state.summaryLogged = false;
    state.ignoreCache = ignoreCache;
}

function formatSelectionEquals(a, b) {
    if (!a || !b) {
        return false;
    }
    return a.jpeg === b.jpeg && a.png8 === b.png8 && a.png24 === b.png24;
}

function getSizeCacheKey(sizeChoice) {
    return sizeChoice === Infinity ? 'inf' : String(sizeChoice);
}

function applyModePlaceholder(job, state, runId) {
    if (!state.reserveInfo || !state.reserveInfo.file_path) {
        return;
    }
    var dims = getDisplayDimensionsForMode(state.mode, state.sourceInfo);
    state.displayWidth = dims.width;
    state.displayHeight = dims.height;

    var newSrc = state.reserveInfo.file_path;
    pendingImageMap.set(newSrc, job.blobUrl);
    applyPendingImages(lastPreviewWrapper);
    if (job.src) {
        replaceImageTagInEditor(job.src, newSrc, dims.width, dims.height);
        updatePendingImageKey(job.src, newSrc);
    } else {
        insertImageTag(newSrc, dims.width, dims.height);
    }
    setJobSrc(job, newSrc);
    updateImageJobOverlay(job);
}

function startModePipeline(job, mode, allowCache) {
    if (!job || job.closed) {
        return;
    }
    var state = job.modes[mode];
    if (!state) {
        return;
    }
    var sizeKey = getSizeCacheKey(state.sizeChoice);
    var cached = allowCache && state.sizeCaches ? state.sizeCaches[sizeKey] : null;
    if (cached && formatSelectionEquals(cached.formatEnabled, state.formatEnabled)) {
        state.candidates = Object.assign({}, cached.candidates);
        state.analysisInfo = cached.analysisInfo;
        state.sourceInfo = cached.sourceInfo;
        state.displayWidth = cached.displayWidth;
        state.displayHeight = cached.displayHeight;
        state.selectedType = cached.selectedType;
        state.formatEnabled = Object.assign({}, cached.formatEnabled);
        state.candidateReady = {jpeg: true, png8: true, png24: true};
        state.status = 'done';
        state.statusLabel = 'Cached';
        updateImageJobOverlay(job);
        if (cached.selectedPath && job.currentMode === mode) {
            replaceImageTagInEditor(job.src, cached.selectedPath, state.displayWidth, state.displayHeight);
            setJobSrc(job, cached.selectedPath);
        }
        return;
    }

    resetModeState(state, !allowCache);
    var runId = state.runId;
    markImageOperation(1);

    state.sourcePromise = imageUtils.resizeImageFile(job.file, state.policy.maxUploadEdge, getResizeOptionsForMode(mode, state.sizeChoice))
        .then(function (info) {
            if (state.runId !== runId) {
                return null;
            }
            state.sourceInfo = info;
            if (info && typeof info.originalWidth === 'number') {
                job.original.width = info.originalWidth;
                job.original.height = info.originalHeight;
            }
            if (info) {
                var dims = getDisplayDimensionsForMode(state.mode, info);
                state.displayWidth = dims.width;
                state.displayHeight = dims.height;
            }
            if (!state.formatInitialized && info) {
                if (shouldPreferJpegOnly(info.originalWidth, info.originalHeight)) {
                    state.formatEnabled.png8 = false;
                    state.formatEnabled.png24 = false;
                }
                state.formatInitialized = true;
            }
            updateImageJobOverlay(job);
            return info;
        })
        .catch(function () {
            if (state.runId !== runId) {
                return null;
            }
            return null;
        });

    state.reservePromise = requestPictureCsrfToken(job.dir)
        .then(function (csrfToken) {
            return reservePictureName(job.dir, job.suggestedName, csrfToken);
        })
        .then(function (reserveInfo) {
            if (state.runId !== runId) {
                return null;
            }
            if (!reserveInfo || reserveInfo.success === false || !reserveInfo.file_path) {
                throw new Error((reserveInfo && reserveInfo.message) ? reserveInfo.message : 'Unable to reserve image name.');
            }
            state.reserveInfo = reserveInfo;
            state.reserveDone = true;
            return reserveInfo;
        })
        .catch(function (error) {
            if (state.runId !== runId) {
                return null;
            }
            console.warn('Reserve error:', error);
            state.reserveFailed = true;
            state.reserveDone = true;
            return null;
        });

    Promise.all([state.reservePromise, state.sourcePromise]).then(function () {
        if (state.runId !== runId) {
            return;
        }
        if (job.currentMode === mode && state.reserveInfo && state.sourceInfo) {
            applyModePlaceholder(job, state, runId);
        }
    });

    state.analysisPromise = state.sourcePromise
        .then(function (info) {
            if (!info || state.runId !== runId) {
                return null;
            }
            state.status = 'analyzing';
            state.statusLabel = 'Analyzing';
            updateImageJobOverlay(job);
            var srcFile = info.file || job.file;
            return imageUtils.analyzeImage(srcFile, state.policy);
        })
        .then(function (info) {
            if (state.runId !== runId) {
                return null;
            }
            if (!info) {
                info = {hasAlpha: false, width: 0, height: 0, data: null};
            }
            state.analysisInfo = info;
            state.status = 'compressing';
            state.statusLabel = 'Compressing';
            updateImageJobOverlay(job);
            startEnabledFormats(job, state, runId);
            maybeStartUpload(job, state, runId);
            return info;
        })
        .catch(function () {
            if (state.runId !== runId) {
                return null;
            }
            state.analysisInfo = {hasAlpha: false, width: 0, height: 0, data: null};
            state.status = 'compressing';
            state.statusLabel = 'Compressing';
            updateImageJobOverlay(job);
            startEnabledFormats(job, state, runId);
            maybeStartUpload(job, state, runId);
            return state.analysisInfo;
        });
}

function startEnabledFormats(job, state, runId) {
    ['jpeg', 'png8', 'png24'].forEach(function (format) {
        if (state.formatEnabled[format]) {
            startFormatTask(job, state, runId, format);
        } else {
            state.candidateReady[format] = true;
        }
    });
}

function startFormatTask(job, state, runId, format) {
    if (!job || job.closed || !state) {
        return;
    }
    if (state.started[format]) {
        return;
    }
    state.started[format] = true;
    state.candidateReady[format] = false;

    if (!state.analysisPromise) {
        return;
    }

    if (format === 'jpeg') {
        state.analysisPromise.then(function () {
            if (state.runId !== runId) {
                return;
            }
            if (!state.analysisInfo || state.analysisInfo.hasAlpha || !state.analysisInfo.data) {
                state.candidateReady.jpeg = true;
                maybeStartUpload(job, state, runId);
                updateImageJobOverlay(job);
                return;
            }
            var srcFile = state.sourceInfo && state.sourceInfo.file ? state.sourceInfo.file : job.file;
            imageUtils.findJpegCandidateForSsim(srcFile, state.analysisInfo, state.policy, '#ffffff', true, function (progress) {
                if (state.runId !== runId || !progress) {
                    return;
                }
                state.candidates.jpeg = {
                    blob: null,
                    size: progress.size,
                    ssim: progress.ssim,
                    ssimDownscale: progress.ssimDownscale,
                    quality: progress.quality
                };
                updateImageJobOverlay(job);
            }).then(function (candidate) {
                if (state.runId !== runId) {
                    return;
                }
                if (candidate) {
                    state.candidates.jpeg = candidate;
                }
                state.candidateReady.jpeg = true;
                maybeStartUpload(job, state, runId);
                updateImageJobOverlay(job);
            }).catch(function () {
                if (state.runId !== runId) {
                    return;
                }
                state.candidateReady.jpeg = true;
                maybeStartUpload(job, state, runId);
                updateImageJobOverlay(job);
            });
        });
        return;
    }

    if (!state.pngSourcePromise) {
        state.pngSourcePromise = state.sourcePromise.then(function (info) {
            var srcFile = info && info.file ? info.file : job.file;
            return (srcFile.type === 'image/png')
                ? Promise.resolve(srcFile)
                : imageUtils.compressToPng(srcFile, true);
        });
    }

    state.pngSourcePromise.then(function (pngFile) {
        if (state.runId !== runId) {
            return;
        }
        if (format === 'png24') {
            runOptipng(pngFile, function (optimizedBlob) {
                if (state.runId !== runId) {
                    return;
                }
                var candidate = optimizedBlob || pngFile;
                state.candidates.png24 = {
                    blob: candidate,
                    size: candidate.size
                };
                state.candidateReady.png24 = true;
                maybeStartUpload(job, state, runId);
                updateImageJobOverlay(job);
            }, {
                quantize: false,
                optLevel: state.policy.png24OptLevel
            });
            return;
        }

        runOptipng(pngFile, function (optimizedBlob, meta) {
            if (state.runId !== runId) {
                return;
            }
            var quantResult = meta && meta.quantResult ? meta.quantResult : null;
            if (optimizedBlob && quantResult && quantResult.accepted) {
                state.analysisPromise.then(function (info) {
                    if (!info || state.runId !== runId) {
                        return null;
                    }
                    return imageUtils.computeCandidateSsimScore(optimizedBlob, info, state.policy);
                }).then(function (score) {
                    if (state.runId !== runId) {
                        return;
                    }
                    var colors = quantResult && typeof quantResult.paletteSize === 'number'
                        ? quantResult.paletteSize
                        : (quantResult && typeof quantResult.originalColors === 'number' ? quantResult.originalColors : null);
                    state.candidates.png8 = {
                        blob: optimizedBlob,
                        size: optimizedBlob.size,
                        ssim: score ? score.score : 0,
                        ssimDownscale: score ? score.downscale : 0,
                        psnr: quantResult.psnr,
                        colors: colors
                    };
                    state.candidateReady.png8 = true;
                    maybeStartUpload(job, state, runId);
                    updateImageJobOverlay(job);
                }).catch(function () {
                    if (state.runId !== runId) {
                        return;
                    }
                    var colors = quantResult && typeof quantResult.paletteSize === 'number'
                        ? quantResult.paletteSize
                        : (quantResult && typeof quantResult.originalColors === 'number' ? quantResult.originalColors : null);
                    state.candidates.png8 = {
                        blob: optimizedBlob,
                        size: optimizedBlob.size,
                        ssim: 0,
                        ssimDownscale: 0,
                        psnr: quantResult.psnr,
                        colors: colors
                    };
                    state.candidateReady.png8 = true;
                    maybeStartUpload(job, state, runId);
                    updateImageJobOverlay(job);
                });
                return;
            }
            state.candidateReady.png8 = true;
            maybeStartUpload(job, state, runId);
            updateImageJobOverlay(job);
        }, {
            quantize: true,
            minPsnr: state.policy.png8MinPsnr,
            optLevel: state.policy.png8OptLevel,
            requireQuantized: true,
            onProgress: function (progress) {
                if (state.runId !== runId || !progress) {
                    return;
                }
                if (progress.stage === 'quant') {
                    var colors = progress.quantResult && typeof progress.quantResult.paletteSize === 'number'
                        ? progress.quantResult.paletteSize
                        : (progress.quantResult && typeof progress.quantResult.originalColors === 'number' ? progress.quantResult.originalColors : null);
                    state.candidates.png8 = {
                        blob: null,
                        size: progress.size,
                        ssim: 0,
                        ssimDownscale: 0,
                        psnr: progress.quantResult ? progress.quantResult.psnr : null,
                        colors: colors
                    };
                    updateImageJobOverlay(job);
                }
            }
        });
    }).catch(function () {
        if (state.runId !== runId) {
            return;
        }
        state.candidateReady[format] = true;
        maybeStartUpload(job, state, runId);
        updateImageJobOverlay(job);
    });
}

function areEnabledCandidatesReady(state) {
    return ['jpeg', 'png8', 'png24'].every(function (format) {
        return !state.formatEnabled[format] || state.candidateReady[format];
    });
}

function chooseBestCandidate(state) {
    var filtered = {
        png24: state.formatEnabled.png24 ? state.candidates.png24 : null,
        png8: state.formatEnabled.png8 ? state.candidates.png8 : null,
        jpeg: state.formatEnabled.jpeg ? state.candidates.jpeg : null
    };
    var choice = imageUtils.selectBestImageCandidate(state.analysisInfo && state.analysisInfo.hasAlpha, filtered, state.policy);
    if (!choice || !choice.candidate || !choice.candidate.blob) {
        if (!state.analysisInfo || !state.analysisInfo.hasAlpha) {
            if (state.formatEnabled.jpeg && state.candidates.jpeg) {
                return {type: 'jpeg', candidate: state.candidates.jpeg};
            }
        }
        if (state.formatEnabled.png8 && state.candidates.png8) {
            return {type: 'png8', candidate: state.candidates.png8};
        }
        if (state.formatEnabled.png24 && state.candidates.png24) {
            return {type: 'png24', candidate: state.candidates.png24};
        }
        return null;
    }
    return choice;
}

function numbersMatch(a, b) {
    if (a === b) {
        return true;
    }
    if (typeof a === 'number' && typeof b === 'number' && isFinite(a) && isFinite(b)) {
        return Math.abs(a - b) <= 0.0001;
    }
    return false;
}

function candidateMatchesUpload(uploaded, candidate, format) {
    if (!uploaded || !candidate || !uploaded.path) {
        return false;
    }
    if (typeof uploaded.size !== 'number' || typeof candidate.size !== 'number') {
        return false;
    }
    if (uploaded.size !== candidate.size) {
        return false;
    }
    if (format === 'jpeg') {
        if (!numbersMatch(uploaded.quality, candidate.quality)) {
            return false;
        }
    } else if (format === 'png8') {
        if (!numbersMatch(uploaded.psnr, candidate.psnr)) {
            return false;
        }
    }
    return true;
}

function maybeStartUpload(job, state, runId) {
    if (!job || job.closed || !state) {
        return;
    }
    if (state.runId !== runId || !state.reserveDone || !state.analysisInfo) {
        return;
    }
    if (!areEnabledCandidatesReady(state)) {
        return;
    }

    var choice = chooseBestCandidate(state);
    if (!choice || !choice.candidate || !choice.candidate.blob) {
        if (state.activeRun) {
            state.activeRun = false;
            markImageOperation(-1);
        }
        state.status = 'done';
        state.statusLabel = 'No candidate';
        updateImageJobOverlay(job);
        return;
    }

    state.selectedType = choice.type;
    updateImageJobOverlay(job);

    var cached = candidateMatchesUpload(state.uploaded[choice.type], choice.candidate, choice.type);
    if (cached) {
        state.status = 'done';
        state.statusLabel = 'Cached';
        if (job.currentMode === state.mode) {
            replaceImageTagInEditor(job.src, state.uploaded[choice.type].path, state.displayWidth, state.displayHeight);
            setJobSrc(job, state.uploaded[choice.type].path);
        }
        if (!state.summaryLogged) {
            logPipelineSummary(job, state);
        }
        if (state.activeRun) {
            state.activeRun = false;
            markImageOperation(-1);
        }
        updateImageJobOverlay(job);
        return;
    }

    if (state.uploadInProgress) {
        return;
    }
    state.uploadInProgress = true;
    state.status = 'uploading';
    state.statusLabel = 'Uploading';
    updateImageJobOverlay(job);

    var reserveInfo = state.reserveInfo;
    var uploadPromise = null;
    var targetName = reserveInfo ? reserveInfo.name : null;
    var uploadDir = reserveInfo ? reserveInfo.dir : null;
    var token = reserveInfo ? reserveInfo.token : null;

    function finishUpload(newPath) {
        if (state.runId !== runId || job.closed) {
            return;
        }
        if (newPath && choice && choice.type) {
            state.uploaded[choice.type] = {
                path: newPath,
                size: choice.candidate.size,
                quality: choice.type === 'jpeg' ? choice.candidate.quality : null,
                psnr: choice.type === 'png8' ? choice.candidate.psnr : null
            };
        }
        state.status = 'done';
        state.statusLabel = 'Done';
        state.uploadInProgress = false;
        if (newPath && job.currentMode === state.mode) {
            if (job.src) {
                replaceImageTagInEditor(job.src, newPath, state.displayWidth, state.displayHeight);
                if (job.src !== newPath) {
                    updatePendingImageKey(job.src, newPath);
                }
            } else {
                insertImageTag(newPath, state.displayWidth || 'auto', state.displayHeight || 'auto');
            }
            setJobSrc(job, newPath);
            finalizePendingImage(newPath, job.blobUrl);
        } else if (state.reserveInfo) {
            finalizePendingImage(state.reserveInfo.file_path, job.blobUrl);
        }
        job.completedModes[state.mode] = true;
        state.cache = {
            candidates: Object.assign({}, state.candidates),
            analysisInfo: state.analysisInfo,
            sourceInfo: state.sourceInfo,
            displayWidth: state.displayWidth,
            displayHeight: state.displayHeight,
            selectedType: state.selectedType,
            selectedPath: newPath,
            formatEnabled: Object.assign({}, state.formatEnabled)
        };
        state.sizeCaches[getSizeCacheKey(state.sizeChoice)] = state.cache;
        if (!state.summaryLogged) {
            logPipelineSummary(job, state);
        }
        if (state.activeRun) {
            state.activeRun = false;
            markImageOperation(-1);
        }
        updateImageJobOverlay(job);
    }

    function failUpload() {
        if (state.runId !== runId || job.closed) {
            return;
        }
        state.status = 'done';
        state.statusLabel = 'Failed';
        state.uploadInProgress = false;
        if (state.reserveInfo) {
            finalizePendingImage(state.reserveInfo.file_path, job.blobUrl);
        }
        if (!state.summaryLogged) {
            logPipelineSummary(job, state);
        }
        if (state.activeRun) {
            state.activeRun = false;
            markImageOperation(-1);
        }
        updateImageJobOverlay(job);
    }

    var uploadBackup = function () {
        if (state.formatEnabled.png24 && state.candidates.png24 && state.candidates.png24.blob && reserveInfo) {
            uploadBlobToPictureDir(
                state.candidates.png24.blob,
                reserveInfo.name,
                null,
                reserveInfo.dir,
                reserveInfo.token
            ).catch(function (error) {
                console.warn('Backup upload error:', error);
            });
        }
    };

    if (choice.type === 'jpeg') {
        uploadBackup();
        targetName = reserveInfo ? reserveInfo.name.replace(/\.png$/i, '.jpg') : null;
        uploadPromise = uploadBlobToPictureDir(choice.candidate.blob, targetName, null, uploadDir);
    } else if (choice.type === 'png8') {
        uploadBackup();
        if (reserveInfo) {
            targetName = reserveInfo.name.replace(/\.png$/i, '-8.png');
            if (targetName === reserveInfo.name) {
                targetName = reserveInfo.name + '-8.png';
            }
        }
        uploadPromise = uploadBlobToPictureDir(choice.candidate.blob, targetName, null, uploadDir);
    } else if (reserveInfo) {
        uploadPromise = uploadBlobToPictureDir(choice.candidate.blob, reserveInfo.name, null, reserveInfo.dir, token);
    }

    if (!uploadPromise && state.reserveFailed) {
        var fallbackExtension = choice.type === 'jpeg' ? 'jpg' : 'png';
        uploadPromise = uploadBlobToPictureDir(choice.candidate.blob, null, fallbackExtension);
    }

    if (!uploadPromise) {
        failUpload();
        return;
    }

    uploadPromise.then(function (result) {
        finishUpload(result && result.res ? result.res.file_path : null);
    }).catch(function () {
        failUpload();
    });
}

function switchImageJobMode(job, mode) {
    if (!job || job.closed || !job.modes[mode] || job.currentMode === mode) {
        return;
    }
    job.currentMode = mode;
    var allowCache = job.completedModes['1x'] && job.completedModes['2x'];
    startModePipeline(job, mode, allowCache);
    updateImageJobOverlay(job);
}

function switchImageJobSize(job, sizeValue) {
    if (!job || job.closed || !job.modes[job.currentMode]) {
        return;
    }
    var state = job.modes[job.currentMode];
    var nextSize = sizeValue === 'inf' ? Infinity : parseInt(sizeValue, 10);
    if (!nextSize && nextSize !== Infinity) {
        return;
    }
    if (state.sizeChoice === nextSize) {
        return;
    }
    state.sizeChoice = nextSize;
    startModePipeline(job, job.currentMode, true);
    updateImageJobOverlay(job);
}

function toggleImageJobFormat(job, format, enabled) {
    if (!job || job.closed || !job.modes[job.currentMode]) {
        return;
    }
    var state = job.modes[job.currentMode];
    if (state.formatEnabled[format] === enabled) {
        return;
    }
    state.formatEnabled[format] = enabled;
    if (enabled) {
        if (state.analysisInfo) {
            startFormatTask(job, state, state.runId, format);
        } else if (state.analysisPromise) {
            state.analysisPromise.then(function () {
                startFormatTask(job, state, state.runId, format);
            });
        }
    } else {
        state.candidateReady[format] = true;
    }
    maybeStartUpload(job, state, state.runId);
    updateImageJobOverlay(job);
}

function logPipelineSummary(job, state) {
    state.summaryLogged = true;
    var summary = {
        mode: state.mode,
        original: {
            width: job.original.width,
            height: job.original.height,
            size: job.original.size
        },
        resize: state.sourceInfo ? {
            width: state.sourceInfo.width,
            height: state.sourceInfo.height,
            resized: state.sourceInfo.resized,
            cropped: state.sourceInfo.cropped
        } : null,
        choice: state.selectedType,
        candidates: {
            jpeg: state.candidates.jpeg ? {
                size: state.candidates.jpeg.size,
                ssim: state.candidates.jpeg.ssim,
                quality: state.candidates.jpeg.quality
            } : null,
            png8: state.candidates.png8 ? {
                size: state.candidates.png8.size,
                ssim: state.candidates.png8.ssim,
                psnr: state.candidates.png8.psnr
            } : null,
            png24: state.candidates.png24 ? {
                size: state.candidates.png24.size
            } : null
        },
        thresholds: {
            jpegMinSsim: state.policy.jpegMinSsim,
            png8MinSsim: state.policy.png8MinSsim
        }
    };
    console.info('Image optimization summary', summary);
}

function optimizeAndUploadFile(file) {
    if (!file) {
        return;
    }
    var job = createImageJob(file);
    pasteImageJobs.set(job.id, job);
    startModePipeline(job, job.currentMode, false);
}
