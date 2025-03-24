<?php

$redirects = $_GET['redirects'] ?? 0;

if ($redirects > 0) {
    header('HTTP/1.1 302 Found');
    header('Location: /_tests/_resources/http_client_mocks/multiredirect.php?redirects=' . ($redirects - 1));
    return;
}

echo 'Redirected!';
