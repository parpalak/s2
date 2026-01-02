(function () {
    var wasm;
    var cachedTextDecoder = new TextDecoder('utf-8', { ignoreBOM: true, fatal: true });
    cachedTextDecoder.decode();

    var cachegetUint8Memory0 = null;
    function getUint8Memory0() {
        if (cachegetUint8Memory0 === null || cachegetUint8Memory0.buffer !== wasm.memory.buffer) {
            cachegetUint8Memory0 = new Uint8Array(wasm.memory.buffer);
        }
        return cachegetUint8Memory0;
    }

    function getStringFromWasm0(ptr, len) {
        return cachedTextDecoder.decode(getUint8Memory0().subarray(ptr, ptr + len));
    }

    var WASM_VECTOR_LEN = 0;
    function passArray8ToWasm0(arg, malloc) {
        var ptr = malloc(arg.length * 1);
        getUint8Memory0().set(arg, ptr / 1);
        WASM_VECTOR_LEN = arg.length;
        return ptr;
    }

    var cachegetInt32Memory0 = null;
    function getInt32Memory0() {
        if (cachegetInt32Memory0 === null || cachegetInt32Memory0.buffer !== wasm.memory.buffer) {
            cachegetInt32Memory0 = new Int32Array(wasm.memory.buffer);
        }
        return cachegetInt32Memory0;
    }

    function getArrayU8FromWasm0(ptr, len) {
        return getUint8Memory0().subarray(ptr / 1, ptr / 1 + len);
    }

    function encode(data, level) {
        var ptr0 = passArray8ToWasm0(data, wasm.__wbindgen_malloc);
        var len0 = WASM_VECTOR_LEN;
        wasm.encode(8, ptr0, len0, level);
        var r0 = getInt32Memory0()[8 / 4 + 0];
        var r1 = getInt32Memory0()[8 / 4 + 1];
        var v1 = getArrayU8FromWasm0(r0, r1).slice();
        wasm.__wbindgen_free(r0, r1 * 1);
        return v1;
    }

    function getWasmUrl() {
        var baseUrl = self.location.href;
        return baseUrl.substring(0, baseUrl.lastIndexOf('/') + 1) + 'oxipng_bg.wasm';
    }

    function normalizeLevel(args) {
        var level = 2;
        if (Array.isArray(args)) {
            for (var i = 0; i < args.length; i++) {
                var arg = args[i];
                if (typeof arg === 'string' && arg.indexOf('-o') === 0) {
                    var parsed = parseInt(arg.slice(2), 10);
                    if (!isNaN(parsed)) {
                        level = parsed;
                        break;
                    }
                }
            }
        }
        if (level < 0) {
            level = 0;
        }
        if (level > 6) {
            level = 6;
        }
        return level;
    }

    function init() {
        if (wasm) {
            return Promise.resolve(wasm);
        }

        var imports = {
            __wbindgen_placeholder__: {
                __wbindgen_throw: function (arg0, arg1) {
                    throw new Error(getStringFromWasm0(arg0, arg1));
                }
            }
        };

        return fetch(getWasmUrl())
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Failed to load oxipng wasm.');
                }
                return response.arrayBuffer();
            })
            .then(function (bytes) {
                return WebAssembly.instantiate(bytes, imports);
            })
            .then(function (result) {
                wasm = result.instance.exports;
                return wasm;
            });
    }

    self.oxipngInit = init;
    self.oxipng = function (data, args) {
        if (!wasm) {
            throw new Error('Oxipng wasm is not initialized.');
        }
        if (data instanceof ArrayBuffer) {
            data = new Uint8Array(data);
        }
        if (!(data instanceof Uint8Array)) {
            throw new Error('Invalid oxipng input.');
        }

        var level = normalizeLevel(args);
        return { data: encode(data, level) };
    };
})();
