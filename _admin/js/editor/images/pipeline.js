/**
 * Image optimization and upload pipeline for editor in S2.
 *
 * @copyright 2024-2026 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

import {runOptipng} from '../../png-optimize-setup.js';
import {resizeImageFile, analyzeImage, findJpegCandidateForSsim, compressToPng, computeCandidateSsimScore, selectBestImageCandidate} from '../../image_utils.js';
import {s2_codemirror} from '../codemirror.js';
import {editorDeps} from '../deps.js';
import {imageState, formatDimensionValue, getModePolicy, getResizeOptionsForMode, getDisplayDimensionsForMode, shouldPreferJpegOnly, logPipelineSummary} from './state.js';
import {renderImageOverlay, updateImageJobOverlay, detachJobOverlay} from './overlay.js';

function getLoadingIndicator() {
    return editorDeps.loadingIndicator;
}

function setJobSrc(job, newSrc) {
    if (job.src && imageState.pasteImageBySrc.get(job.src) === job) {
        imageState.pasteImageBySrc.delete(job.src);
    }
    job.src = newSrc;
    if (newSrc) {
        imageState.pasteImageBySrc.set(newSrc, job);
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
    return imageState.pasteImageBySrc.get(key) || null;
}

function releaseBlobUrl(blobUrl) {
    if (!blobUrl) {
        return;
    }
    if (isActiveBlobUrl(blobUrl)) {
        return;
    }
    var stillUsed = false;
    imageState.pendingImageMap.forEach(function (value) {
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

    if (job.src && imageState.pasteImageBySrc.get(job.src) === job) {
        imageState.pasteImageBySrc.delete(job.src);
    }
    imageState.pasteImageJobs.delete(job.id);

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

function requestPictureCsrfToken(path) {
    if (imageState.pictureFolderCsrfTokens[path]) {
        return Promise.resolve(imageState.pictureFolderCsrfTokens[path]);
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
            imageState.pictureFolderCsrfTokens[path] = data.csrf_token;
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
    if (!oldSrc) {
        return;
    }
    const safeOld = sanitizeImageSrc(oldSrc);
    const safeNew = sanitizeImageSrc(newSrc);
    s2_codemirror.replaceAllText(safeOld, safeNew);
}

function replaceImageTagInEditor(oldSrc, newSrc, width, height) {
    if (!oldSrc) {
        return;
    }

    const safeOld = sanitizeImageSrc(oldSrc);
    const content = s2_codemirror.getValue();
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

    s2_codemirror.replaceRangeByIndex(updated, start, end + 1);
}

function applyPendingImages(wrapper) {
    if (!wrapper || imageState.pendingImageMap.size === 0) {
        return;
    }

    const images = wrapper.querySelectorAll('img[src]');
    images.forEach(function (img) {
        const src = img.getAttribute('src');
        if (imageState.pendingImageMap.has(src)) {
            img.setAttribute('data-pending-src', src);
            img.setAttribute('src', imageState.pendingImageMap.get(src));
            img.style.filter = 'blur(2px)';
            img.style.opacity = '0.75';
        }
    });
}

function updatePendingImageKey(oldSrc, newSrc) {
    if (!imageState.pendingImageMap.has(oldSrc)) {
        return;
    }

    const blobUrl = imageState.pendingImageMap.get(oldSrc);
    imageState.pendingImageMap.delete(oldSrc);
    imageState.pendingImageMap.set(newSrc, blobUrl);
    var job = imageState.pasteImageBySrc.get(oldSrc);
    if (job) {
        setJobSrc(job, newSrc);
    }

    if (imageState.lastPreviewWrapper) {
        const images = imageState.lastPreviewWrapper.querySelectorAll('img[data-pending-src="' + oldSrc + '"]');
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
    imageState.pasteImageJobs.forEach(function (job) {
        if (!active && job && !job.closed && job.blobUrl === blobUrl) {
            active = true;
        }
    });
    return active;
}

function finalizePendingImage(filePath, blobUrl) {
    if (imageState.pendingImageMap.has(filePath)) {
        imageState.pendingImageMap.delete(filePath);
    }

    if (imageState.lastPreviewWrapper) {
        const images = imageState.lastPreviewWrapper.querySelectorAll('img');
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
        imageState.pendingImageMap.forEach(function (value) {
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
    imageState.activeImageOperations = Math.max(0, imageState.activeImageOperations + delta);
    syncImageLoadingIndicator();
}

function syncImageLoadingIndicator() {
    const loadingIndicator = getLoadingIndicator();
    if (typeof loadingIndicator === 'function') {
        loadingIndicator(imageState.activeImageOperations > 0);
    }
}

export function uploadBlobToPictureDir(blob, name, extension, dir, token) {
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
        id: ++imageState.pasteImageCounter,
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

function applyModePlaceholder(job, state) {
    if (!state.reserveInfo || !state.reserveInfo.file_path) {
        return;
    }
    var dims = getDisplayDimensionsForMode(state.mode, state.sourceInfo);
    state.displayWidth = dims.width;
    state.displayHeight = dims.height;

    var newSrc = state.reserveInfo.file_path;
    imageState.pendingImageMap.set(newSrc, job.blobUrl);
    applyPendingImages(imageState.lastPreviewWrapper);
    if (job.src) {
        replaceImageTagInEditor(job.src, newSrc, dims.width, dims.height);
        updatePendingImageKey(job.src, newSrc);
    } else {
        insertImageTag(newSrc, dims.width, dims.height);
    }
    setJobSrc(job, newSrc);
    updateImageJobOverlay(job, overlayHandlers);
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
        updateImageJobOverlay(job, overlayHandlers);
        if (cached.selectedPath && job.currentMode === mode) {
            replaceImageTagInEditor(job.src, cached.selectedPath, state.displayWidth, state.displayHeight);
            setJobSrc(job, cached.selectedPath);
        }
        return;
    }

    resetModeState(state, !allowCache);
    var runId = state.runId;
    markImageOperation(1);

    state.sourcePromise = resizeImageFile(job.file, state.policy.maxUploadEdge, getResizeOptionsForMode(mode, state.sizeChoice))
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
            updateImageJobOverlay(job, overlayHandlers);
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
            applyModePlaceholder(job, state);
        }
    });

    state.analysisPromise = state.sourcePromise
        .then(function (info) {
            if (!info || state.runId !== runId) {
                return null;
            }
            state.status = 'analyzing';
            state.statusLabel = 'Analyzing';
            updateImageJobOverlay(job, overlayHandlers);
            var srcFile = info.file || job.file;
            return analyzeImage(srcFile, state.policy);
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
            updateImageJobOverlay(job, overlayHandlers);
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
            updateImageJobOverlay(job, overlayHandlers);
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
                updateImageJobOverlay(job, overlayHandlers);
                return;
            }
            var srcFile = state.sourceInfo && state.sourceInfo.file ? state.sourceInfo.file : job.file;
            findJpegCandidateForSsim(srcFile, state.analysisInfo, state.policy, '#ffffff', true, function (progress) {
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
                updateImageJobOverlay(job, overlayHandlers);
            }).then(function (candidate) {
                if (state.runId !== runId) {
                    return;
                }
                if (candidate) {
                    state.candidates.jpeg = candidate;
                }
                state.candidateReady.jpeg = true;
                maybeStartUpload(job, state, runId);
                updateImageJobOverlay(job, overlayHandlers);
            }).catch(function () {
                if (state.runId !== runId) {
                    return;
                }
                state.candidateReady.jpeg = true;
                maybeStartUpload(job, state, runId);
                updateImageJobOverlay(job, overlayHandlers);
            });
        });
        return;
    }

    if (!state.pngSourcePromise) {
        state.pngSourcePromise = state.sourcePromise.then(function (info) {
            var srcFile = info && info.file ? info.file : job.file;
            return (srcFile.type === 'image/png')
                ? Promise.resolve(srcFile)
                : compressToPng(srcFile, true);
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
                updateImageJobOverlay(job, overlayHandlers);
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
                    return computeCandidateSsimScore(optimizedBlob, info, state.policy);
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
                    updateImageJobOverlay(job, overlayHandlers);
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
                    updateImageJobOverlay(job, overlayHandlers);
                });
                return;
            }
            state.candidateReady.png8 = true;
            maybeStartUpload(job, state, runId);
            updateImageJobOverlay(job, overlayHandlers);
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
                    updateImageJobOverlay(job, overlayHandlers);
                }
            }
        });
    }).catch(function () {
        if (state.runId !== runId) {
            return;
        }
        state.candidateReady[format] = true;
        maybeStartUpload(job, state, runId);
        updateImageJobOverlay(job, overlayHandlers);
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
    var choice = selectBestImageCandidate(state.analysisInfo && state.analysisInfo.hasAlpha, filtered, state.policy);
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
        updateImageJobOverlay(job, overlayHandlers);
        return;
    }

    state.selectedType = choice.type;
    updateImageJobOverlay(job, overlayHandlers);

    var cached = candidateMatchesUpload(state.uploaded[choice.type], choice.candidate, choice.type);
    if (cached) {
        state.status = 'done';
        state.statusLabel = 'Cached';
        if (job.currentMode === state.mode) {
            replaceImageTagInEditor(job.src, state.uploaded[choice.type].path, state.displayWidth, state.displayHeight);
            setJobSrc(job, state.uploaded[choice.type].path);
        }
        if (!state.summaryLogged) {
            logPipelineSummary(state, job);
        }
        if (state.activeRun) {
            state.activeRun = false;
            markImageOperation(-1);
        }
        updateImageJobOverlay(job, overlayHandlers);
        return;
    }

    if (state.uploadInProgress) {
        return;
    }
    state.uploadInProgress = true;
    state.status = 'uploading';
    state.statusLabel = 'Uploading';
    updateImageJobOverlay(job, overlayHandlers);

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
            logPipelineSummary(state, job);
        }
        if (state.activeRun) {
            state.activeRun = false;
            markImageOperation(-1);
        }
        updateImageJobOverlay(job, overlayHandlers);
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
            logPipelineSummary(state, job);
        }
        if (state.activeRun) {
            state.activeRun = false;
            markImageOperation(-1);
        }
        updateImageJobOverlay(job, overlayHandlers);
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
    updateImageJobOverlay(job, overlayHandlers);
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
    updateImageJobOverlay(job, overlayHandlers);
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
    updateImageJobOverlay(job, overlayHandlers);
}

const overlayHandlers = {
    closeImageJob: closeImageJob,
    switchImageJobMode: switchImageJobMode,
    switchImageJobSize: switchImageJobSize,
    toggleImageJobFormat: toggleImageJobFormat
};

export function optimizeAndUploadFile(file) {
    if (!file) {
        return;
    }
    var job = createImageJob(file);
    imageState.pasteImageJobs.set(job.id, job);
    startModePipeline(job, job.currentMode, false);
}

let pipelineInitialized = false;
let codemirrorHandlersBound = false;

function bindCodemirrorImageHandlers() {
    if (codemirrorHandlersBound) {
        return;
    }
    if (!s2_codemirror.isReady()) {
        return;
    }
    codemirrorHandlersBound = true;

    s2_codemirror.onPaste(function (event) {
        var items = (event.clipboardData || event.originalEvent.clipboardData).items,
            hasImage = false;

        for (var i = 0; i < items.length; i++) {
            var item = items[i];
            if (item.type.indexOf('image') !== -1) {
                optimizeAndUploadFile(item.getAsFile());
                hasImage = true;
            }
        }

        if (hasImage) {
            event.preventDefault();
        }
        return !hasImage;
    });

    s2_codemirror.onDrop(function (e) {
        var dt = e.dataTransfer;
        if (!dt || !dt.files) {
            return;
        }

        var files = dt.files, processed = false;
        for (var i = files.length; i--;) {
            if (
                files[i].type === 'image/jpeg'
                || files[i].type === 'image/png'
            ) {
                processed = true;
                uploadBlobToPictureDir(files[i], files[i].name)
                    .then(function (result) {
                        document.dispatchEvent(new CustomEvent('return_image.s2', {
                            detail: {
                                file_path: result.res.file_path,
                                width: result.width || 'auto',
                                height: result.height || 'auto'
                            }
                        }));
                    }).catch(function (error) {
                        console.warn('Upload error:', error);
                    });
            }
        }

        if (processed) {
            // Move cursor to a new position where a file was drpopped
            s2_codemirror.setSelectionFromCoords(e.x, e.y);
            e.preventDefault();
        }
    });
}

export function initImagePipeline() {
    if (pipelineInitialized) {
        return;
    }
    pipelineInitialized = true;

    bindCodemirrorImageHandlers();

    document.addEventListener('preview_updated.s2', function (event) {
        if (!event.detail || !event.detail.wrapper) {
            return;
        }

        imageState.lastPreviewWrapper = event.detail.wrapper;
        applyPendingImages(imageState.lastPreviewWrapper);

        var images = imageState.lastPreviewWrapper.querySelectorAll('img');
        images.forEach(function (img) {
            var job = findImageJobForPreview(img);
            if (job) {
                renderImageOverlay(img, job, overlayHandlers);
            }
        });
    });
}
