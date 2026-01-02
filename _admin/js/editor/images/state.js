/**
 * Image pipeline shared state and config for editor in S2.
 *
 * @copyright 2025-2026 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

const imageState = {
    pictureFolderCsrfTokens: {},
    pendingImageMap: new Map(),
    lastPreviewWrapper: null,
    activeImageOperations: 0,
    pasteImageJobs: new Map(),
    pasteImageBySrc: new Map(),
    pasteImageCounter: 0,
    previewOverlayStylesId: 's2-image-overlay-styles',
    sizeOptions: [1024, 1200, 1600, Infinity],
    imagePolicyConfig: {
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
    return imageState.imagePolicyConfig.modes[mode] || imageState.imagePolicyConfig.modes['1x'];
}

function getModePolicy(mode, sizeChoice) {
    var modeConfig = getModeConfig(mode);
    var policy = Object.assign({}, imageState.imagePolicyConfig.base, modeConfig.policy || {});
    var maxEdge = (typeof sizeChoice === 'number' && isFinite(sizeChoice))
        ? sizeChoice
        : imageState.imagePolicyConfig.base.maxUploadEdge;
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
            : imageState.imagePolicyConfig.base.maxUploadEdge;
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

function logPipelineSummary(state, job) {
    if (!state || state.summaryLogged) {
        return;
    }
    state.summaryLogged = true;

    var summary = {
        mode: state.mode,
        original: job && job.original ? {
            width: job.original.width,
            height: job.original.height,
            size: job.original.size
        } : null,
        sizeChoice: state.sizeChoice,
        policy: {
            maxUploadEdge: state.policy.maxUploadEdge,
            jpegMinSsim: state.policy.jpegMinSsim,
            png8MinSsim: state.policy.png8MinSsim,
            jpegQualityRange: [state.policy.jpegMinQuality, state.policy.jpegQuality],
            png8MinPsnr: state.policy.png8MinPsnr
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

export {
    imageState,
    humanFileSize,
    formatDimensionValue,
    formatDimensions,
    getModePolicy,
    getResizeOptionsForMode,
    getDisplayDimensionsForMode,
    shouldPreferJpegOnly,
    logPipelineSummary
};
