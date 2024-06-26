<?php

declare(strict_types=1);

if (!isset($this)) {
    die;
}

/** @var string $basePath */
/** @var callable $trans */
/** @var string $menu */
/** @var ?string $content */
/** @var ?string $errorMessage */
/** @var array $flashMessages */

?>
<html>
<head>
    <meta charset="utf-8">
    <title><?php echo $trans('Admin panel'), S2_SITE_NAME ? ' - ' . S2_SITE_NAME : ''; ?></title>
    <meta name="ROBOTS" content="NOINDEX, NOFOLLOW">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?php echo $basePath; ?>/_vendor/s2/admin-yard/demo/style.css">
    <link rel="stylesheet" href="css/admin-override.css">

    <script src="<?php echo $basePath; ?>/_vendor/s2/admin-yard/demo/script.js"></script>
    <script src="js/lib.js"></script>

    <!-- note: move to editor pages only -->
    <script src="js/s2lib.js"></script>
    <script src="js/conversion.js"></script>
    <script src="js/optipng-setup.js"></script>

    <script src="lang/<?php echo Lang::admin_code(); ?>/ui.js"></script>
    <style>:root {
            --page-secondary-background: <?php echo S2_ADMIN_COLOR; ?>;
        }</style>
</head>
<body>
<div id="loading"></div>
<nav>
    <?= $menu ?>
    <ul class="main-menu-list">
        <li class="main-menu-item"><a class="main-menu-link" href="?action=logout"><?= $trans('Logout') ?></a></li>
    </ul>
</nav>
<?php
foreach ($flashMessages as $type => $messages) {
    foreach ($messages as $message) {
        ?>
        <div class="flash-message flash-<?= $type ?>">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="flash-message-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
        <?php
    }
}
?>
<?php if ($content === null && isset($errorMessage)): ?>
    <article class="error">
        <h1><?= $trans('An error encountered') ?></h1>
        <div class="error-message-box"><p><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p></div>
    </article>
<?php else: ?>
    <article class="admin-content"><?= $content ?></article>
<?php endif; ?>
    <dialog id="error-dialog">
        <h1><?= $trans('An error encountered') ?></h1>
        <p><iframe id="error-iframe" src="about:blank"></iframe></p>
        <button class="primary" id="error-dialog-close"><?= $trans('Close') ?></button>
    </dialog>
</body>
</html>
