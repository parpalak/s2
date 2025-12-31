(function () {
    importScripts('optipng.min.js');

    function print(text) {
        postMessage({'type': 'stdout', 'data': text});
    }

    onmessage = function (event) {
        var message = event.data;
        if (message.type === "command") {
            var args = message.arguments;
            var inputData = message.file && message.file.data ? message.file.data : null;
            if (inputData instanceof ArrayBuffer) {
                inputData = new Uint8Array(inputData);
            }
            if (!inputData) {
                postMessage({'type': 'error', 'id': message.id, 'message': 'No input data.'});
                return;
            }

            postMessage({
                'type': 'start',
                'id': message.id,
                'data': JSON.stringify(args)
            });

            print('Received command: ' + JSON.stringify(args));

            try {
                var time = performance.now();
                var result = optipng(inputData, args, print);
                var totalTime = performance.now() - time;

                print('Finished processing (took ' + totalTime.toFixed(0) + 'ms)');

                var output = result && result.data ? result.data : result;
                if (output && output.buffer) {
                    output = output.buffer.slice(output.byteOffset || 0, (output.byteOffset || 0) + output.byteLength);
                }
                postMessage({'type': 'done', 'id': message.id, 'data': [{'data': output}], 'time': totalTime}, output ? [output] : []);
            } catch (e) {
                postMessage({'type': 'error', 'id': message.id, 'message': e && e.message ? e.message : 'OptiPNG failed.'});
            }
        }
    };
    postMessage({'type': 'ready'});
})();
