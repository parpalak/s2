<?php

/** @var callable $trans */
/** @var callable $param */
/** @var string $basePath */
/** @var string $challenge */
/** @var string $salt */
/** @var string $errorMessage */

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $trans('Admin panel'), $param('S2_SITE_NAME') ? ' - ' . $param('S2_SITE_NAME') : ''; ?></title>
    <meta name="ROBOTS" content="NOINDEX, NOFOLLOW">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= $basePath ?>/_admin/css/admin-override.css">
    <script src="<?= $basePath ?>/_admin/js/lib.js"></script>
</head>
<body class="login_page" onload="LoginInit();">
<div id="login_wrap">
    <noscript><p><?php echo $trans('Noscript'); ?></p></noscript>
    <form name="loginform" class="loginform" method="post" action="" data-salt="<?php echo $salt ?>"
          onsubmit="SendLoginForm(); return false;">
        <p>
            <label>
                <span><?php echo $trans('Login'); ?></span>
                <input type="text" name="login" size="30" maxlength="255">
            </label>
            <label>
                <span><?php echo $trans('Password'); ?></span>
                <input type="password" name="pass" size="30" maxlength="255" disabled="disabled">
            </label>
        </p>
        <p>
            <input type="submit" name="button" value="<?php echo $trans('Log in'); ?>"/>
            <input type="hidden" name="key" value=""/>
            <input type="hidden" name="challenge" value="<?php echo $challenge ?>"/>
        </p>
    </form>
    <p id="message" class="message"><?= htmlentities($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
</div>
</body>
</html>
