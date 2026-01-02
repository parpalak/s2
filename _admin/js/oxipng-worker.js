(function () {
    function print(text) {
        postMessage({'type': 'stdout', 'data': text});
    }

    var moduleReady = false;

    try {
        importScripts('../lib/oxipng.js');
    } catch (e) {
        postMessage({'type': 'init-error', 'message': e && e.message ? e.message : 'Failed to load oxipng.js'});
        return;
    }

    if (typeof self.oxipngInit === 'function') {
        self.oxipngInit()
            .then(function () {
                moduleReady = true;
                postMessage({'type': 'ready'});
            })
            .catch(function (e) {
                postMessage({'type': 'init-error', 'message': e && e.message ? e.message : 'Failed to init oxipng.'});
            });
    }

    onmessage = function (event) {
        var message = event.data;
        if (message.type !== "command") {
            return;
        }

        if (!moduleReady) {
            postMessage({'type': 'error', 'id': message.id, 'message': 'Oxipng module is not ready.'});
            return;
        }

        var args = message.arguments || [];
        var inputData = message.file && message.file.data ? message.file.data : null;
        if (inputData instanceof ArrayBuffer) {
            inputData = new Uint8Array(inputData);
        }

        postMessage({
            'type': 'start',
            'id': message.id,
            'data': JSON.stringify(args)
        });

        print('Received command: ' + JSON.stringify(args));

        var compressor = self.oxipng || self.optipng;
        if (typeof compressor !== 'function') {
            postMessage({'type': 'error', 'id': message.id, 'message': 'Oxipng function not found.'});
            return;
        }

        try {
            var time = performance.now();
            var result = compressor(inputData, args, print);
            var totalTime = performance.now() - time;

            print('Finished processing (took ' + totalTime.toFixed(0) + 'ms)');

            var output = result && result.data ? result.data : result;
            if (output && output.buffer) {
                output = output.buffer.slice(output.byteOffset || 0, (output.byteOffset || 0) + output.byteLength);
            }
            postMessage({'type': 'done', 'id': message.id, 'data': [{'data': output}], 'time': totalTime}, output ? [output] : []);
        } catch (e) {
            postMessage({'type': 'error', 'id': message.id, 'message': e && e.message ? e.message : 'Oxipng failed.'});
        }
    };
})();
