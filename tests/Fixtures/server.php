<?php

if ($_SERVER['REQUEST_URI'] === '/slow') {
    sleep(3);
}

header('Content-Type: text/html; charset=utf-8');

readfile(__DIR__.'/document.html');
