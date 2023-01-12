var runOptipng = (function () {
    var worker = null;

    function printConsole(text) {
        console.log(text);
    }

    function fileSize(bytes) {
        var exp = Math.log(bytes) / Math.log(1024) | 0;
        var result = (bytes / Math.pow(1024, exp)).toFixed(2);

        return result + ' ' + (exp === 0 ? 'bytes' : 'KMGTPEZY' [exp - 1] + 'B');
    }

    function dataURLtoUint8(dataurl) {
        var arr = dataurl.split(','),
            mime = arr[0].match(/:(.*?);/)[1],
            bstr = atob(arr[1]),
            n = bstr.length,
            u8arr = new Uint8Array(n);
        while (n--) {
            u8arr[n] = bstr.charCodeAt(n);
        }
        return u8arr;
    }

    var workerLoaded = false,
        loadingCallbackQueue = [];

    function runWhenWorkerIsLoaded(loadedCallback) {
        if (workerLoaded) {
            loadedCallback(worker);
            return;
        }

        loadingCallbackQueue.push(loadedCallback);

        if (worker !== null) {
            return;
        }
        worker = new Worker("js/optipng-worker.js");

        worker.onmessage = function (event) {
            var message = event.data;

            if (message.type === "ready") {
                printConsole("Ready worker");
                workerLoaded = true;
                for (var i = loadingCallbackQueue.length; i--;) {
                    (loadingCallbackQueue[i])(worker);
                }

            } else if (message.type === "stdout") {
                printConsole(message.data);

            } else if (message.type === "start") {
                printConsole("Start worker...");

            } else if (message.type === "done") {
                var buffers = message.data;

                if (buffers && buffers.length) {
                    buffers.forEach(function (file) {
                        tasks[message.id](new Blob([file.data]));
                        printConsole('size ' + fileSize(file.data.byteLength));
                    });
                }
                tasks[message.id] = null;
            }
        };
    }

    var tasks = [];

    return function progressFile(file, callback) {
        var id = tasks.length;
        tasks[id] = callback;

        runWhenWorkerIsLoaded(function (worker) {
            var fileReader = new FileReader();
            fileReader.onload = function (readFile) {
                worker.postMessage({
                    type: 'command',
                    id: id,
                    arguments: ['-o2'],
                    file: {
                        'data': dataURLtoUint8(readFile.target.result)
                    }
                });
            };
            fileReader.readAsDataURL(file);
        });
    }
})();
