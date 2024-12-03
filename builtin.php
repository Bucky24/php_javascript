<?php

function console_log() {
    $params = func_get_args();
    print(implode(" ", $params) . "\n");
}

const BUILTIN_CLASSES = array(
    "console" => array(
        "functions" => array(
            "log" => "console_log",
        ),
    ),
);

?>