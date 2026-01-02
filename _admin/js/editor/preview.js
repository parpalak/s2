/**
 * Editor preview rendering and sync for S2.
 *
 * @copyright 2025-2026 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

import {editorDeps, assertDeps} from './deps.js';
import {s2_codemirror} from './codemirror.js';
import {escapeHtml} from './utils/escape.js';

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

function renderPreviewError(doc, message) {
    if (!doc) {
        return;
    }
    doc.open();
    doc.write('<div style="padding: 1em; font-family: sans-serif; color: #b40;">' + escapeHtml(message) + '</div>');
    doc.close();
}

export async function Preview(sTitle, sHtmlContent, iArticleId, sTemplateId) {
    if (!assertDeps(['s2_lang', 'sUrl'], 'Preview')) {
        return;
    }

    const d = window.frames['preview_frame'].document;
    let eHeader;
    let eText;
    const sUrl = editorDeps.sUrl;
    const morphdom = editorDeps.morphdom;

    if (sTemplateId !== Preview.lastTemplateId) {
        let response;
        try {
            response = await fetch(sUrl + 'action=load_template&template_id=' + encodeURIComponent(sTemplateId) + '&article_id=' + encodeURIComponent(iArticleId));
        } catch (error) {
            console.warn('Failed to load template preview:', error);
            renderPreviewError(d, editorDeps.s2_lang.unknown_error);
            return;
        }
        if (!response.ok) {
            console.warn('Failed to load template preview:', response.status);
            renderPreviewError(d, editorDeps.s2_lang.unknown_error);
            return;
        }
        const data = await response.json();
        if (!data || data.success !== true || !data.template) {
            console.warn('Template preview is unavailable:', data && data.preview_message ? data.preview_message : 'Unknown error');
            renderPreviewError(d, (data && data.preview_message) ? data.preview_message : editorDeps.s2_lang.unknown_error);
            return;
        }
        Preview.template = data.template;
        Preview.lastTemplateId = sTemplateId;
    } else {
        eHeader = d.getElementById('preview-header-wrapper');
        eText = d.getElementById('preview-text-wrapper');
    }

    if (!eHeader && !eText) {
        const s = Preview.template
            .replaceAll('<!-- s2_text -->', '<div id="preview-text-wrapper" data-template-name=""></div>')
            .replaceAll('<!-- s2_title -->', '<h1 id="preview-header-wrapper"></h1>');

        d.open();
        d.write(s);
        d.close();
    }

    let try_num = 30;
    const repeater = function () {
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

                if (morphdom) {
                    morphdom(eText, wrapper, {childrenOnly: true});
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

Preview.lastTemplateId = null;
Preview.template = '';

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

function getNodeScrollTop(node) {
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

export function initPreviewSync(eForm, sTextareaName) {
    const eTextarea = eForm.elements[sTextareaName];
    if (!eTextarea) {
        return null;
    }

    const previewFrame = document.getElementById(eTextarea.id + '-preview-frame');
    if (!previewFrame) {
        return null;
    }

    const scrollMap = new ScrollMap(function () {
        const doc = previewFrame.contentDocument;
        const srcScroller = s2_codemirror.getScrollerElement();
        if (!srcScroller || !doc) {
            return [[0], [0]];
        }

        const scrollElement = getScrollElement(doc);
        const lineCount = s2_codemirror.getLineCount();
        const resultNodes = doc.querySelectorAll('#preview-text-wrapper .line[data-line]');
        const mapSrc = [0];
        const mapResult = [0];
        const mapLines = [0];
        const seen = new Set();

        if (!resultNodes.length) {
            mapSrc.push(srcScroller.scrollHeight);
            mapResult.push(scrollElement.scrollHeight);
            mapLines.push(0);
            return [mapSrc, mapResult, mapLines];
        }
        if (!lineCount) {
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

            const safeLine = Math.max(0, Math.min(line, lineCount - 1));
            const srcTop = s2_codemirror.getLineTop(safeLine);
            const resultTop = getNodeScrollTop(node);

            mapSrc.push(Math.round(srcTop));
            mapResult.push(Math.round(resultTop));
            mapLines.push(safeLine);
        });

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

        const doc = previewFrame.contentDocument;
        const srcScroller = s2_codemirror.getScrollerElement();
        if (!srcScroller || !doc) {
            return;
        }

        const scrollElement = getScrollElement(doc);

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
                return s2_codemirror.getScrollTop();
            }, function (y) {
                s2_codemirror.setScrollTop(y);
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
    let A;
    let omega;
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

    this.switchScrollToSrc = function () {
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
