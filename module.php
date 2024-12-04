<?php

require_once("loader.php");

$modules = array();

function getModule($file) {
    global $modules;
    if (!array_key_exists($file, $modules)) {
        return null;
    }

    return $modules[$file];
}

function saveModule($file, $results) {
    global $modules;
    $modules[$file] = $results;
}

?>