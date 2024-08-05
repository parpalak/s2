<?php

/** @var callable $trans */
/** @var string $locale */
/** @var string $basePath */

?>
<section class="admin-structure">
    <div class="toolbar">
        <img class="expand" src="i/1.gif" onclick="OpenAll(); return false;"
             alt="<?php echo $trans('Expand'); ?>"/>
        <img class="separator" src="i/1.gif" alt=""/>
        <img class="collapse" src="i/1.gif" onclick="CloseAll(); return false;"
             alt="<?php echo $trans('Collapse'); ?>"/>
        <img class="separator" src="i/1.gif" alt=""/>
        <img class="refresh" src="i/1.gif" alt="<?php echo $trans('Refresh'); ?>"/>
        <input type="search" name="search" id="search_field" placeholder="<?php echo $trans('Search'); ?>"/>
    </div>
    <span id="context_buttons">
        <a href="#" class="context-link edit" title="<?php echo $trans('Edit'); ?>"><img id="context_edit" src="i/1.gif" alt=""></a>
        <a href="#" class="context-link comments" title="<?php echo $trans('Comments'); ?>"><img id="context_comments" src="i/1.gif" alt=""></a>
        <img id="context_add" src="i/1.gif" alt="<?= $trans('Create subarticle') ?>" title="<?= $trans('Create subarticle') ?>">
        <img id="context_delete" src="i/1.gif" alt="<?php echo $trans('Delete'); ?>" title="<?php echo $trans('Delete'); ?>">
    </span>
    <div id="tree" class="treetree" tabindex="0"></div>
</section>
<script src="<?php echo $basePath; ?>/_admin/js/ajax.js"></script>
<script src="<?php echo $basePath; ?>/_admin/lang/<?php echo $locale; ?>/ui.js"></script>
<script src="<?php echo $basePath; ?>/_admin/js/jquery.js"></script>
<script src="<?php echo $basePath; ?>/_admin/js/jquery-tools.js"></script>
<script src="<?php echo $basePath; ?>/_admin/js/jquery.jstree.js"></script>
<script src="<?php echo $basePath; ?>/_admin/js/structure.js"></script>
<script>
    var sUrl = '<?php echo $basePath; ?>/_admin/ajax.php?';
    var new_page_pos = <?php echo json_encode(S2_ADMIN_NEW_POS); ?>;
</script>
