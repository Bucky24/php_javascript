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

?>
