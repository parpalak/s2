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
    root.imageUtils.compressToPng = compressToPng;
    root.imageUtils.compressToJpeg = compressToJpeg;
})(window);
