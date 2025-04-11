<?php

$contentType = $_SERVER['CONTENT_TYPE'];

if (!str_contains($contentType, 'application/json')) {
    header('HTTP/1.1 400 Bad Request');
    exit;
}

header('Content-Type: application/json');

echo file_get_contents('php://input');
