<?php

function loadFile($file) {
    $path = new SplFileInfo($file);
    $realPath = $path->getRealPath();

    if (!file_exists($realPath)) {
        throw new Exception("Cannot find file '$realPath'");
    }

    $contents = file_get_contents($realPath);

    return $contents;
}

?>