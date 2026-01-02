self.window = self;

try {
    importScripts('../lib/image-q.min.js', '../lib/pako.min.js', '../lib/upng.js');
} catch (error) {
    postMessage({type: 'init-error', message: (error && error.message) || 'Failed to load quantizer libs'});
}

var imageQ = self['image-q'];
if (imageQ && typeof UPNG !== 'undefined') {
    postMessage({type: 'ready'});
} else {
    postMessage({type: 'init-error', message: 'Quantizer libs not available'});
}

function countUniqueColors(rgba, limit) {
    var seen = Object.create(null);
    var count = 0;
    for (var i = 0; i < rgba.length; i += 4) {
        var key = (rgba[i] | (rgba[i + 1] << 8) | (rgba[i + 2] << 16) | (rgba[i + 3] << 24)) >>> 0;
        if (seen[key] === undefined) {
            seen[key] = 1;
            count += 1;
            if (count > limit) {
                break;
            }
        }
    }
    return count;
}

function calculatePsnr(original, quantized) {
    var sum = 0;
    var len = original.length;
    for (var i = 0; i < len; i++) {
        var diff = original[i] - quantized[i];
        sum += diff * diff;
    }
    if (sum === 0) {
        return Infinity;
    }
    var mse = sum / len;
    return 10 * Math.log(255 * 255 / mse) / Math.LN10;
}

function quantizePng(inputData, options) {
    var t0 = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
    var minPsnr = options && typeof options.minPsnr === 'number' ? options.minPsnr : 40;
    if (typeof UPNG === 'undefined') {
        throw new Error('UPNG is not available');
    }
    if (!imageQ) {
        throw new Error('image-q is not available');
    }
    var originalSize = inputData.byteLength || inputData.length || 0;
    var tDecodeStart = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
    var decoded = UPNG.decode(inputData.buffer ? inputData.buffer : inputData);
    var rgbaBuffer = UPNG.toRGBA8(decoded)[0];
    var rgba = new Uint8Array(rgbaBuffer);
    var width = decoded.width;
    var height = decoded.height;
    var tDecodeEnd = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();

    var tPaletteStart = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
    var pointContainer = imageQ.utils.PointContainer.fromUint8Array(rgba, width, height);
    var palette = imageQ.buildPaletteSync([pointContainer], {
        colors: 256,
        paletteQuantization: 'wuquant',
        colorDistanceFormula: 'pngquant'
    });
    var tPaletteEnd = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
    var tApplyStart = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
    var quantized = imageQ.applyPaletteSync(pointContainer, palette, {
        imageQuantization: 'nearest',
        colorDistanceFormula: 'pngquant'
    });
    var tApplyEnd = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();

    var quantRgba = quantized.toUint8Array();
    var tPsnrStart = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
    var psnr = calculatePsnr(rgba, quantRgba);
    var tPsnrEnd = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
    var tEncodeStart = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
    var encoded = UPNG.encode([quantRgba.buffer], width, height, 0);
    var tEncodeEnd = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
    var encodedSize = encoded.byteLength || 0;
    var tDone = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();

    return {
        accepted: psnr >= minPsnr && encodedSize < originalSize,
        data: encoded,
        psnr: psnr,
        encodedSize: encodedSize,
        originalSize: originalSize,
        paletteSize: palette.getPointContainer().getPointArray().length,
        originalColors: countUniqueColors(rgba, 256),
        timings: {
            decode: tDecodeEnd - tDecodeStart,
            palette: tPaletteEnd - tPaletteStart,
            apply: tApplyEnd - tApplyStart,
            psnr: tPsnrEnd - tPsnrStart,
            encode: tEncodeEnd - tEncodeStart,
            total: tDone - t0
        }
    };
}

self.onmessage = function (event) {
    var message = event.data || {};

    if (message.type !== 'command') {
        return;
    }

    postMessage({type: 'stdout', message: 'Quant start id ' + message.id});
    try {
        var result = quantizePng(message.file.data, message.options || {});
        postMessage({type: 'stdout', message: 'Quant finished id ' + message.id + ' accepted=' + result.accepted});
        if (result.timings) {
            postMessage({
                type: 'stdout',
                message: 'Quant timings ms: decode ' + result.timings.decode.toFixed(0)
                    + ', palette ' + result.timings.palette.toFixed(0)
                    + ', apply ' + result.timings.apply.toFixed(0)
                    + ', psnr ' + result.timings.psnr.toFixed(0)
                    + ', encode ' + result.timings.encode.toFixed(0)
                    + ', total ' + result.timings.total.toFixed(0)
            });
        }

        if (result.accepted) {
            postMessage({
                type: 'done',
                id: message.id,
                accepted: true,
                data: result.data,
                psnr: result.psnr,
                encodedSize: result.encodedSize,
                originalSize: result.originalSize,
                paletteSize: result.paletteSize,
                originalColors: result.originalColors
            }, [result.data]);
        } else {
            postMessage({
                type: 'done',
                id: message.id,
                accepted: false,
                psnr: result.psnr,
                encodedSize: result.encodedSize,
                originalSize: result.originalSize,
                paletteSize: result.paletteSize,
                originalColors: result.originalColors
            });
        }
    } catch (error) {
        postMessage({
            type: 'error',
            id: message.id,
            message: (error && error.message) || 'Quantization error'
        });
    }
};
