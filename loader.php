<?php

require_once("process.php");
require_once("runner.php");
require_once("builtin.php");

function loadFile($file) {
    $path = new SplFileInfo($file);
    $realPath = $path->getRealPath();

    if (is_dir($realPath)) {
        $testPath = $realPath . "/index.js";
        if (!file_exists($testPath)) {
            $testPath = $realPath . "/index.ts";
        }

        if (file_exists($testPath)) {
            $realPath = $testPath;
        }
    }

    if (!file_exists($realPath)) {
        throw new Exception("Cannot find file '$file' => '$realPath'");
    }

    $contents = file_get_contents($realPath);

    return $contents;
}

function processFile($file, $context = array()) {
    $realPath = null;
    $modulePath = null;
    if ($file[0] === '/') {
        $realPath = $file;
        $modulePath = $file;
    } else if ($file[0] == ".") {
        $path = $file;
        if (array_key_exists("dir", $context)) {
            $path = $context['dir'] . "/" . $file;
        }
        $path = new SplFileInfo($path);
        $realPath = $path->getRealPath();
        $modulePath = $realPath;
    } else {
        if (array_key_exists($file, BUILTIN_MODULES)) {
            $exports = array();
            $builtin = BUILTIN_MODULES[$file];
            if (array_key_exists("functions", $builtin)) {
                foreach ($builtin['functions'] as $key=>$value) {
                    $exports[] = array(
                        "type" => "export",
                        "export" => array(
                            "type" => "function",
                            "name" => $key,
                            "data" => $value,
                        ),
                    );
                }
            }
            return array(
                "file" => $file,
                "contents" => $exports,
            );
        }

        // it's a module we need to load
        if (!array_key_exists("dir", $context)) {
            throw new Exception("Attempting to load a module but we have no directory set in the context");
        }

        $packagePath = $context['dir'] . "/node_modules/$file/package.json";
        if (!file_exists($packagePath)) {
            throw new Exception("Can't find module in $packagePath");
        }
        $packageString = file_get_contents($packagePath);
        $packageData = json_decode($packageString, true);
        if (!array_key_exists("main", $packageData)) {
            throw new Exception("Module $file does not have a 'main' field in package.json");
        }
        $realPath = $context['dir'] . "/node_modules/$file/" . $packageData['main'];
        $modulePath = $file;
    }

    $lines = loadFile($realPath);
    $result = processCode($lines, array("file" => $realPath));
    $tree = $result[0];

    $context = array(
        "file" => $realPath,
    );
    $contents = execute($tree, $context);

    return array(
        "file" => $realPath,
        "contents" => $contents,
    );
}

?>