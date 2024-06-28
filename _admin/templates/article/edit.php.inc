<?php

declare(strict_types=1);

/** @var string $basePath */
/** @var string $previewUrl */
/** @var string $templateContent */
/** @var array $tagsList */
/** @var array $templateList */
/** @var array $statusData Info about URL status */
/** @var callable $trans */
/** @var string $title */
/** @var string $entityName */
/** @var array $header */
/** @var array $errorMessages */
/** @var \S2\AdminYard\Form\Form $form */
/** @var string $csrfToken */
/** @var array $primaryKey */
/** @var array $actions */

$formQueryParams = http_build_query(array_merge([
    'entity' => $entityName,
    'action' => 'edit'
], $primaryKey));

$errorMessages = array_merge($errorMessages, $form->getGlobalFormErrors());

$columnMap = [
    'excerpt'     => ['column' => 1, 'label_area' => '3/1/4/2', 'input_area' => '3/2/4/5'],
    'pagetext'    => ['column' => 1, 'input_area' => '4/1/5/5'],
    'user_id'     => ['column' => 2,],
    'template'    => ['column' => 2,],
    'create_time' => ['column' => 2,],
    'modify_time' => ['column' => 2,],
    'favorite'    => ['column' => 2,],
    'commented'   => ['column' => 2,],
    'url'         => ['column' => 2,],
    'published'   => ['column' => 2,],
];

$visibleControls = $form->getVisibleControls();

/** @var array<string,\S2\Cms\Framework\ControllerInterface>[] $columns */
$columns = [1 => [], 2 => []];
foreach ($columnMap as $fieldName => $columnInfo) {
    $columns[$columnInfo['column']][$fieldName] = $visibleControls[$fieldName];
    unset($visibleControls[$fieldName]);
}

$columns[1] = array_merge($columns[1], $visibleControls);

function s2_jsvarencode ($var)
{
    return str_replace(array('\\', '\'', '</script>', "\n", "\r"), array('\\\\', '\\\'', '</scr\' + \'ipt>', "\\\n", '') , $var);
}

?>
<link rel="stylesheet" href="<?php echo $basePath; ?>/_extensions/s2_highlight/codemirror.css"/>
<link rel="stylesheet" href="<?php echo $basePath; ?>/_extensions/s2_highlight/codemirror/foldgutter.css"/>
<link rel="stylesheet" href="<?php echo $basePath; ?>/_extensions/s2_highlight/codemirror/dialog.css"/>
<link rel="stylesheet" href="<?php echo $basePath; ?>/_extensions/s2_highlight/codemirror/matchesonscrollbar.css"/>

<script src="<?php echo $basePath; ?>/_extensions/s2_highlight/codemirror/codemirror.min.js"></script>
<script src="<?php echo $basePath; ?>/_extensions/s2_highlight/codemirror/selection-pointer.min.js"></script>
<script src="<?php echo $basePath; ?>/_extensions/s2_highlight/codemirror/xml.min.js"></script>
<script src="<?php echo $basePath; ?>/_extensions/s2_highlight/codemirror/javascript.min.js"></script>
<script src="<?php echo $basePath; ?>/_extensions/s2_highlight/codemirror/css.min.js"></script>
<script src="<?php echo $basePath; ?>/_extensions/s2_highlight/codemirror/htmlmixed.min.js"></script>
<script src="<?php echo $basePath; ?>/_extensions/s2_highlight/codemirror/clike.min.js"></script>
<script src="<?php echo $basePath; ?>/_extensions/s2_highlight/codemirror/php.min.js"></script>
<script src="<?php echo $basePath; ?>/_extensions/s2_highlight/codemirror/foldcode.js"></script>
<script src="<?php echo $basePath; ?>/_extensions/s2_highlight/codemirror/foldgutter.js"></script>
<script src="<?php echo $basePath; ?>/_extensions/s2_highlight/codemirror/xml-fold.js"></script>
<script src="<?php echo $basePath; ?>/_extensions/s2_highlight/codemirror/annotatescrollbar.js"></script>
<script src="<?php echo $basePath; ?>/_extensions/s2_highlight/codemirror/dialog.js"></script>
<script src="<?php echo $basePath; ?>/_extensions/s2_highlight/codemirror/jump-to-line.js"></script>
<script src="<?php echo $basePath; ?>/_extensions/s2_highlight/codemirror/matchesonscrollbar.js"></script>
<script src="<?php echo $basePath; ?>/_extensions/s2_highlight/codemirror/search.js"></script>
<script src="<?php echo $basePath; ?>/_extensions/s2_highlight/codemirror/searchcursor.js"></script>
<script src="<?php echo $basePath; ?>/_admin/templates/article/codemirror_init.js"></script>

<section class="edit-content large-edit-form article-edit-content">
    <?php if (!empty($errorMessages)): ?>
        <div class="error-message-box">
            <p><?php printf($trans('Cannot save %s due to the following errors:'), $entityName) ?></p>
            <ul class="error-messages">
                <?php foreach ($errorMessages as $message): ?>
                    <li><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <form name="article-form" method="POST" action="?<?= $formQueryParams ?>">
        <?php foreach ($form->getHiddenControls() as $control): ?>
            <?= $control->getHtml() ?>
        <?php endforeach; ?>
        <div class="large-form-wrapper">
            <?php foreach ($columns as $columnIndex => $controls): ?>
                <div class="article-form-column article-form-column-<?= $columnIndex ?>">
                    <?php foreach ($controls as $fieldName => $control): ?>
                        <?php if ($control instanceof \S2\AdminYard\Form\Checkbox): ?>
                            <div
                                    style="grid-area: <?= $columnMap[$fieldName]['input_area'] ?? 'auto' ?>"
                                    class="article-form-control form-control-<?= strtolower(basename(strtr(get_class($control), ['\\' => '/']))) ?> field-<?= $fieldName ?>">
                                <label class="field-name"
                                       style="grid-area: <?= $columnMap[$fieldName]['label_area'] ?? 'auto' ?>"
                                       for="id-<?= $fieldName ?>">
                                    <?= $control->getHtml('id-' . $fieldName) ?>
                                    <?= htmlspecialchars($trans($header[$fieldName]), ENT_QUOTES, 'UTF-8') ?>
                                </label>
                                <?php foreach ($control->getValidationErrors() as $error): ?>
                                    <span class="validation-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endforeach; ?>
                                <?php if ($fieldName === 'commented') {
                                    echo '<a href="?' . http_build_query([
                                            'entity' => 'Comment',
                                            'action' => 'list',
                                            'article_id' => $primaryKey['id']
                                        ]) . '">' . $trans('Comments') . ' &rarr;</a>';
                                } ?>
                            </div>
                        <?php elseif ($control instanceof \S2\Cms\AdminYard\Form\HtmlTextarea): ?>
                            <div style="grid-area: <?= $columnMap[$fieldName]['input_area'] ?? 'auto' ?>" id="id-article-editor-block"
                                 class="article-form-control form-control-<?= strtolower(basename(strtr(get_class($control), ['\\' => '/']))) ?> field-<?= $fieldName ?>">
                                <?= $control->getHtmlWithWrapper($trans, 'id-' . $fieldName) ?>
                            </div>
                        <?php else: ?>
                            <label class="field-name"
                                   style="grid-area: <?= $columnMap[$fieldName]['label_area'] ?? 'auto' ?>"
                                   for="id-<?= $fieldName ?>"><?= htmlspecialchars($trans($header[$fieldName]), ENT_QUOTES, 'UTF-8') ?></label>
                            <div style="grid-area: <?= $columnMap[$fieldName]['input_area'] ?? 'auto' ?>"
                                 class="article-form-control form-control-<?= strtolower(basename(strtr(get_class($control), ['\\' => '/']))) ?> field-<?= $fieldName ?>">
                                <?= $control->getHtml('id-' . $fieldName) ?>
                                <?php foreach ($control->getValidationErrors() as $error): ?>
                                    <span class="validation-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($columnIndex === 2): ?>
                        <p class="article-form-buttons">
                            <button type="submit"><?= $trans('Save') ?></button>
                        </p>
                        <hr>
                        <p>
                            <a target="_blank" data-href="<?= $previewUrl ?>" href="<?= $previewUrl ?>" id="preview_link"><?= $trans('Preview ready') ?></a>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <datalist id="article-templates-datalist">
            <?php foreach ($templateList as $templateItem): ?>
                <option value="<?= htmlspecialchars($templateItem, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($templateItem, ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?></datalist>
    </form>
</section>
<dialog id="picture_dialog">
    <button class="picture-dialog-close" onclick="this.parentNode.close()">&times;</button>
    <iframe src="" frameborder="0" id="picture_frame" name="picture_frame"></iframe>
</dialog>

<script>
    const template = '<?php echo s2_jsvarencode($templateContent); ?>';
    document.forms['article-form'].elements['template'].setAttribute('list', 'article-templates-datalist');
    document.forms['article-form'].elements['template'].setAttribute('placeholder', '<?= $trans('inherited') ?>');
    document.addEventListener('DOMContentLoaded', () => {
        initArticleEditForm(document.forms['article-form'], <?= json_encode($statusData) ?>, 'Article', 'pagetext');
    })

    function ReturnImage(s, w, h) {
        document.dispatchEvent(new CustomEvent('return_image.s2', {detail: {file_path: s, width: w, height: h}}));
    }

    function ClosePictureDialog() {
        document.getElementById('picture_dialog').close();
    }
</script>
<script src="<?php echo $basePath; ?>/_admin/js/autoComplete.min.js"></script>
<script>
    initTagsAutocomplete('id-tags', <?= json_encode($tagsList) ?>);
</script>
