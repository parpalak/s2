<?php

if ($row['virtual_current'] === '1') {
    echo $trans('Current session');
} else {
    require __DIR__ . '/../../../_vendor/s2/admin-yard/templates/list-actions.php.inc';
}
