<?php

$seconds = $_GET['time'] ?: 1;
sleep($seconds);

echo 'Slept for ' . $seconds . ' seconds.';
