/**
 * Image optimization overlay UI for editor preview in S2.
 *
 * @copyright 2026 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

import {formatDimensions, humanFileSize, imageState} from './state.js';

function findPreviewImageForJob(job) {
    if (!job || !imageState.lastPreviewWrapper) {
        return null;
    }
    const images = imageState.lastPreviewWrapper.querySelectorAll('img');
    for (let i = 0; i < images.length; i += 1) {
        const img = images[i];
        const key = img.getAttribute('data-pending-src') || img.getAttribute('src');
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

function findJobOverlayContainer(job) {
    if (!job || !job.overlay || !job.overlay.overlay) {
        return null;
    }
    const overlay = job.overlay.overlay;
    return overlay.closest('.s2-image-overlay-wrap');
}

function detachJobOverlay(job) {
    const container = findJobOverlayContainer(job);
    if (!container) {
        return;
    }
    const img = container.querySelector('img');
    if (img && container.parentNode) {
        container.parentNode.insertBefore(img, container);
    }
    container.remove();
}

function ensurePreviewOverlayStyles(doc) {
    if (!doc || doc.getElementById(imageState.previewOverlayStylesId)) {
        return;
    }
    const style = doc.createElement('style');
    style.id = imageState.previewOverlayStylesId;
    style.textContent = '' +
        '.s2-image-overlay-wrap{position:relative;display:inline-block;max-width:100%;}' +
        '.s2-image-overlay-wrap>img{display:block;max-width:100%;height:auto;}' +
        '.s2-image-overlay{position:absolute;left:8px;top:8px;max-width:92%;min-width:210px;background:rgba(20,22,28,0.9);color:#f5f5f5;font:12px/1.35 "Trebuchet MS",Verdana,sans-serif;padding:8px 10px;border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,0.25);}' +
        '.s2-image-overlay[data-status="done"]{background:rgba(16,28,18,0.88);}' +
        '.s2-image-overlay[data-status="uploading"]{background:rgba(32,24,12,0.9);}' +
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

function renderImageOverlay(img, job, handlers) {
    if (!img || !job || job.closed) {
        return;
    }

    const doc = img.ownerDocument;
    ensurePreviewOverlayStyles(doc);
    let container = img.closest('.s2-image-overlay-wrap');
    if (!container || container.getAttribute('data-job-id') !== String(job.id)) {
        container = doc.createElement('span');
        container.className = 's2-image-overlay-wrap';
        container.setAttribute('data-job-id', String(job.id));
        img.parentNode.insertBefore(container, img);
        container.appendChild(img);
    }

    let overlay = container.querySelector('.s2-image-overlay');
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

        const closeButton = doc.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 's2-image-overlay-close';
        closeButton.innerHTML = '&times;';
        closeButton.addEventListener('click', function () {
            if (handlers && handlers.closeImageJob) {
                handlers.closeImageJob(job);
            }
        });
        overlay.appendChild(closeButton);

        overlay.querySelectorAll('button[data-mode]').forEach(function (button) {
            button.addEventListener('click', function () {
                const mode = button.getAttribute('data-mode');
                if (mode && handlers && handlers.switchImageJobMode) {
                    handlers.switchImageJobMode(job, mode);
                }
            });
        });

        overlay.querySelectorAll('input[type="checkbox"][data-format]').forEach(function (input) {
            input.addEventListener('change', function () {
                const format = input.getAttribute('data-format');
                if (format && handlers && handlers.toggleImageJobFormat) {
                    handlers.toggleImageJobFormat(job, format, input.checked);
                }
            });
        });

        const sizeGroup = doc.createElement('div');
        sizeGroup.className = 's2-image-overlay-group s2-image-overlay-size';
        imageState.sizeOptions.forEach(function (sizeOption) {
            const button = doc.createElement('button');
            button.type = 'button';
            button.setAttribute('data-size', sizeOption === Infinity ? 'inf' : String(sizeOption));
            button.innerHTML = sizeOption === Infinity ? '&infin;' : String(sizeOption);
            button.addEventListener('click', function () {
                const value = button.getAttribute('data-size');
                if (value && handlers && handlers.switchImageJobSize) {
                    handlers.switchImageJobSize(job, value);
                }
            });
            sizeGroup.appendChild(button);
        });
        const controls = overlay.querySelector('.s2-image-overlay-controls');
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

    updateImageJobOverlay(job, handlers);
}

function updateImageJobOverlay(job, handlers) {
    if (!job || job.closed) {
        return;
    }
    if (!job.overlay || !job.overlay.overlay || !job.overlay.overlay.isConnected) {
        const img = findPreviewImageForJob(job);
        if (img) {
            renderImageOverlay(img, job, handlers);
        }
    }
    if (!job.overlay || !job.overlay.overlay || !job.overlay.overlay.isConnected) {
        return;
    }
    const state = job.modes[job.currentMode];
    const overlay = job.overlay;
    const status = state && state.status ? state.status : 'idle';
    overlay.overlay.setAttribute('data-status', status);

    let bestSize = null;
    if (state) {
        ['jpeg', 'png8', 'png24'].forEach(function (format) {
            if (!state.formatEnabled[format]) {
                return;
            }
            const candidate = state.candidates[format];
            if (candidate && typeof candidate.size === 'number') {
                if (bestSize === null || candidate.size < bestSize) {
                    bestSize = candidate.size;
                }
            }
        });
    }

    let dimText = '–';
    if (job.original.width && job.original.height) {
        dimText = formatDimensions(job.original.width, job.original.height);
        if (state && state.sourceInfo && typeof state.sourceInfo.width === 'number') {
            if (state.sourceInfo.resized || state.sourceInfo.cropped) {
                dimText += ' &rarr; ' + formatDimensions(state.sourceInfo.width, state.sourceInfo.height);
            }
        }
    }
    overlay.dims.innerHTML = dimText;

    let sizeText = humanFileSize(job.original.size);
    if (bestSize !== null) {
        sizeText += ' &rarr; ' + humanFileSize(bestSize);
    } else {
        sizeText += ' &rarr; ?';
    }
    overlay.sizes.innerHTML = sizeText;

    overlay.modeButtons.forEach(function (button) {
        const mode = button.getAttribute('data-mode');
        if (mode === job.currentMode) {
            button.classList.add('is-active');
        } else {
            button.classList.remove('is-active');
        }
    });

    overlay.sizeButtons.forEach(function (button) {
        const value = button.getAttribute('data-size');
        const sizeValue = value === 'inf' ? Infinity : parseInt(value, 10);
        if (state && state.sizeChoice === sizeValue) {
            button.classList.add('is-active');
        } else {
            button.classList.remove('is-active');
        }
    });

    ['jpeg', 'png8', 'png24'].forEach(function (format) {
        const row = overlay.formatRows[format];
        if (!row || !state) {
            return;
        }
        const input = row.querySelector('input');
        const size = row.querySelector('.s2-format-size');
        const info = row.querySelector('.s2-format-info');
        if (input) {
            input.checked = !!state.formatEnabled[format];
        }
        if (state.candidates[format] && typeof state.candidates[format].size === 'number') {
            size.textContent = humanFileSize(state.candidates[format].size);
        } else {
            size.textContent = state.candidateReady[format] ? '-' : '...';
        }
        let infoText = '';
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

export {
    detachJobOverlay,
    renderImageOverlay,
    updateImageJobOverlay
};
