var runOptipng = (function () {
    var worker = null;
    var workerLoaded = false;
    var workerFailed = false;
    var workerUrls = ["js/oxipng-worker.js", "js/optipng-worker.js"];
    var workerUrlIndex = 0;

    function printConsole(text) {
        console.log(text);
    }

    function fileSize(bytes) {
        var exp = Math.log(bytes) / Math.log(1024) | 0;
        var result = (bytes / Math.pow(1024, exp)).toFixed(2);

        return result + ' ' + (exp === 0 ? 'bytes' : 'KMGTPEZY' [exp - 1] + 'B');
    }

    function readFileAsUint8Array(file) {
        if (file && typeof file.arrayBuffer === 'function') {
            return file.arrayBuffer().then(function (buffer) {
                return new Uint8Array(buffer);
            });
        }

        return new Promise(function (resolve, reject) {
            var fileReader = new FileReader();
            fileReader.onload = function (readFile) {
                resolve(new Uint8Array(readFile.target.result));
            };
            fileReader.onerror = function () {
                reject(fileReader.error || new Error('File read error'));
            };
            fileReader.readAsArrayBuffer(file);
        });
    }

    var loadingCallbackQueue = [];

    function runWhenWorkerIsLoaded(loadedCallback, fallbackCallback) {
        if (workerLoaded) {
            loadedCallback(worker);
            return;
        }

        if (workerFailed) {
            fallbackCallback();
            return;
        }

        loadingCallbackQueue.push(loadedCallback);

        if (worker !== null) {
            return;
        }

        startWorker(workerUrls[workerUrlIndex]);
    }

    function startWorker(url) {
        worker = new Worker(url);

        worker.onerror = function () {
            fallbackToNextWorker();
        };

        worker.onmessage = function (event) {
            var message = event.data || {};

            if (message.type === "ready") {
                printConsole("Ready worker (" + url + ")");
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
                var task = tasks[message.id];

                if (task && buffers && buffers.length) {
                    buffers.forEach(function (file) {
                        task.callback(new Blob([file.data]));
                        printConsole('size ' + fileSize(file.data.byteLength));
                    });
                }
                tasks[message.id] = null;
            } else if (message.type === "error") {
                var failedTask = tasks[message.id];
                if (failedTask) {
                    failedTask.callback(failedTask.file);
                    tasks[message.id] = null;
                }
            } else if (message.type === "init-error") {
                printConsole(message.data || message.message || 'Worker error');
                fallbackToNextWorker();
            }
        };
    }

    function fallbackToNextWorker() {
        if (worker) {
            worker.terminate();
            worker = null;
        }

        workerLoaded = false;
        workerUrlIndex += 1;

        if (workerUrlIndex >= workerUrls.length) {
            workerFailed = true;
            for (var i = tasks.length; i--;) {
                if (tasks[i]) {
                    tasks[i].callback(tasks[i].file);
                    tasks[i] = null;
                }
            }
            for (var i = loadingCallbackQueue.length; i--;) {
                loadingCallbackQueue[i] = null;
            }
            loadingCallbackQueue = [];
            return;
        }

        startWorker(workerUrls[workerUrlIndex]);
    }

    var tasks = [];

    return function progressFile(file, callback) {
        var id = tasks.length;
        tasks[id] = {callback: callback, file: file};

        runWhenWorkerIsLoaded(function (worker) {
            readFileAsUint8Array(file).then(function (data) {
                worker.postMessage({
                    type: 'command',
                    id: id,
                    arguments: ['-o2'],
                    file: {
                        'data': data
                    }
                }, [data.buffer]);
            }).catch(function () {
                var task = tasks[id];
                tasks[id] = null;
                if (task) {
                    task.callback(task.file);
                }
            });
        }, function () {
            var task = tasks[id];
            tasks[id] = null;
            if (task) {
                task.callback(task.file);
            }
        });
    }
})();
