<?php

$jsvarencode = static function ($var) {
    return str_replace(array('\\', '\'', '</script>', "\n", "\r"), array('\\\\', '\\\'', '</scr\' + \'ipt>', "\\\n", ''), $var);
};

$bytesFromString = static function ($val)
{
    $val  = trim($val);
    $last = strtolower(substr($val, -1));
    $val  = substr($val, 0, -1);
    switch ($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }

    return $val;
};

$maxFileSize = $bytesFromString(ini_get('upload_max_filesize'))

/** @var string $basePath */
/** @var string $locale */
/** @var callable $trans */
/** @var callable $param */
/** @var callable $isGranted */
/** @var callable $friendlyFilesize */

/** @var string $imagePath */


?>
<!DOCTYPE html>
<html lang="<?= $locale ?>">
<head>
    <meta charset="utf-8">
    <title><?php echo $trans('Pictures'); ?></title>
    <link rel="stylesheet" href="<?php echo $basePath; ?>/_vendor/s2/admin-yard/demo/style.css">
    <link rel="stylesheet" href="<?php echo $basePath; ?>/_admin/css/admin-override.css"/>
    <link rel="stylesheet" href="<?php echo $basePath; ?>/_admin/css/pictures.css"/>
    <script src="<?php echo $basePath; ?>/_admin/js/lib.js"></script>
    <script src="<?php echo $basePath; ?>/_admin/js/ajax.js"></script>
    <script>
        var sUrl = '<?php echo $basePath; ?>/_admin/ajax.php?';
        var sPicturePrefix = '<?php echo $imagePath; ?>';
        var iMaxFileSize = <?php echo $maxFileSize; ?>;
        var sFriendlyMaxFileSize = '<?php echo $friendlyFilesize($maxFileSize); ?>';
    </script>
    <style>:root {
            --page-secondary-background: <?= $param('S2_ADMIN_COLOR') ?>;
        }</style>
    <script src="<?php echo $basePath; ?>/_admin/js/jquery.js"></script>
    <script src="<?php echo $basePath; ?>/_admin/js/jquery-tools.js"></script>
    <script src="<?php echo $basePath; ?>/_admin/js/jquery.jstree.js"></script>
    <script src="<?php echo $basePath; ?>/_admin/js/pictman.js"></script>
    <script src="<?php echo $basePath; ?>/_admin/lang/<?php echo $locale; ?>/ui.js"></script>
</head>

<body>
<div id="tree_div">
    <div class="treetree">
        <div id="fupload">
            <div id="loading_pict" class="loading-indicator"></div>

            <?php

            if ($isGranted(\S2\Cms\Model\PermissionChecker::PERMISSION_CREATE_ARTICLES)) {
                ?>
                <form id="uploadForm" enctype="multipart/form-data"
                      action="<?php echo $basePath; ?>/_admin/ajax.php?action=upload" method="post">
                    <?php echo $trans('Upload'); ?> <?php echo $trans('Upload to'); ?> <span
                        id="fold_name"><strong><?php echo $trans('Pictures'); ?></strong></span>
                    <input name="pictures[]" multiple="multiple" min="1" max="999" size="20" type="file"
                           onchange="UploadChange(this);"/><br/>
                    <?php printf($trans('Upload limit'), $friendlyFilesize($maxFileSize), $friendlyFilesize($bytesFromString(ini_get('post_max_size')))) ?>
                    <br/>
                    <input type="hidden" name="dir" value=""/>
                </form>
                <?php
            }

            ?>
        </div>
        <div id="folders" tabindex="0"></div>
        <div id="finfo"></div>
    </div>
    <div id="file-wrap">
        <div id="brd">
            <div id="files" tabindex="0"></div>
            <div id="loadstatus"></div>
        </div>
    </div>
    <dialog id="error-dialog">
        <h1><?= $trans('An error encountered') ?></h1>
        <p><iframe id="error-iframe" src="about:blank"></iframe></p>
        <button class="primary" id="error-dialog-close"><?= $trans('Close') ?></button>
    </dialog>
</div>
</body>
</html>
