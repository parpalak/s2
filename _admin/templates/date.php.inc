<?php

if (!isset($this)) {
    die;
}
if ($value !== null) {
    echo date('Y-m-d', strtotime($value));
} else {
    echo '<span class="null">null</span>';
}
