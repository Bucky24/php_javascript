<?php

require_once("utils.php");

function console_log() {
    $params = func_get_args();
    $output = array();
    foreach ($params as $param) {
        $output[] = getValue($param);
    }
    print(implode(" ", $output) . "\n");
}

const BUILTIN_CLASSES = array(
    "console" => array(
        "functions" => array(
            "log" => "console_log",
        ),
    ),
);

const BUILTIN_MODULES = array(
    "dotenv" => array(

    ),
);

?>