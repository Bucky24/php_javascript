<?php

require_once("loader.php");
require_once("process.php");
require_once("runner.php");

$index_file = $argv[1];

$lines = loadFile($index_file);
$result = processCode($lines);
$tree = $result[0];

execute($tree);

?>