var runOptipng = (function () {
    var worker = null;
    var workerLoaded = false;
    var workerFailed = false;
    var workerUrls = ["js/oxipng-worker.js", "js/optipng-worker.js"];
    var workerUrlIndex = 0;
    var quantWorker = null;
    var quantWorkerLoaded = false;
    var quantWorkerFailed = false;
    var quantWorkerUrl = "js/png-quant-worker.js";
    var QUANT_MIN_PSNR = 40;

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

    function makePngFile(original, data) {
        if (typeof File === 'function' && original && original.name) {
            return new File([data], original.name, {type: 'image/png'});
        }
        return new Blob([data], {type: 'image/png'});
    }

    var loadingCallbackQueue = [];
    var quantLoadingQueue = [];

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

    function runWhenQuantWorkerIsLoaded(loadedCallback, fallbackCallback) {
        if (quantWorkerLoaded) {
            printConsole('Quant worker already loaded');
            loadedCallback(quantWorker);
            return;
        }

        if (quantWorkerFailed) {
            printConsole('Quant worker failed earlier');
            fallbackCallback();
            return;
        }

        quantLoadingQueue.push({
            loaded: loadedCallback,
            fallback: fallbackCallback
        });

        if (quantWorker !== null) {
            printConsole('Quant worker initialization in progress');
            return;
        }

        printConsole('Start quant worker...');
        startQuantWorker();
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
                        task.callback(new Blob([file.data]), task.meta);
                        printConsole('size ' + fileSize(file.data.byteLength));
                    });
                }
                tasks[message.id] = null;
            } else if (message.type === "error") {
                printConsole('Worker error id ' + message.id);
                var failedTask = tasks[message.id];
                if (failedTask) {
                    failedTask.callback(failedTask.fallbackFile || failedTask.file, failedTask.meta);
                    tasks[message.id] = null;
                }
            } else if (message.type === "init-error") {
                printConsole(message.data || message.message || 'Worker error');
                fallbackToNextWorker();
            }
        };
    }

    function startQuantWorker() {
        quantWorker = new Worker(quantWorkerUrl);

        quantWorker.onerror = function () {
            printConsole('Quant worker error event');
            handleQuantWorkerFailure();
        };

        quantWorker.onmessage = function (event) {
            var message = event.data || {};

            if (message.type === "ready") {
                printConsole("Ready quant worker (" + quantWorkerUrl + ")");
                quantWorkerLoaded = true;
                for (var i = quantLoadingQueue.length; i--;) {
                    (quantLoadingQueue[i].loaded)(quantWorker);
                }
                quantLoadingQueue = [];
            } else if (message.type === "stdout") {
                printConsole(message.message || message.data);
            } else if (message.type === "done") {
                var task = quantTasks[message.id];
                if (task) {
                    printConsole('Quant worker done id ' + message.id + (message.accepted ? ' (accepted)' : ' (rejected)'));
                    if (task.start) {
                        var now = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
                        printConsole('Quant worker time id ' + message.id + ': ' + (now - task.start).toFixed(0) + 'ms');
                    }
                    if (message.accepted && message.data) {
                        task.resolve({
                            data: message.data,
                            psnr: message.psnr,
                            paletteSize: message.paletteSize,
                            originalColors: message.originalColors,
                            encodedSize: message.encodedSize,
                            originalSize: message.originalSize,
                            accepted: true
                        });
                    } else {
                        task.resolve({
                            psnr: message.psnr,
                            paletteSize: message.paletteSize,
                            originalColors: message.originalColors,
                            encodedSize: message.encodedSize,
                            originalSize: message.originalSize,
                            accepted: false
                        });
                    }
                    quantTasks[message.id] = null;
                }
            } else if (message.type === "error" || message.type === "init-error") {
                var failedTask = quantTasks[message.id];
                if (failedTask) {
                    failedTask.resolve(null);
                    quantTasks[message.id] = null;
                }
                printConsole('Quant worker error: ' + (message.message || 'unknown'));
                handleQuantWorkerFailure();
            }
        };
    }

    function handleQuantWorkerFailure() {
        printConsole('Quant worker failure fallback');
        quantWorkerFailed = true;
        for (var j = quantLoadingQueue.length; j--;) {
            if (quantLoadingQueue[j] && typeof quantLoadingQueue[j].fallback === 'function') {
                quantLoadingQueue[j].fallback();
            }
        }
        quantLoadingQueue = [];
        for (var k = quantTasks.length; k--;) {
            if (quantTasks[k]) {
                quantTasks[k].resolve(null);
                quantTasks[k] = null;
            }
        }
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
                    tasks[i].callback(tasks[i].fallbackFile || tasks[i].file, tasks[i].meta);
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
    var quantTasks = [];

    function quantizePng(file, minPsnr) {
        return new Promise(function (resolve) {
            if (quantWorkerFailed) {
                printConsole('Quant worker is disabled, skip quantization');
                resolve(null);
                return;
            }

            var id = quantTasks.length;
            quantTasks[id] = {resolve: resolve, start: 0};

            runWhenQuantWorkerIsLoaded(function (worker) {
                readFileAsUint8Array(file).then(function (data) {
                    printConsole('Quant worker post message id ' + id + ', size ' + fileSize(data.byteLength));
                    quantTasks[id].start = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
                    worker.postMessage({
                        type: 'command',
                        id: id,
                        options: {
                            minPsnr: typeof minPsnr === 'number' ? minPsnr : QUANT_MIN_PSNR
                        },
                        file: {
                            data: data
                        }
                    }, [data.buffer]);
                }).catch(function () {
                    printConsole('Quant worker read file failed');
                    var task = quantTasks[id];
                    if (task) {
                        task.resolve(null);
                        quantTasks[id] = null;
                    }
                });
            }, function () {
                var task = quantTasks[id];
                if (task) {
                    task.resolve(null);
                    quantTasks[id] = null;
                }
            });
        });
    }

    return function progressFile(file, callback, options) {
        var opts = options || {};
        var quantize = opts.quantize !== false;
        var requireQuantized = opts.requireQuantized === true;
        var minPsnr = typeof opts.minPsnr === 'number' ? opts.minPsnr : QUANT_MIN_PSNR;
        var optLevel = typeof opts.optLevel === 'number' ? opts.optLevel : 2;
        var onProgress = typeof opts.onProgress === 'function' ? opts.onProgress : null;

        var id = tasks.length;
        tasks[id] = {callback: callback, file: file, meta: {quantResult: null}, fallbackFile: file};

        runWhenWorkerIsLoaded(function (worker) {
            var quantPromise = quantize ? quantizePng(file, minPsnr) : Promise.resolve(null);
            quantPromise.then(function (quantResult) {
                var optimizedFile = file;
                if (quantResult && quantResult.accepted && quantResult.data) {
                    optimizedFile = makePngFile(file, quantResult.data);
                }

                tasks[id].meta.quantResult = quantResult;
                tasks[id].fallbackFile = optimizedFile;
                if (onProgress) {
                    onProgress({
                        stage: 'quant',
                        quantResult: quantResult,
                        size: quantResult && typeof quantResult.encodedSize === 'number' ? quantResult.encodedSize : (optimizedFile && optimizedFile.size) || null
                    });
                }

                if (requireQuantized && (!quantResult || !quantResult.accepted || !quantResult.data)) {
                    var task = tasks[id];
                    tasks[id] = null;
                    if (task) {
                        task.callback(null, task.meta);
                    }
                    return;
                }

                readFileAsUint8Array(optimizedFile).then(function (data) {
                    worker.postMessage({
                        type: 'command',
                        id: id,
                        arguments: ['-o' + optLevel],
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
            }).catch(function () {
                var task = tasks[id];
                tasks[id] = null;
                if (task) {
                    task.callback(requireQuantized ? null : task.file, task.meta);
                }
            });
        }, function () {
            var task = tasks[id];
            tasks[id] = null;
            if (task) {
                task.callback(requireQuantized ? null : task.file, task.meta);
            }
        });
    }
})();
