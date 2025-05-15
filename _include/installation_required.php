<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   s2
 *
 * @var string $installationPath
 * @var string $configFilename
 */

declare(strict_types=1);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>S2 Setup Required</title>
    <style>
        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            font-size: 1rem;
            color: #000;
            background: #fffdf5;
            margin: 0;
            padding: 0;
            line-height: 1.5;
            max-width: inherit;
        }
        .card {
            max-width: 580px;
            margin: 3em auto;
            padding: 1em 1.5em;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px -3px rgba(0, 0, 0, 0.2);
        }
        h1 {
            color: #007093;
            font-size: 2.5em;
            margin: 0 0 0.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .icon {
            width: 36px;
            height: 36px;
        }
        a.link-button.main-button {
            background: #bde4ed;
            background: linear-gradient(to bottom, #ccf6ff 0%, #a7dde5 100%);
            border-color: #54b2bf;
        }
        a.link-button.main-button:hover {
            background: #bde4ed;
            background: linear-gradient(to bottom, #b4e4ee 0%, #8dcfd9 100%);
        }
        a.link-button {
            font-size: 1.25em;
            color: #000;
            text-decoration: none;
            border: 1px solid #999;
            border-radius: 4px;
            padding: 0.375em 0.75em;
            box-shadow: 0 2px 0 rgba(255, 255, 255, 0.2) inset, 0 2px 2px rgba(0, 0, 0, 0.1);
            cursor: pointer;
        }
        .help {
            margin-top: 2em;
            padding-top: 1.5em;
            border-top: 1px solid rgba(0,0,0, 0.15);
            font-size: 0.9em;
            color: rgba(0,0,0, 0.6);
        }
        p, ul {
            margin: 0 0 0.75em;
        }
        ul {
            padding: 0 0 0 1em;
        }
        li {
            margin: 0.25em 0;
        }
        p code, li code {
            background: rgba(0,0,0, 0.06);
            padding: 2px 3px;
            border-radius: 3px;
            font-family: 'Menlo', monospace;
            font-size: 0.9em;
        }
        pre {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 4px;
            border-left: 3px solid #54b2bf;
            overflow-x: auto;
            margin: 0.5em 0 1em;
        }
        .toggle-config {
            color: #05c;
            cursor: pointer;
            display: inline-block;
            text-decoration: underline;
            text-decoration-style: dashed;
            text-decoration-color: rgba(0, 85, 204, 0.5);
        }
        .config-example {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }
        .config-example.expanded {
            max-height: 1000px;
        }
        .link-button {
            transition: all 0.2s ease;
        }
    </style>
</head>
<body>
<div class="card">
    <h1>S2 Setup Required <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" aria-label="Warning" role="img">
            <path fill="#ffce31" d="M5.9 62c-3.3 0-4.8-2.4-3.3-5.3L29.3 4.2c1.5-2.9 3.9-2.9 5.4 0l26.7 52.5c1.5 2.9 0 5.3-3.3 5.3H5.9z"/>
            <g fill="#231f20">
                <path d="m27.8 23.6 2.8 18.5c.3 1.8 2.6 1.8 2.9 0l2.7-18.5c.5-7.2-8.9-7.2-8.4 0"/>
                <circle cx="32" cy="49.6" r="4.2"/>
            </g>
        </svg>
    </h1>

    <p>
        <strong>The configuration file (<code><?=$configFilename?></code>) is missing or corrupted.</strong><br>
        This could mean either:
    </p>

    <ul>
        <li>S2 hasn't been installed yet, <strong>or</strong></li>
        <li>The config file was accidentally deleted after setup.</li>
    </ul>

    <div style="margin: 1.5rem 0;">
        <a href="<?=$installationPath?>" class="link-button main-button">Run Installation</a>
    </div>

    <div class="help">
        <p><strong>Need help?</strong></p>
        <ul>
            <li>If you already installed S2, restore <code><?=$configFilename?></code> from a backup.</li>
            <li>Or you can create the file manually using <span class="toggle-config">this template</span></li>
        </ul>

        <div class="config-example" id="configExample">
            <pre><code>&lt;?php

$db_type = 'mysql';
$db_host = '127.0.0.1';
$db_name = 's2_test';
$db_username = 'root';
$db_password = '';
$db_prefix = '';
$p_connect = false;

define('S2_BASE_URL', 'https://example.com/my_site');
define('S2_PATH', '/my_site');
define('S2_URL_PREFIX', ''); // or '/?', '/index.php', '/index.php?'

$s2_cookie_name = 's2_cookie_82378103978'; // some random string
</code></pre>
        </div>
    </div>
</div>

<script>
    document.querySelector('.toggle-config').addEventListener('click', function() {
        document.getElementById('configExample').classList.toggle('expanded');
    });
</script>
</body>
</html>
