<?php

include_once("types.php");
include_once("builtin.php");
include_once("module.php");
include_once("loader.php");
include_once("utils.php");

function getChildenOfType($statement, $type) {
    if (!array_key_exists('children', $statement)) {
        return null;
    }
    $results = array();
    foreach ($statement['children'] as $child) {
        if ($child['state'] == $type) {
            $results[] = $child;
        }
    }

    return $results;
}


function getChildOfType($statement, $type) {
    $results = getChildenOfType($statement, $type);
    if (count($results) > 0) {
        return $results[0];
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
            list ($function, $name_fragment) = getFunctionByName(array(
                "state" => NESTED_PATH,
                "path" => array_slice($path, 1),
            ), $class);

            return array(
                $function,
                "{$path[0]}.$name_fragment",
            );
        } else {
            if (array_key_exists($path[0], $context['functions'])) {
                return array(
                    $context['functions'][$path[0]],
                    $path[0],
                );
            }
            throw new Exception("Function {$path[0]} was not found");
        }
    } else if ($name['state'] == VAR_OR_FUNCTION) {
        $func_name = $name['var_or_function'];
        if (array_key_exists($func_name, $context['functions'])) {
            return array(
                $context['functions'][$func_name],
                $func_name,
            );
        }
        throw new Exception("Function {$func_name} was not found");
    } else {
        throw new Exception("Unsure how to get function from name " . var_export($name, true));
    }
}

function execute($tree, &$context = null) {
    if (!$context) {
        $context = array();
    }
    if (!array_key_exists("variables", $context)) {
        $context["variables"] = array();
    }
    if (!array_key_exists("functions", $context)) {
        $context["functions"] = array();
    }
    if (array_key_exists("file", $context)) {
        $context['dir'] = dirname($context['file']);
    }

    $currentStatement = 0;
    $results = array();
    while ($currentStatement < count($tree)) {
        $statement = $tree[$currentStatement];
        _log("Executing statement ". $currentStatement . " " . $statement['state']);

        try {
            if ($statement['state'] == START_STATE) {
                // noop
            } else if ($statement['state'] == FUNCTION_CALL) {
                $params = getChildOfType($statement, FUNCTION_PARAMS);
                if ($params === null) {
                    throw new Exception("No parameters provided for function {$statement['function']}");
                }
                $paramData = array();
                    if (array_key_exists("children", $params)) {
                    foreach ($params['children'] as $param) {
                        $paramData[] = execute(array($param), $context)[0];
                    }
                }

                $function_name = $statement['children'][0];
                list($function, $real_name) = getFunctionByName($function_name, $context);
                if (!$function) {
                    throw new Exception("Cannot find function with name $real_name");
                }

                if (is_array($function)) {
                    execute($function['statements'], $context);
                } else {
                    call_user_func_array($function, $paramData);
                }
            } else if ($statement['state'] == STRING) {
                $results[] = array(
                    "type" => "string",
                    "data" => implode('', $statement['contents']),
                );
            } else if ($statement['state'] == VAR_DEFINITION) {
                $value = execute($statement['children'], $context)[0];
                $context['variables'][$statement['name']] = $value;
                $results[] = array(
                    "type" => "variable",
                    "name" => $statement['name'],
                    "data" => $statement,
                );
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
                } else if ($op === "<") {
                    $results[] = $left_hand_value < $right_hand_value;
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
            } else if ($statement['state'] == FOR_LOOP) {
                $init = $statement['statements'][0];
                $check = $statement['statements'][1];
                $post = $statement['statements'][2];

                execute(array($init), $context);
                while (true) {
                    $check_result = execute(array($check), $context)[0];
                    if (!$check_result) {
                        break;
                    }
                    execute($statement['children'], $context);
                    execute(array($post), $context);
                }
            } else if ($statement['state'] == NUMBER) {
                $results[] = array(
                    "type" => "number",
                    "data" => intval($statement['number']),
                );
            } else if ($statement['state'] == INCREMENT) {
                if (array_key_exists($statement['variable'], $context['variables'])) {
                    $value = $context['variables'][$statement['variable']];
                    if ($value['type'] !== "number") {
                        throw new Exception("Attempting to increment a non-number");
                    }
                    $data = getValue($value);
                    $context['variables'][$statement['variable']] = array(
                        "type" => "number",
                        "data" => $data + 1,
                    );
                } else {
                    throw new Exception("No such variable \"" . $statement['var_or_function'] . "\"");
                }
            } else if ($statement['state'] == IMPORT) {
                $file = execute($statement['children'], $context)[0];

                if (!$file['type'] === 'string') {
                    throw new Exception("Import path should be a string");
                }

                $file = getValue($file);
                _log("handling import $file with context of " . var_export($context, true));

                $contents = getModule($file);
                if (!$contents) {
                    $result = processFile($file, $context);
                    saveModule($result['file'], $result['contents']);
                    $contents = $result['contents'];
                }
                $expect = array();
                foreach ($statement['import'] as $import) {
                    if ($import['state'] === OBJ) {
                        $import['state'] = DESTRUCTURE;
                    } else if ($import['state'] === VAR_OR_FUNCTION) {
                        $import['state'] = IMPORT_VAR;
                    } else if ($import['state'] === IMPORT_ALL) {
                        // nothing needed
                    } else {
                        throw new Exception("Import doesn't know how to handle {$import['state']}");
                    }
                    $expect = array_merge($expect, execute(array($import), $context));
                }
                $exports = array();
                foreach ($contents as $content) {
                    if ($content['type'] === 'export') {
                        $export = $content['export'];
                        $exports[$export['name']] = $export;
                    }
                }

                $expect_items = array();
                foreach ($expect as $item) {
                    if ($item['type'] === "obj_destructure") {
                        foreach ($item['data'] as $name) {
                            $expect_items[] = array(
                                "export_name" => $name,
                                "variable" => $name,
                            );
                        }
                    } else if ($item['type'] === 'import_var') {
                        $expect_items[] = array(
                            "export_name" => 'default',
                            "variable" => $name,
                        );
                    } else if ($item['type'] === "import_all") {
                        foreach ($exports as $key=>$value) {
                            $expect_items[] = array(
                                "export_name" => $key,
                                "variable" => $key,
                            );
                        }
                    } else {
                        throw new Exception("Import doesn't know how to handle {$item['type']} for import $file");
                    }
                }
                $expect = $expect_items;

                foreach ($expect as $item) {
                    if (!array_key_exists($item['export_name'], $exports)) {
                        throw new Error("No export detected in $file named {$item['export_name']}");
                    }
                    $export = $exports[$item['export_name']];
                    if ($export['type'] === "function") {
                        $context['functions'][$item['variable']] = $export['data'];
                    }
                }
            } else if ($statement['state'] == EXPORT) {
                $result = execute($statement['children'], $context)[0];
                $results[] = array(
                    "type" => "export",
                    "export" => $result,
                );
            } else if ($statement['state'] == FUNCTION_DEF) {
                $results[] = array(
                    "type" => "function",
                    "name" => $statement['name'],
                    "data" => array(
                        "params" => getChildOfType($statement, FUNCTION_DEF_PARAMS),
                        "statements" => getChildenOfType($statement, BLOCK),
                    ),
                );
            } else if ($statement['state'] == OBJ) {
                $data = array();
                foreach ($statement['values'] as $value) {
                    $value_result = execute(array($value['value']), $context)[0];
                    $data[$value['name']] = $value_result;
                }
                $results[] = array(
                    "type" => "object",
                    "data" => $data,
                );
            } else if ($statement['state'] == SINGLE_COMMENT) {
                // pass
            } else if ($statement['state'] == NEGATION) {
                $result = execute($statement['children'], $context)[0];

                if ($result['type'] === "string") {
                    $results[] = array(
                        "type" => "boolean",
                        "data" => false,
                    );
                } else {
                    throw new Exception("Negation can't handle type {$result['type']}");
                }
            } else if ($statement['state'] === DESTRUCTURE) {
                $names = array();
                foreach ($statement['values'] as $value) {
                    $names[] = $value['name'];
                }

                $results[] = array(
                    "type" => "obj_destructure",
                    "data" => $names,
                );
            } else if ($statement['state'] === IMPORT_VAR) {
                $results[] = array(
                    "type" => "import_var",
                    "data" => $statement['var_or_function'],
                );
            } else  if ($statement['state'] === IMPORT_ALL) {
                $results[] = array(
                    "type" => "import_all",
                );
            } else {
                throw new Exception("Don't know how to execute {$statement['state']}");
            }
            $currentStatement ++;
        } catch (Exception $e) {
            _log("ERROR: {$e->getMessage()} {$e->getTraceAsString()}");
            throw new Exception("Exception found when running statement " . var_export($statement['state'], true) . ": {$e->getMessage()}");
        }
    }

    return $results;
}

?>