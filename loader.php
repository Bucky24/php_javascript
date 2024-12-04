<?php

require_once("process.php");
require_once("runner.php");

function loadFile($file) {
    $path = new SplFileInfo($file);
    $realPath = $path->getRealPath();

    if (!file_exists($realPath)) {
        throw new Exception("Cannot find file '$file' => '$realPath'");
    }

    $contents = file_get_contents($realPath);

    return $contents;
}

function processFile($file) {
    $lines = loadFile($file);
    $result = processCode($lines);
    $tree = $result[0];

    return execute($tree);
}

?>