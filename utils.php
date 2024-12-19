<?php

function getValue($param) {
    if ($param['type'] === "string") {
        return $param['data'];
    } else if ($param['type'] === "number") {
        return $param['data'];
    } else {
        throw new Exception("Invalid value type \"{$param['type']}\"");
    }
}

$LOG = false;

function _log($str) {
    global $LOG;
    if ($LOG) {
        print("LOG ". $str . "\n");
    }
}

function _trace() {
    debug_print_backtrace();
}

?>
