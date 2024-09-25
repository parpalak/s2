<?php

$htmlEncode = static fn(string $str) => htmlspecialchars($str, ENT_QUOTES, 'UTF-8');

/** @var array $availableExtensions */
/** @var array $failedExtensions */
/** @var array $installedExtensions */
/** @var callable $trans */
/** @var callable $csrfTokenGenerator */
/** @var string $basePath */
?>
<section class="admin-extensions">
    <h2><?= $trans('Extensions available') ?></h2>

    <?php if (count($availableExtensions) > 0): ?>
        <div class="extensions">
            <?php foreach ($availableExtensions as $item): ?>
                <div class="extension available">
                    <div class="info">
                        <h3 title="<?= $htmlEncode($item['entry']) ?>"><?= $htmlEncode($item['title']) ?> <?= sprintf($trans('Version'), $htmlEncode($item['version'])) ?></h3>
                        <p><?= sprintf($trans('Extension by'), $htmlEncode($item['author'])) ?></p>
                    </div>
                    <?php if ($item['description']): ?>
                        <p class="description"><?= $htmlEncode($item['description']) ?></p>
                    <?php endif; ?>
                    <div class="options">
                        <button
                                data-csrf-token="<?= $csrfTokenGenerator($item['entry']) ?>"
                                data-id="<?= $htmlEncode($item['entry']) ?>"
                                class="primary <?= isset($item['installed_version']) ? 'upgr_ext' : 'inst_ext' ?>"
                                onclick="return changeExtension('install_extension', '<?= $htmlEncode(addslashes($item['entry'])) ?>', '<?= $csrfTokenGenerator($item['entry']) ?>', '<?= implode('\\n', array_map(static fn($s) => s2_htmlencode(addslashes($s)), $item['install_notes'])) ?>');"
                        >
                            <?= isset($item['installed_version']) ? $trans('Upgrade extension') . ' <span class="icon icon-warning"></span>'  : $trans('Install extension') ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="info-box">
            <p><?= $trans('No available extensions') ?></p>
        </div>
    <?php endif; ?>

    <?php if (count($failedExtensions) > 0): ?>
        <div class="info-box">
            <p class="important"><?= $trans('Invalid extensions') ?></p>
        </div>
        <div class="extensions">
            <?php foreach ($failedExtensions as $error): ?>
                <div class="extension error">
                    <h3><?= $htmlEncode($error['error']) ?></h3>
                    <p><?= $error['message'] ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h2><?= $trans('Installed extensions') ?></h2>

    <?php if (count($installedExtensions) > 0): ?>
        <div class="info-box">
            <p class="important"><?= $trans('Installed extensions warn') ?></p>
        </div>
        <div class="extensions">
            <?php foreach ($installedExtensions as $ext): ?>
                <div class="extension <?= $ext['disabled'] == '1' ? 'disabled' : 'enabled' ?>">
                    <div class="info">
                        <h3 title="<?= $htmlEncode($ext['id']) ?>"><?= $htmlEncode($ext['title']) ?> <?= sprintf($trans('Version'), $htmlEncode($ext['version'])) ?></h3>
                        <p><?= sprintf($trans('Extension by'), $htmlEncode($ext['author'])) ?></p>
                    </div>
                    <?php if ($ext['description']): ?>
                        <p class="description"><?= $htmlEncode($ext['description']) ?></p>
                    <?php endif; ?>
                    <div class="options">
                        <button class="link-as-button secondary flip_ext"
                                onclick="return changeExtension('flip_extension', '<?= $htmlEncode(addslashes($ext['id'])) ?>', '<?= $csrfTokenGenerator($ext['id']) ?>', '');">
                            <?= $ext['disabled'] != '1' ? $trans('Disable') : $trans('Enable') ?>
                        </button>
                        <button class="link-as-button danger uninst_ext"
                                onclick="return changeExtension('uninstall_extension', '<?= $htmlEncode(addslashes($ext['id'])) ?>', '<?= $csrfTokenGenerator($ext['id']) ?>', '<?= $htmlEncode(addslashes($ext['uninstall_note'] ?? '')) ?>');">
                            <?= $trans('Uninstall') ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="info-box">
            <p><?= $trans('No installed extensions') ?></p>
        </div>
    <?php endif; ?>
</section>
<script src="<?php echo $basePath; ?>/_admin/js/extensions.js"></script>
<script>
    var sUrl = '<?php echo $basePath; ?>/_admin/ajax.php?';
</script>
