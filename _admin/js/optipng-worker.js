(function () {
    importScripts('optipng.min.js');

    function print(text) {
        postMessage({'type': 'stdout', 'data': text});
    }

    onmessage = function (event) {
        var message = event.data;
        if (message.type === "command") {
            var args = message.arguments;

            postMessage({
                'type': 'start',
                'id': message.id,
                'data': JSON.stringify(args)
            });

            print('Received command: ' + JSON.stringify(args));

            var time = performance.now();
            var result = optipng(message.file.data, args, print);
            var totalTime = performance.now() - time;

            print('Finished processing (took ' + totalTime.toFixed(0) + 'ms)');

            postMessage({'type': 'done', 'id': message.id, 'data': [result], 'time': totalTime});
        }
    };
    postMessage({'type': 'ready'});
})();
