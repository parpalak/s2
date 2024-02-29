<?php if (!defined('S2_ROOT')) die; ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <meta name="Generator" content="S2 <?php echo S2_VERSION; ?>" />
    <title>Error - <?php echo defined('S2_SITE_NAME') ? s2_htmlencode(S2_SITE_NAME) : 'S2'; ?></title>
    <style>
        body {
            margin: 40px;
            font: 16px/1.5 Helvetica, Arial, sans-serif;
            color: #333;
        }
        p {
            max-width: 40em;
        }
    </style>
</head>
<body>
<div class="container">
    <h1><?php echo Lang::get('Error encountered') ?: 'An error was encountered'; ?></h1>

    <p>
        Please refer to logs to find out the cause.
    </p>

    <p>
        <strong>Note:</strong> For detailed error information (necessary for troubleshooting), enable "DEBUG mode".
        To enable "DEBUG mode", open up the file config.php in a text editor, add a line that looks like
        <code>define('S2_DEBUG', 1);</code> and re-upload the file.
        Once you've solved the problem, it is recommended that "DEBUG mode" be turned off again
        (just remove the line from the file and re-upload it).
    </p>
</div>
</body>
</html>
