/**
 * Image analysis and compression helpers for the editor image pipeline in S2.
 *
 * @copyright 2026 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

function fileToImage(file) {
    return new Promise(function (resolve, reject) {
        if (!file) {
            reject(new Error('No file provided'));
            return;
        }

        var img = new Image();
        var url = URL.createObjectURL(file);
        img.onload = function () {
            URL.revokeObjectURL(url);
            resolve(img);
        };
        img.onerror = function () {
            URL.revokeObjectURL(url);
            reject(new Error('Unable to decode image'));
        };
        img.src = url;
    });
}

function imageToCanvas(img, options) {
    var opts = options || {};
    var canvas = document.createElement('canvas');
    var width = opts.width;
    var height = opts.height;
    var scale = opts.scale;
    var baseWidth = img.naturalWidth || img.width;
    var baseHeight = img.naturalHeight || img.height;

    if (typeof scale === 'number' && scale > 0) {
        width = Math.round(baseWidth * scale);
        height = Math.round(baseHeight * scale);
    } else if (typeof width === 'number' && typeof height === 'number') {
        // Use provided dimensions.
    } else if (typeof width === 'number') {
        height = Math.round(baseHeight * (width / baseWidth));
    } else if (typeof height === 'number') {
        width = Math.round(baseWidth * (height / baseHeight));
    } else {
        width = baseWidth;
        height = baseHeight;
    }

    canvas.width = width;
    canvas.height = height;

    var ctx = canvas.getContext('2d');
    if (opts.backgroundColor) {
        ctx.fillStyle = opts.backgroundColor;
        ctx.fillRect(0, 0, width, height);
    }
    ctx.drawImage(img, 0, 0, width, height);

    return canvas;
}

function canvasToBlob(canvas, type, quality) {
    return new Promise(function (resolve, reject) {
        canvas.toBlob(function (blob) {
            if (!blob) {
                reject(new Error('Unable to encode image'));
                return;
            }
            resolve(blob);
        }, type, quality);
    });
}

function getImageDataFromImage(img, options) {
    var opts = options || {};
    var sx = typeof opts.sx === 'number' ? opts.sx : 0;
    var sy = typeof opts.sy === 'number' ? opts.sy : 0;
    var sw = typeof opts.sw === 'number' ? opts.sw : (img.naturalWidth || img.width);
    var sh = typeof opts.sh === 'number' ? opts.sh : (img.naturalHeight || img.height);
    var width = typeof opts.width === 'number' ? opts.width : sw;
    var height = typeof opts.height === 'number' ? opts.height : sh;

    var canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    var ctx = canvas.getContext('2d');
    ctx.drawImage(img, sx, sy, sw, sh, 0, 0, width, height);
    return ctx.getImageData(0, 0, width, height);
}

function toLumaRgba(data) {
    var out = new Uint8ClampedArray(data.length);
    for (var i = 0; i < data.length; i += 4) {
        var y = Math.round(0.2126 * data[i] + 0.7152 * data[i + 1] + 0.0722 * data[i + 2]);
        out[i] = y;
        out[i + 1] = y;
        out[i + 2] = y;
        out[i + 3] = 255;
    }
    return out;
}

function calculateSsim(referenceData, candidateData, width, height) {
    if (!referenceData || !candidateData || referenceData.length !== candidateData.length) {
        return 0;
    }

    var imageQ = globalThis['image-q'];
    if (!imageQ || !imageQ.quality || typeof imageQ.quality.ssim !== 'function') {
        return 0;
    }

    var refLuma = toLumaRgba(referenceData);
    var candLuma = toLumaRgba(candidateData);
    var refContainer = imageQ.utils.PointContainer.fromUint8Array(refLuma, width, height);
    var candContainer = imageQ.utils.PointContainer.fromUint8Array(candLuma, width, height);
    return imageQ.quality.ssim(refContainer, candContainer);
}

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
    return {
        alpha: !!hasAlpha,
        choice: choice ? choice.type : 'none',
        png24: candidates.png24 ? {size: candidates.png24.size} : null,
        png8: candidates.png8 ? {size: candidates.png8.size, ssim: candidates.png8.ssim, ssimDownscale: candidates.png8.ssimDownscale} : null,
        jpeg: candidates.jpeg ? {size: candidates.jpeg.size, ssim: candidates.jpeg.ssim, ssimDownscale: candidates.jpeg.ssimDownscale, quality: candidates.jpeg.quality} : null,
        thresholds: {
            jpegMinSsim: policy.jpegMinSsim,
            png8MinSsim: policy.png8MinSsim
        }
    };
}

function computeCandidateSsimScore(blob, analysisInfo, policy) {
    if (!analysisInfo || !analysisInfo.data) {
        return Promise.resolve({score: 0, downscale: 0});
    }

    return fileToImage(blob)
        .then(function (img) {
            var downscale = getImageDataFromImage(img, {
                width: analysisInfo.width,
                height: analysisInfo.height
            });
            var downscaleScore = calculateSsim(analysisInfo.data, downscale.data, analysisInfo.width, analysisInfo.height);
            return {
                score: downscaleScore,
                downscale: downscaleScore
            };
        })
        .catch(function () {
            return {score: 0, downscale: 0};
        });
}

function analyzeImage(file, policy) {
    return getImageData(file).then(function (info) {
        return info;
    });
}

function getImageData(file) {
    return fileToImage(file).then(function (img) {
        var width = img.naturalWidth || img.width;
        var height = img.naturalHeight || img.height;
        var imageData = getImageDataFromImage(img, {width: width, height: height});
        var hasAlpha = false;
        for (var i = 3; i < imageData.data.length; i += 4) {
            if (imageData.data[i] < 255) {
                hasAlpha = true;
                break;
            }
        }

        return {
            data: imageData.data,
            width: imageData.width,
            height: imageData.height,
            hasAlpha: hasAlpha,
            originalWidth: width,
            originalHeight: height,
            image: img
        };
    });
}

function getImageDataForSize(file, width, height) {
    return fileToImage(file).then(function (img) {
        var imageData = getImageDataFromImage(img, {width: width, height: height});
        return {
            data: imageData.data,
            width: imageData.width,
            height: imageData.height
        };
    });
}

function calculatePsnr(originalData, candidateData) {
    if (!originalData || !candidateData || originalData.length !== candidateData.length) {
        return 0;
    }

    var sum = 0;
    for (var i = 0; i < originalData.length; i += 4) {
        var dr = originalData[i] - candidateData[i];
        var dg = originalData[i + 1] - candidateData[i + 1];
        var db = originalData[i + 2] - candidateData[i + 2];
        sum += dr * dr + dg * dg + db * db;
    }

    if (sum === 0) {
        return Infinity;
    }

    var mse = sum / (originalData.length / 4 * 3);
    return 10 * Math.log(255 * 255 / mse) / Math.LN10;
}

function compressToPng(file, keepSmaller) {
    var applyKeepSmaller = keepSmaller !== false;

    return fileToImage(file)
        .then(function (img) {
            var canvas = imageToCanvas(img);
            return canvasToBlob(canvas, 'image/png');
        })
        .then(function (blob) {
            if (applyKeepSmaller && file.type === 'image/png' && blob.size > file.size) {
                return file;
            }
            return blob;
        });
}

function compressToJpeg(file, quality, backgroundColor, keepSmaller) {
    var applyKeepSmaller = keepSmaller !== false;
    var opts = {};

    if (backgroundColor) {
        opts.backgroundColor = backgroundColor;
    }

    return fileToImage(file)
        .then(function (img) {
            var canvas = imageToCanvas(img, opts);
            return canvasToBlob(canvas, 'image/jpeg', quality);
        })
        .then(function (blob) {
            if (applyKeepSmaller && file.type === 'image/jpeg' && blob.size > file.size) {
                return file;
            }
            return blob;
        });
}

function resizeImageFile(file, maxEdge, backgroundColor, options) {
    var opts = options;
    if (backgroundColor && typeof backgroundColor === 'object') {
        opts = backgroundColor;
        backgroundColor = null;
    }
    opts = opts || {};
    var evenDimensions = !!opts.evenDimensions;
    var evenIfNoResize = !!opts.evenIfNoResize;
    var baseEdge = typeof opts.baseEdge === 'number' && opts.baseEdge > 0 ? opts.baseEdge : null;

    if (!file || typeof maxEdge !== 'number' || maxEdge <= 0) {
        return Promise.resolve({
            file: file,
            width: null,
            height: null,
            resized: false,
            cropped: false,
            originalWidth: null,
            originalHeight: null
        });
    }

    return fileToImage(file)
        .then(function (img) {
            var width = img.naturalWidth || img.width;
            var height = img.naturalHeight || img.height;
            var maxDim = Math.max(width, height);
            var targetWidth = width;
            var targetHeight = height;
            var resized = false;
            var cropped = false;

            if (maxDim && maxDim > maxEdge) {
                resized = true;
                if (baseEdge) {
                    var scaleBase = baseEdge / maxDim;
                    targetWidth = Math.max(1, Math.round(width * scaleBase) * 2);
                    targetHeight = Math.max(1, Math.round(height * scaleBase) * 2);
                } else {
                    var scale = maxEdge / maxDim;
                    targetWidth = Math.max(1, Math.round(width * scale));
                    targetHeight = Math.max(1, Math.round(height * scale));
                    if (evenDimensions) {
                        if (targetWidth % 2 !== 0) {
                            targetWidth = Math.max(1, targetWidth - 1);
                        }
                        if (targetHeight % 2 !== 0) {
                            targetHeight = Math.max(1, targetHeight - 1);
                        }
                    }
                }
            } else if (evenDimensions || evenIfNoResize) {
                if (targetWidth % 2 !== 0) {
                    targetWidth = Math.max(1, targetWidth - 1);
                    cropped = true;
                }
                if (targetHeight % 2 !== 0) {
                    targetHeight = Math.max(1, targetHeight - 1);
                    cropped = true;
                }
            }

            if (targetWidth === width && targetHeight === height && !cropped) {
                return {
                    file: file,
                    width: width,
                    height: height,
                    resized: false,
                    cropped: false,
                    originalWidth: width,
                    originalHeight: height
                };
            }

            var canvas;
            if (!resized && cropped) {
                canvas = document.createElement('canvas');
                canvas.width = targetWidth;
                canvas.height = targetHeight;
                var ctx = canvas.getContext('2d');
                if (backgroundColor) {
                    ctx.fillStyle = backgroundColor;
                    ctx.fillRect(0, 0, targetWidth, targetHeight);
                }
                ctx.drawImage(img, 0, 0, targetWidth, targetHeight, 0, 0, targetWidth, targetHeight);
            } else {
                canvas = imageToCanvas(img, {width: targetWidth, height: targetHeight, backgroundColor: backgroundColor});
            }
            return canvasToBlob(canvas, 'image/png').then(function (blob) {
                return {
                    file: blob,
                    width: canvas.width,
                    height: canvas.height,
                    resized: resized,
                    cropped: cropped,
                    originalWidth: width,
                    originalHeight: height
                };
            });
        })
        .catch(function () {
            return {
                file: file,
                width: null,
                height: null,
                resized: false,
                cropped: false,
                originalWidth: null,
                originalHeight: null
            };
        });
}

function normalizeQuality(value, fallback) {
    var q = typeof value === 'number' ? value : fallback;
    if (!isFinite(q)) {
        q = fallback;
    }
    return Math.max(0.1, Math.min(1, q));
}

function findJpegCandidateForSsim(file, analysisInfo, policy, backgroundColor, keepSmaller, progress) {
    if (!analysisInfo || !analysisInfo.data) {
        return Promise.resolve(null);
    }

    var maxQuality = normalizeQuality(policy && policy.jpegQuality, 0.95);
    var minQuality = normalizeQuality(policy && policy.jpegMinQuality, 0.4);
    var target = policy && typeof policy.jpegMinSsim === 'number' ? policy.jpegMinSsim : 0;
    var maxSteps = policy && typeof policy.jpegQualitySearchSteps === 'number' ? policy.jpegQualitySearchSteps : 6;
    if (minQuality > maxQuality) {
        var swap = minQuality;
        minQuality = maxQuality;
        maxQuality = swap;
    }

    function evaluate(q) {
        return compressToJpeg(file, q, backgroundColor, keepSmaller)
            .then(function (blob) {
                return computeCandidateSsimScore(blob, analysisInfo, policy).then(function (score) {
                    var candidate = {
                        blob: blob,
                        size: blob.size,
                        ssim: score.score,
                        ssimDownscale: score.downscale,
                        quality: q
                    };
                    if (typeof progress === 'function') {
                        progress({
                            stage: 'candidate',
                            quality: q,
                            size: blob.size,
                            ssim: score.score,
                            ssimDownscale: score.downscale
                        });
                    }
                    return candidate;
                });
            });
    }

    return evaluate(maxQuality).then(function (maxCandidate) {
        if (!maxCandidate || maxCandidate.ssim < target || maxQuality === minQuality || maxSteps <= 0) {
            return maxCandidate;
        }

        return evaluate(minQuality).then(function (minCandidate) {
            if (minCandidate.ssim >= target) {
                return minCandidate;
            }

            var low = minQuality;
            var high = maxQuality;
            var best = maxCandidate;
            var steps = 0;

            function next() {
                if (steps >= maxSteps) {
                    return Promise.resolve(best);
                }

                var mid = (low + high) / 2;
                steps += 1;
                return evaluate(mid).then(function (candidate) {
                    if (candidate.ssim >= target) {
                        best = candidate;
                        high = mid;
                    } else {
                        low = mid;
                    }
                    return next();
                });
            }

            return next();
        });
    });
}

export {
    fileToImage,
    imageToCanvas,
    canvasToBlob,
    getImageDataFromImage,
    toLumaRgba,
    calculateSsim,
    selectBestImageCandidate,
    logImageCandidateDecision,
    computeCandidateSsimScore,
    analyzeImage,
    getImageData,
    getImageDataForSize,
    calculatePsnr,
    compressToPng,
    compressToJpeg,
    resizeImageFile,
    findJpegCandidateForSsim
};
