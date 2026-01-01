/**
 * @copyright 2026 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

(function (root) {
    'use strict';

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

        var imageQ = root['image-q'];
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

    function aggregateSsimScore(downscaleScore, tileScores, tileWeight) {
        if (!tileScores || tileScores.length === 0) {
            return downscaleScore;
        }

        var minTile = tileScores[0];
        for (var i = 1; i < tileScores.length; i++) {
            if (tileScores[i] < minTile) {
                minTile = tileScores[i];
            }
        }

        var weight = typeof tileWeight === 'number' ? tileWeight : 0.3;
        return downscaleScore * (1 - weight) + minTile * weight;
    }

    function computeCandidateSsimScore(blob, analysisInfo, policy) {
        if (!analysisInfo || !analysisInfo.data) {
            return Promise.resolve({score: 0, downscale: 0, tiles: []});
        }

        return fileToImage(blob)
            .then(function (img) {
                var ssimLabel = 'SSIM score ' + (blob && blob.type ? blob.type : 'blob');
                console.time(ssimLabel);
                var downscale = getImageDataFromImage(img, {
                    width: analysisInfo.width,
                    height: analysisInfo.height
                });
                var downscaleScore = calculateSsim(analysisInfo.data, downscale.data, analysisInfo.width, analysisInfo.height);
                var tileScores = [];

                if (analysisInfo.tileData && analysisInfo.tileData.length) {
                    for (var i = 0; i < analysisInfo.tileData.length; i++) {
                        var tile = analysisInfo.tiles[i];
                        var refTile = analysisInfo.tileData[i];
                        var candTile = getImageDataFromImage(img, {
                            sx: tile.sourceX,
                            sy: tile.sourceY,
                            sw: tile.sourceW,
                            sh: tile.sourceH,
                            width: refTile.width,
                            height: refTile.height
                        });
                        tileScores.push(calculateSsim(refTile.data, candTile.data, refTile.width, refTile.height));
                    }
                }

                console.timeEnd(ssimLabel);
                return {
                    score: aggregateSsimScore(downscaleScore, tileScores, policy.ssimTileWeight),
                    downscale: downscaleScore,
                    tiles: tileScores
                };
            })
            .catch(function () {
                return {score: 0, downscale: 0, tiles: []};
            });
    }

    function selectSsimTiles(info, tileSize) {
        if (!info || !info.data || !info.width || !info.height) {
            return [];
        }

        var width = info.width;
        var height = info.height;
        var size = Math.max(16, Math.min(tileSize || 64, width, height));

        if (width <= size || height <= size) {
            return [{
                x: 0,
                y: 0,
                width: width,
                height: height,
                sourceX: 0,
                sourceY: 0,
                sourceW: info.originalWidth || width,
                sourceH: info.originalHeight || height
            }];
        }

        var luma = toLumaRgba(info.data);
        var step = size;
        var maxX = width - size;
        var maxY = height - size;
        var bestEdge = null;
        var bestSmooth = null;
        var bestText = null;

        function updateCandidate(candidate, value, mode) {
            if (!candidate) {
                return {value: value};
            }
            if (mode === 'max') {
                return value > candidate.value ? {value: value} : candidate;
            }
            return value < candidate.value ? {value: value} : candidate;
        }

        for (var y = 0; y <= maxY; y += step) {
            for (var x = 0; x <= maxX; x += step) {
                var edgeSum = 0;
                var edgeCount = 0;
                var pixels = 0;

                for (var yy = 0; yy < size - 1; yy++) {
                    for (var xx = 0; xx < size - 1; xx++) {
                        var idx = ((y + yy) * width + (x + xx)) * 4;
                        var y0 = luma[idx];
                        var y1 = luma[idx + 4];
                        var y2 = luma[idx + width * 4];
                        var grad = Math.abs(y1 - y0) + Math.abs(y2 - y0);
                        edgeSum += grad;
                        if (grad > 20) {
                            edgeCount += 1;
                        }
                        pixels += 1;
                    }
                }

                var edgeEnergy = edgeSum / Math.max(1, pixels);
                var edgeRatio = edgeCount / Math.max(1, pixels);

                var edgeCandidate = updateCandidate(bestEdge, edgeEnergy, 'max');
                if (edgeCandidate !== bestEdge) {
                    bestEdge = edgeCandidate;
                    bestEdge.x = x;
                    bestEdge.y = y;
                }

                var smoothCandidate = updateCandidate(bestSmooth, edgeEnergy, 'min');
                if (smoothCandidate !== bestSmooth) {
                    bestSmooth = smoothCandidate;
                    bestSmooth.x = x;
                    bestSmooth.y = y;
                }

                var textCandidate = updateCandidate(bestText, edgeRatio, 'max');
                if (textCandidate !== bestText) {
                    bestText = textCandidate;
                    bestText.x = x;
                    bestText.y = y;
                }
            }
        }

        var tiles = [];
        function pushTile(candidate) {
            if (!candidate) {
                return;
            }
            for (var i = 0; i < tiles.length; i++) {
                if (tiles[i].x === candidate.x && tiles[i].y === candidate.y) {
                    return;
                }
            }
            tiles.push({
                x: candidate.x,
                y: candidate.y,
                width: size,
                height: size
            });
        }

        pushTile(bestEdge);
        pushTile(bestSmooth);
        pushTile(bestText);

        var scaleX = (info.originalWidth || width) / width;
        var scaleY = (info.originalHeight || height) / height;

        tiles.forEach(function (tile) {
            tile.sourceX = Math.round(tile.x * scaleX);
            tile.sourceY = Math.round(tile.y * scaleY);
            tile.sourceW = Math.round(tile.width * scaleX);
            tile.sourceH = Math.round(tile.height * scaleY);
        });

        return tiles;
    }

    function analyzeImage(file, policy) {
        return getImageData(file, policy.compareMaxSize).then(function (info) {
            info.tiles = selectSsimTiles(info, policy.ssimTileSize);
            info.tileData = info.tiles.map(function (tile) {
                var tileData = getImageDataFromImage(info.image, {
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
            return info;
        });
    }

    function getImageData(file, maxSize) {
        return fileToImage(file).then(function (img) {
            var width = img.naturalWidth || img.width;
            var height = img.naturalHeight || img.height;
            var scale = 1;

            if (typeof maxSize === 'number' && maxSize > 0) {
                var maxDim = Math.max(width, height);
                if (maxDim > maxSize) {
                    scale = maxSize / maxDim;
                }
            }

            var targetWidth = Math.max(1, Math.round(width * scale));
            var targetHeight = Math.max(1, Math.round(height * scale));
            var imageData = getImageDataFromImage(img, {width: targetWidth, height: targetHeight});
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

    root.imageUtils = root.imageUtils || {};
    root.imageUtils.fileToImage = fileToImage;
    root.imageUtils.imageToCanvas = imageToCanvas;
    root.imageUtils.canvasToBlob = canvasToBlob;
    root.imageUtils.getImageDataFromImage = getImageDataFromImage;
    root.imageUtils.toLumaRgba = toLumaRgba;
    root.imageUtils.calculateSsim = calculateSsim;
    root.imageUtils.selectBestImageCandidate = selectBestImageCandidate;
    root.imageUtils.logImageCandidateDecision = logImageCandidateDecision;
    root.imageUtils.aggregateSsimScore = aggregateSsimScore;
    root.imageUtils.computeCandidateSsimScore = computeCandidateSsimScore;
    root.imageUtils.selectSsimTiles = selectSsimTiles;
    root.imageUtils.analyzeImage = analyzeImage;
    root.imageUtils.getImageData = getImageData;
    root.imageUtils.getImageDataForSize = getImageDataForSize;
    root.imageUtils.calculatePsnr = calculatePsnr;
    root.imageUtils.compressToPng = compressToPng;
    root.imageUtils.compressToJpeg = compressToJpeg;
})(window);
