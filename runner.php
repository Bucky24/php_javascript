<?php

include_once("types.php");
include_once("builtin.php");

function getChildOfType($statement, $type) {
    if (!array_key_exists('children', $statement)) {
        return null;
    }
    foreach ($statement['children'] as $child) {
        if ($child['state'] == $type) {
            return $child;
        }
    }

    return null;
}

function getClassByName($name) {
    if (array_key_exists($name, BUILTIN_CLASSES)) {
        return BUILTIN_CLASSES[$name];
    }

    return null;
}

function getFunctionByName($name, $context = null) {
    if ($name['state'] == NESTED_PATH) {
        $path = $name['path'];
        if (count($path) > 1) {
            $class = getClassByName($path[0], $context);
            return getFunctionByName(array(
                "state" => NESTED_PATH,
                "path" => array_slice($path, 1),
            ), $class);
        } else {
            if (array_key_exists($path[0], $context['functions'])) {
                return $context['functions'][$path[0]];
            }
            throw new Exception("Function {$path[0]} was not found");
        }
    }
}

function execute($tree) {
    $currentStatement = 0;
    $results = array();
    while ($currentStatement < count($tree)) {
        $statement = $tree[$currentStatement];

        if ($statement['state'] == FUNCTION_CALL) {
            $params = getChildOfType($statement, FUNCTION_PARAMS);
            if ($params === null) {
                throw new Exception("No parameters provided for function {$statement['function']}");
            }
            $paramData = array();
            foreach ($params['children'] as $param) {
                $paramData[] = execute(array($param))[0];
            }

            $function_name = $statement['children'][0];
            $function = getFunctionByName($function_name);

            call_user_func_array($function, $paramData);
        } else if ($statement['state'] == STRING) {
            $results[] = implode('', $statement['contents']);
        } else {
            throw new Exception("Don't know how to execute {$statement['state']}");
        }
        $currentStatement ++;
    }

    return $results;
}

?>