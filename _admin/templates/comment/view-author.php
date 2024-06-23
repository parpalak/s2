<?php

echo htmlspecialchars($row['column_nick'], ENT_QUOTES, 'UTF-8'), '<br>';
echo htmlspecialchars($row['column_email'], ENT_QUOTES, 'UTF-8');

if (!$row['column_show_email']) {
    echo ' <i class="icon icon-hidden" title="' . htmlspecialchars($trans('Hidden'), ENT_QUOTES, 'UTF-8') . '"></i>';
}
if ($row['column_subscribed']) {
    echo ' <i class="icon icon-bell" title="' . htmlspecialchars($trans('Subscribed'), ENT_QUOTES, 'UTF-8') . '"></i>';
}
