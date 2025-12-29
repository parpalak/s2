/* global CompressionStream */

(function (document, window) {
    'use strict';

    if (!document || !window) {
        return;
    }

    const protocol = window.location.protocol === 'https:' ? 'https:' : 'http:';
    const preloader = new ImagePreloader(protocol);
    let layoutTimer = null;
    let uniqueIndex = 0;

    const disallowedTags = new Set(['SCRIPT', 'STYLE', 'TEXTAREA', 'CODE', 'PRE']);
    let prevFormulas = [];

    function scheduleLayoutChange() {
        if (layoutTimer) {
            return;
        }
        layoutTimer = window.setTimeout(function () {
            layoutTimer = null;
            document.dispatchEvent(new Event('preview_layout_changed.s2'));
        }, 50);
    }

    function splitByFormula(text) {
        const parts = [];
        let index = 0;
        let start = text.indexOf('$$', index);

        while (start !== -1) {
            if (start > 0 && text[start - 1] === '\\') {
                index = start + 2;
                start = text.indexOf('$$', index);
                continue;
            }

            const end = text.indexOf('$$', start + 2);
            if (end === -1) {
                break;
            }

            if (start > index) {
                parts.push({type: 'text', value: text.slice(index, start)});
            }
            parts.push({type: 'formula', value: text.slice(start + 2, end)});

            index = end + 2;
            start = text.indexOf('$$', index);
        }

        if (index < text.length) {
            parts.push({type: 'text', value: text.slice(index)});
        }

        return parts.length ? parts : [{type: 'text', value: text}];
    }

    function isBlockFormulaParagraph(paragraph) {
        let formulaCount = 0;
        let text = '';

        for (let i = 0; i < paragraph.childNodes.length; i++) {
            const node = paragraph.childNodes[i];
            if (node.nodeType === Node.TEXT_NODE) {
                text += node.textContent;
            } else if (node.nodeType === Node.ELEMENT_NODE && node.classList.contains('s2-latex-slot')) {
                formulaCount++;
            } else {
                return false;
            }
        }

        if (formulaCount !== 1) {
            return false;
        }

        return /^[ \t]*(?:\([ \t]*\S+[ \t]*\))?[ \t]*$/.test(text);
    }

    function markBlockFormulas(root) {
        const slots = root.querySelectorAll('span.s2-latex-slot');
        slots.forEach(function (node) {
            const paragraph = node.parentElement;
            if (!paragraph || paragraph.tagName !== 'P') {
                return;
            }
            if (paragraph.dataset.s2LatexChecked) {
                return;
            }
            paragraph.dataset.s2LatexChecked = '1';
            if (isBlockFormulaParagraph(paragraph) && !paragraph.hasAttribute('align')) {
                paragraph.setAttribute('align', 'center');
            }
        });
    }

    function replaceAll(str, search, replacement) {
        return str.split(search).join(replacement);
    }

    function makeSvgIdsUnique(svg) {
        const matches = svg.match(/id=["']([^"']*)["']/g);
        if (!matches) {
            return svg;
        }

        for (let i = 0; i < matches.length; i++) {
            const curStr = matches[i];
            const match = curStr.match(/id=["']([^"']*)["']/);
            if (!match) {
                continue;
            }
            const id = match[1];
            const newId = 's' + uniqueIndex + id;

            svg = replaceAll(svg, curStr, 'id="' + newId + '"');
            svg = replaceAll(svg, '#' + id, '#' + newId);
        }

        uniqueIndex++;

        return svg;
    }

    function createSvgNode(doc, svg, baselineShift, opacity) {
        const attrs = [];
        attrs.push('class="svg-preview"');

        if (baselineShift === null) {
            attrs.push('width="13px" height="13px"');
        } else {
            let style = 'vertical-align:' + (-baselineShift) + 'pt';
            if (typeof opacity === 'number') {
                style += '; opacity: ' + opacity;
            }
            attrs.push('style="' + style + '"');
        }

        const container = doc.createElement('div');
        container.innerHTML = svg.replace('<svg ', '<svg ' + attrs.join(' ') + ' ');

        return container.firstElementChild;
    }

    function detectPlaceholderFormula(formulas) {
        if (formulas.length !== prevFormulas.length) {
            return null;
        }

        let editNum = 0;
        let index = -1;
        for (let i = 0; i < formulas.length; i++) {
            if (formulas[i] !== prevFormulas[i]) {
                editNum++;
                index = i;
                if (editNum > 1) {
                    return null;
                }
            }
        }

        if (editNum === 1) {
            return {index: index, formula: prevFormulas[index]};
        }

        return null;
    }

    function replaceSlotContent(slot, svgNode) {
        if (!svgNode) {
            return;
        }
        while (slot.firstChild) {
            slot.removeChild(slot.firstChild);
        }
        slot.appendChild(svgNode);
    }

    function fadeInNode(node) {
        if (!node) {
            return;
        }
        node.style.opacity = '0';
        node.style.transition = 'opacity 120ms ease';
        (window.requestAnimationFrame || window.setTimeout)(function () {
            node.style.opacity = '1';
        }, 0);
    }

    function renderFormulas(root) {
        const doc = root.ownerDocument;
        const walker = doc.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
            acceptNode: function (node) {
                if (!node.nodeValue || node.nodeValue.indexOf('$$') === -1) {
                    return NodeFilter.FILTER_REJECT;
                }
                let parent = node.parentNode;
                while (parent) {
                    if (disallowedTags.has(parent.nodeName)) {
                        return NodeFilter.FILTER_REJECT;
                    }
                    parent = parent.parentNode;
                }
                return NodeFilter.FILTER_ACCEPT;
            }
        });

        const textNodes = [];
        let current;
        while ((current = walker.nextNode())) {
            textNodes.push(current);
        }

        textNodes.forEach(function (node) {
            const parts = splitByFormula(node.nodeValue);
            if (parts.length === 1 && parts[0].type === 'text') {
                return;
            }

            const fragment = doc.createDocumentFragment();
            parts.forEach(function (part) {
                if (part.type === 'text') {
                    fragment.appendChild(doc.createTextNode(part.value));
                } else {
                    const slot = doc.createElement('span');
                    slot.className = 's2-latex-slot';
                    slot.setAttribute('data-s2-latex', part.value);
                    fragment.appendChild(slot);
                }
            });

            if (node.parentNode) {
                node.parentNode.replaceChild(fragment, node);
            }
        });

        markBlockFormulas(root);

        const slots = root.querySelectorAll('span.s2-latex-slot[data-s2-latex]');
        if (!slots.length) {
            return;
        }

        const formulas = [];
        const formulaMap = new Map();
        slots.forEach(function (node) {
            const formula = node.getAttribute('data-s2-latex');
            if (!formula) {
                return;
            }
            formulas.push(formula);
            if (!formulaMap.has(formula)) {
                formulaMap.set(formula, []);
            }
            formulaMap.get(formula).push(node);
        });

        const placeholder = detectPlaceholderFormula(formulas);
        if (placeholder && placeholder.index >= 0 && slots[placeholder.index]) {
            const cached = preloader.getImageDataFromFormula(placeholder.formula);
            if (cached && cached.svg !== null) {
                const fadedSvg = createSvgNode(doc, makeSvgIdsUnique(cached.svg), cached.baseline, 0.5);
                replaceSlotContent(slots[placeholder.index], fadedSvg);
            }
        }

        formulaMap.forEach(function (nodes, formula) {
            preloader.onLoad(formula, function (key, svg, baselineShift) {
                nodes.forEach(function (node) {
                    const svgNode = createSvgNode(doc, makeSvgIdsUnique(svg), baselineShift);
                    replaceSlotContent(node, svgNode);
                    fadeInNode(svgNode);
                });
                scheduleLayoutChange();
            });
        });

        prevFormulas = formulas;
    }

    document.addEventListener('preview_updated.s2', function (event) {
        if (!event.detail || !event.detail.wrapper) {
            return;
        }
        renderFormulas(event.detail.wrapper);
    });

    function ImagePreloader(proto) {
        const data = {};

        function ajaxReady() {
            let svg;

            if (this.status >= 200 && this.status < 400) {
                svg = this.responseText;
            } else {
                svg = '<svg height="24" version="1.1" width="24" xmlns="http://www.w3.org/2000/svg"></svg>';
            }

            setImage(this.S2formula, svg);
        }

        function deflateRaw(text, callback) {
            if (typeof CompressionStream === 'undefined') {
                callback(null);
                return;
            }

            try {
                const stream = new Blob([text]).stream();
                const compressedStream = stream.pipeThrough(new CompressionStream('deflate-raw'));

                new Response(compressedStream).blob().then(function (compressedBlob) {
                    return compressedBlob.arrayBuffer();
                }).then(function (buffer) {
                    const compressedArray = new Uint8Array(buffer);
                    const binary = Array.from(compressedArray).map(function (b) {
                        return String.fromCharCode(b);
                    }).join('');
                    const base64 = btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
                    callback(base64);
                }).catch(function () {
                    callback(null);
                });
            } catch (e) {
                callback(null);
            }
        }

        function loadImage(formula) {
            const fallbackUrl = proto + '//i.upmath.me/svg/' + encodeURIComponent(formula);

            deflateRaw(formula, function (compressed) {
                const shortUrl = compressed ? proto + '//i.upmath.me/svgb/' + compressed : null;
                const url = shortUrl && shortUrl.length < fallbackUrl.length ? shortUrl : fallbackUrl;

                const request = new XMLHttpRequest();
                request.open('GET', url, true);
                request.S2formula = formula;
                request.onload = ajaxReady;
                request.onerror = function () {
                };
                request.send();
            });
        }

        this.onLoad = function (formula, callback) {
            if (!data[formula]) {
                data[formula] = {
                    svg: null,
                    baseline: null,
                    callback: callback
                };
                loadImage(formula);
            } else if (data[formula].svg !== null) {
                callback(formula, data[formula].svg, data[formula].baseline);
            } else {
                data[formula].callback = callback;
            }
        };

        this.getImageDataFromFormula = function (formula) {
            return data[formula] || null;
        };

        function setImage(formula, svg) {
            const urlData = data[formula];
            if (!urlData) {
                return;
            }

            const m = svg.match(/postMessage\((?:&quot;|")([\d\|\.\-eE]*)(?:&quot;|")/);
            let baselineShift;
            if (m) {
                baselineShift = m && m[1] ? m[1].split('|').shift() : 0;
            } else {
                baselineShift = null;
            }

            urlData.svg = svg;
            urlData.baseline = baselineShift;

            if (urlData.callback) {
                urlData.callback(formula, svg, baselineShift);
                urlData.callback = null;
            }
        }
    }
})(document, window);
