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

function dotenv_config() {
    print "STUB dotenv_config\n";
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
        "functions" => array(
            "config" => "dotenv_config",
        ),
    ),
    "express" => array(
        "functions" => array(
            "default" => "express_default",
        ),
    ),
    "cors" => array(
        "functions" => array(
            "default" => "cors_default",
        ),
    ),
    "path" => array(
        "functions" => array(
            "default" => "path_default",
        ),
    ),
);

?>