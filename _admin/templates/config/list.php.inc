<?php

require __DIR__ . '/../../../_vendor/s2/admin-yard/templates/list.php.inc';

?>
<script src="js/ajax.js"></script>
<script>
    document.querySelector('.field-Config-value input[type="color"]').addEventListener('change', (event) => {
        document.documentElement.style.setProperty('--page-secondary-background', event.target.value);
    })
</script>
