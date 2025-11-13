<?php

use S2\Cms\Config\DynamicConfigProvider;
use S2\Cms\Framework\Application;

if (isset($GLOBALS['app']) && $GLOBALS['app'] instanceof Application) {
    $siteName = $GLOBALS['app']->container->get(DynamicConfigProvider::class)->get('S2_SITE_NAME');
} else {
    return;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="Generator" content="S2">
    <title>Error - <?php echo s2_htmlencode($siteName); ?></title>
    <style>
        :root {
            --error-color: #d32f2f;
            --text-color: #333;
            --border-color: rgba(0, 0, 0, 0.1);
        }

        body {
            padding: 2rem;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            background-color: #fefefe;
            max-width: 800px;
            margin: 3rem auto;
        }

        .error-container {
            padding: 2rem;
            border-left: 4px solid var(--error-color);
            background: white;
            box-shadow: 0 1px 8px -2px rgba(0, 0, 0, 0.13);
            border-radius: 0 4px 4px 0;
        }

        h1 {
            margin: 0 0 1rem;
            color: var(--error-color);
            font-size: 1.8rem;
        }

        .error-message {
            margin: 1rem 0;
            white-space: pre-wrap;
        }

        pre, code {
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
            font-size: 0.9em;
            background-color: rgba(0, 0, 0, 0.05);
            padding: 2px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
<div class="error-container">
    <h1>An error was encountered</h1>

    <p>
        Please refer to logs to find out the cause.
    </p>

    <p>
        <strong>Note:</strong> For detailed error information (necessary for troubleshooting), enable "DEBUG mode".
        To enable "DEBUG mode", open up the file config.php in a text editor, add a line that looks like
        <code>define('S2_DEBUG', 1);</code> before "return" statement and re-upload the file.
        Once you've solved the problem, it is recommended that "DEBUG mode" be turned off again
        (just remove the line from the file and re-upload it).
    </p>
</div>
</body>
</html>
