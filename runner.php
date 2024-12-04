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

function execute($tree, &$context = null) {
    if ($context === null) {
        $context = array(
            "variables" => array(),
        );
    }

    $currentStatement = 0;
    $results = array();
    while ($currentStatement < count($tree)) {
        $statement = $tree[$currentStatement];

        if ($statement['state'] == START_STATE) {
            // noop
        } else if ($statement['state'] == FUNCTION_CALL) {
            $params = getChildOfType($statement, FUNCTION_PARAMS);
            if ($params === null) {
                throw new Exception("No parameters provided for function {$statement['function']}");
            }
            $paramData = array();
            foreach ($params['children'] as $param) {
                $paramData[] = execute(array($param), $context)[0];
            }

            $function_name = $statement['children'][0];
            $function = getFunctionByName($function_name);

            call_user_func_array($function, $paramData);
        } else if ($statement['state'] == STRING) {
            $results[] = implode('', $statement['contents']);
        } else if ($statement['state'] == VAR_DEFINITION) {
            $value = execute($statement['children'], $context)[0];
            $context['variables'][$statement['name']] = $value;
        } else if ($statement['state'] == CONDITIONAL) {
            if (array_key_exists('else', $statement) && $statement['else'] && !array_key_exists('condition', $statement)) {
                $condition_result = true;
            } else {
                $condition_result = execute($statement['condition'][0]['children'], $context)[0];
            }
            if ($condition_result) {
                execute($statement['children'], $context);
                $results[] = true;
            } else {
                $results[] = false;
            }
        } else if ($statement['state'] == COMPARISON) {
            $left_hand_value = execute(array($statement['left_hand']), $context)[0];
            $right_hand_value = execute($statement['children'], $context)[0];

            $op = $statement['operator'];

            if ($op === "===") {
                $results[] = $left_hand_value === $right_hand_value;
            } else {
                throw new Exception("Unexpected operator \"$op\"");
            }
        } else if ($statement['state'] == VAR_OR_FUNCTION) {
            if (array_key_exists($statement['var_or_function'], $context['variables'])) {
                $results[] = $context['variables'][$statement['var_or_function']];
            } else {
                throw new Exception("No such variable \"" . $statement['var_or_function'] . "\"");
            }
        } else if ($statement['state'] == CONDITIONAL_GROUP) {
            foreach ($statement['children'] as $child) {
                $result = execute(array($child), $context)[0];
                if ($result) {
                    break;
                }
            }
        } else if ($statement['state'] == BLOCK) {
            // later on we will want to have a specific context for this block but that comes later
            execute($statement['children'], $context);
        } else {
            throw new Exception("Don't know how to execute {$statement['state']}");
        }
        $currentStatement ++;
    }

    return $results;
}

?>