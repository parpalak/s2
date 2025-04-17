<?php

if (!empty($_SERVER['HTTP_X_TEST'])) {
    header('X-Test: ' . $_SERVER['HTTP_X_TEST']);
    header('x-test-2: ' . $_SERVER['HTTP_X_TEST']);
}

echo 'Success!';
