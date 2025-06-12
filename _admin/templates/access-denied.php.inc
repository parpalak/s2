<?php

/** @var callable $trans */
/** @var callable $param */
/** @var string $locale */
/** @var string $basePath */

?>
<!DOCTYPE html>
<html lang="<?= $locale ?>">
<head>
    <meta charset="utf-8">
    <title><?= $trans('Admin panel'), $param('S2_SITE_NAME') ? ' - ' . $param('S2_SITE_NAME') : '' ?></title>
    <meta name="ROBOTS" content="NOINDEX, NOFOLLOW">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= $basePath ?>/_vendor/s2/admin-yard/demo/style.css">
    <link rel="stylesheet" href="<?= $basePath ?>/_admin/css/admin-override.css">
    <style>:root {
            --page-secondary-background: <?= $param('S2_ADMIN_COLOR') ?>;
        }</style></head>
<body>
<article>
    <h1><?= $trans('Access denied') ?></h1>
    <p><?= $trans('You do not have permission to access this page.') ?></p>
</article>
</body>
</html>
