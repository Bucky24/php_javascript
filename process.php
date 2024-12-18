<?php

include_once("types.php");

$LOG = false;

function _log($str) {
    global $LOG;
    if ($LOG) {
        print("PROCESS LOG ". $str);
    }
}

function processCode($code) {
    _log("Start processing code");
    $tokens = tokenize($code);

    $tree = buildTree($tokens);

    //print_r($tree);

    return array(
        $tree,
    );
}

function newContext() {
    return array(
        "state" => START_STATE,
    );;
}

function pushContext(&$context_stack, $context) {
    _log("Push {$context['state']}\n");
    $context_stack[] = $context;
    return newContext();
}

function popContext(&$context_stack, $context, &$tree) {
    if (count($context_stack) === 0) {
        $tree[] = $context;
        $next_item = newContext();
    } else {
        $next_item = array_pop($context_stack);
        if (!array_key_exists("children", $next_item)) {
            $next_item['children'] = array();
        }

        $next_item['children'][] = $context;
    }

    _log("Pop {$next_item['state']}, " . count($context_stack) . " left on stack\n");

    return $next_item;
}

function isValidVariable($token) {
    for ($i=0;$i<strlen($token);$i++) {
        $char = $token[$i];
        if (ctype_alpha($char) || is_numeric($char) || $char === "_") {
            continue;
        }
        return false;
    }
    return true;
}

function isComparitor($token) {
    if ($token === "=" || $token === "<" || $token === ">") {
        return true;
    }

    return false;
}

function isMath($token) {
    return ($token === "+" || $token === "-" || $token === "/" || $token === "*" || $token === "|");
}

function buildTree($tokens) {
    $context = newContext();
    $context_stack = array();
    $tree = array();

    for ($i=0;$i<count($tokens);$i++) {
        $token = $tokens[$i];
        $state = $context['state'];
        $prev_context = null;
        if (count($context_stack) > 0) {
            $prev_context = $context_stack[count($context_stack)-1];
        }
        $prev_state = "none";
        if ($prev_context) {
            $prev_state = $prev_context['state'];
        }
        _log($token . " " . var_export($context, true) . ". Previous context: $prev_state. Tokens left: " . (count($tokens) - $i - 1) . "\n");
        if ($state == START_STATE) {
            if ($token === "\"" || $token === "'") {
                $context['state'] = STRING;
                $context['quote_type'] = $token;
                $context['contents'] = array();
                continue;
            } else if ($token === ";") {
                if ($prev_state === BLOCK) {
                    $context = popContext($context_stack, $context, $tree);
                    continue;
                }
                // in this case everything else handled it and was popped
                continue;
            } else if ($token === "const") {
                $context['state'] = VAR_DEFINITION;
                $context['constant'] = true;
                continue;
            } else if ($token === "let") {
                $context['state'] = VAR_DEFINITION;
                $context['constant'] = false;
                continue;
            } else if ($token === " " || $token === "\n") {
                continue;
            } else if ($token === "if") {
                $context['state'] = CONDITIONAL;
                continue;
            } else if ($token === "}") {
                $context = popContext($context_stack, $context, $tree);
                $i --;
                continue;
            } else if ($token === "else") {
                $last_entry = array_pop($tree);
                if ($last_entry['state'] == CONDITIONAL || $last_entry['state'] == CONDITIONAL_GROUP) {
                    $context = $last_entry;
                    $i--;
                    continue;
                }
                // see if our previous entry is a block that has a conditional child
                if ($prev_state === BLOCK) {
                    $last_entry = array_pop($prev_context['children']);
                    if ($last_entry['state'] == CONDITIONAL || $last_entry['state'] == CONDITIONAL_GROUP) {
                        $context = $last_entry;
                        $i--;
                        continue;
                    }
                }
            } else if ($token === "for") {
                $context['state'] = FOR_LOOP;
                continue;
            } else if (is_numeric($token)) {
                $context['state'] = NUMBER;
                $context['number'] = $token;
                continue;
            } else if($token === "import") {
                $context['state'] = IMPORT;
                continue;  
            } else if ($token === "{") {
                $context['state'] = OBJ;
                continue;  
            } else if ($token === "export") {
                $context['state'] = EXPORT;
                continue;  
            } else if ($token === "function") {
                $context['state'] = FUNCTION_DEF;
                continue;
            } else if ($token === "/") {
                $context['state'] = SINGLE_COMMENT;
                continue;
            } else if ($token === "!") {
                $context['state'] = NEGATION;
                continue;
            } else if ($token === "new") {
                $context['state'] = INSTANCE;
                continue;
            } else if (isValidVariable($token)) {
                $context['var_or_function'] = $token;
                $context['state'] = VAR_OR_FUNCTION;
                continue;
            } else if ($token === "`") {
                $context['state'] = TEMPLATE_STRING;
                continue;
            }
        } else if ($state == VAR_OR_FUNCTION) {
            if ($token === ".") {
                $old_context = $context;
                $context = pushContext($context_stack, $context);
                $context['state'] = NESTED_PATH;
                $context['path'] = array(
                    $old_context['var_or_function'],
                );
                continue;
            } else if ($token === "(") {
                $context['state'] = FUNCTION_CALL;
                if (!array_key_exists("children", $context)) {
                    $context['children'] = array(
                        array(
                            "state" => VAR_OR_FUNCTION,
                            "var_or_function" => $context['var_or_function'],
                        ),
                    );
                }
                $context = pushContext($context_stack, $context);
                $context['state'] = FUNCTION_PARAMS;
                continue;
            } else if ($token === " ") {
                continue;
            } else if (isComparitor($token)) {
                if ($prev_context && $prev_context['state'] == NEGATION) {
                    // let the above handle it
                    $context = popContext($context_stack, $context, $tree);
                    $i --;
                    continue;
                } else {
                    $oldContext = $context;
                    $context = newContext();
                    $context['state'] = COMPARISON;
                    $context['operator'] = $token;
                    $context['left_hand'] = $oldContext;
                    continue;
                }
            } else if (isMath($token)) {
                if ($prev_context && $prev_context['state'] == NEGATION) {
                    // let the above handle it
                    $context = popContext($context_stack, $context, $tree);
                    $i --;
                    continue;
                } else {
                    $oldContext = $context;
                    $context = newContext();
                    $context['state'] = MATH;
                    $context['operator'] = $token;
                    $context['left_hand'] = $oldContext;
                    continue;
                }
            } else if ($token === ")" || $token === "from" || $token === ";" || $token === "," || $token === "}") {
                $context = popContext($context_stack, $context, $tree);
                $i--;
                continue;
            }
        } else if ($state == NESTED_PATH) {
            if (isValidVariable($token)) {
                $context['path'][] = $token;
                continue;
            } else if ($token === ".") {
                continue;
            } else {
                $context = popContext($context_stack, $context, $tree);
                $i--;
                continue;
            }
        } else if ($state == FUNCTION_PARAMS) {
            if ($token === ")") {
                $context = popContext($context_stack, $context, $tree);
                $i --;
                continue;
            } else if ($token === ",") {
                continue;
            }
            $context = pushContext($context_stack, $context);
            $i--;
            continue;
        } else if ($state == STRING) {
            if ($token === $context['quote_type']) {
                $context = popContext($context_stack, $context, $tree);
                continue;
            }
            $context['contents'][] = $token;
            continue;
        } else if ($state === FUNCTION_CALL) {
            if ($token === ";") {
                $context = popContext($context_stack, $context, $tree);
                $i--;
                continue;
            } else if ($token === ")") {
                $context = popContext($context_stack, $context, $tree);
                continue;
            }
        } else if ($state === VAR_DEFINITION) {
            if ($token === " ") {
                continue;
            } else if (isValidVariable($token)) {
                $context['name'] = $token;
                continue;
            } else if ($token === "=") {
                $context = pushContext($context_stack, $context);
                continue;
            } else if ($token === ";") {
                $context = popContext($context_stack, $context, $tree);
                continue;
            }
        } else if ($state === CONDITIONAL) {
            if ($token === " ") {
                continue;
            } else if ($token === "(") {
                $context = pushContext($context_stack, $context);
                $context['state'] = CONDITIONAL_CONDITION;
                continue;
            } else if ($token === "{") {
                $context = pushContext($context_stack, $context);
                $context['state'] = BLOCK;
                continue;
            } else if ($token === "}") {
                $context['finished'] = true;
                $context = popContext($context_stack, $context, $tree);
                continue;
            } else if ($token === "if") {
                if (array_key_exists('else', $context) && $context['else']) {
                    continue;
                }  
            } else if ($token === "else") {
                if ($context['finished']) {
                    $group = newContext();
                    $group['state'] = CONDITIONAL_GROUP;
                    $group['children'] = array(
                        $context,
                    );
                    $context = pushContext($context_stack, $group);
                    $context['state'] = CONDITIONAL;
                    $context['else'] = true;
                    continue;
                }
            }
        } else if ($state === CONDITIONAL_CONDITION) {
            if ($token === ")") {
                $context = popContext($context_stack, $context, $tree);
                $context['condition'] = $context['children'];
                $context['children'] = array(); 
                continue;
            } else {
                $context = pushContext($context_stack, $context);
                $i --;
                continue;
            }
        } else if ($state === COMPARISON) {
            if ($token === " ") {
                continue;
            } else if ($token === ")" || $token === ";") {
                $context = popContext($context_stack, $context, $tree);
                $i--;
                continue;
            } else if (isComparitor($token)) {
                $context['operator'] .= $token;
                continue;
            } else {
                $context = pushContext($context_stack, $context);
                $i --;
                continue;
            }
        } else if ($state === CONDITIONAL_GROUP) {
            if ($token === "else") {
                $context = pushContext($context_stack, $context);
                $context['state'] = CONDITIONAL;
                $context['else'] = true;
                continue;
            } else if ($token === " ") {
                continue;
            } else {
                $context = popContext($context_stack, $context, $tree);
                continue;
            }
        } else if($state === FOR_LOOP) {
            if ($token === " ") {
                continue;
            } else if ($token === "(") {
                $context['start_params'] = true;
                $context = pushContext($context_stack, $context);
                continue;
            } else if ($token === ";") {
                continue;
            } else if ($token === ")") {
                $context['statements'] = $context['children'];
                $context['children'] = array();
                continue;
            } else if ($token === "{") {
                $context = pushContext($context_stack, $context);
                $context['state'] = BLOCK;
                continue;
            } else if ($token === "}") {
                $context = popContext($context_stack, $context, $tree);
                continue;
            } else {
                if ($context['start_params']) {
                    $context = pushContext($context_stack, $context);
                    $i--;
                    continue;
                }
            }
        } else if ($state === NUMBER) {
            if ($token === ";") {
                $context = popContext($context_stack, $context, $tree);
                $i --;
                continue;
            }
        } else if($state === MATH) {
            if ($token === "+") {
                if ($context['operator'] === "+") {
                    $context['state'] = INCREMENT;
                    $context['variable'] = $context['left_hand']['var_or_function'];
                    continue;
                }
            } else if ($token === "|") {
                if ($context['operator'] === "|") {
                    $context['operator'] = "||";
                    $context = pushContext($context_stack, $context);
                    continue; 
                }
            } else if ($token === ";" || $token === ")") {
                $context = popContext($context_stack, $context, $tree);
                $i --;
                continue;   
            }
        } else if ($state === INCREMENT) {
            if ($token === ")") {
                $context = popContext($context_stack, $context, $tree);
                $i --;
                continue;
            }
        } else if ($state === BLOCK) {
            if (!array_key_exists("finished", $context)) {
                $context['finished'] = false;
            }
            if ($token === "}") {
                $context['finished'] = true;
                $context = popContext($context_stack, $context, $tree);
                $i --;
                continue;
            } else if (!$context['finished']) {
                $context = pushContext($context_stack, $context);
                $i--;
                continue;
            }
        } else if ($state === IMPORT) {
            if ($token === " ") {
                continue;
            } else if ($token === "from") {
                $context['import'] = $context['children'];
                $context['children'] = array();

                $context = pushContext($context_stack, $context);
                continue;
            } else if ($token === ";") {
                $context = popContext($context_stack, $context, $tree);
                continue;
            } else {
                $context = pushContext($context_stack, $context);
                $i--;
                continue;
            }
        } else if ($state === OBJ) {
            if (!array_key_exists("substate", $context)) {
                $context['substate'] = "waiting_for_name";
                $context['values'] = array();
            }
            if ($token === " " || $token === "\n") {
                continue;
            } else if ($token === ",") {
                // may need to do something different here
                if ($context['substate'] === "waiting_for_value") {
                    $context['values'][] = array(
                        "name" => $context['name'],
                        "value" => $context['children'],
                    );
                    $context['children'] = array();
                    $context['substate'] = "waiting_for_name";
                    continue;
                }
            } else if ($token === "}") {
                if ($context['substate'] === "waiting_for_name") {
                    $context = popContext($context_stack, $context, $tree);
                    continue;
                } else if ($context['substate'] === "has_name") {
                    $context['values'][] = array(
                        "name" => $context['name'],
                        "value" => array(
                            "state" => VAR_OR_FUNCTION,
                            "var_or_function" => $context['name'],
                        ),
                    );
                    $context = popContext($context_stack, $context, $tree);
                    continue;
                } else {
                    print("Unexpected substate {$context['substate']}\n");
                }
            } else if (isValidVariable($token)) {
                if ($context['substate'] === "waiting_for_name") {
                    $context['name'] = $token;
                    $context['substate'] = "has_name";
                    continue;
                }
            } else if ($token === ":") {
                if ($context['substate'] === "has_name") {
                    $context['substate'] = "waiting_for_value";
                    $context = pushContext($context_stack, $context);
                    continue;
                }
            }
        } else if ($state === EXPORT) {
            if (array_key_exists("children", $context)) {
                $context = popContext($context_stack, $context, $tree);
                $i --;
                continue;
            } else {
                $context = pushContext($context_stack, $context);
                continue;
            }
        } else if ($state === FUNCTION_DEF) {
            if ($token === " ") {
                continue;
            } else if (isValidVariable($token) && !array_key_exists("name", $context)) {
                $context['name'] = $token;
                continue;
            } else if ($token === "(") {
                $context = pushContext($context_stack, $context);
                $context['state'] = FUNCTION_DEF_PARAMS;
                $context['params'] = array();
                continue;
            } else if ($token === ")") {
                $context['have_args'] = true;
                continue;
            } else if ($token === "{") {
                $context = pushContext($context_stack, $context);
                $context['state'] = BLOCK;
                continue;
            } else if ($token === "}") {
                $context = popContext($context_stack, $context, $tree);
                continue;
            }
        } else if ($state === FUNCTION_DEF_PARAMS) {
            if (!array_key_exists("params", $context)) {
                $context['params'] = array();
            }
            if ($token === ")") {
                $context = popContext($context_stack, $context, $tree);
                $i --;
                continue;
            } else if (isValidVariable($token)) {
                $context['params'][] = $token;
                continue;
            } else if ($token === "," || $token === " ") {
                continue;
            }
        } else if ($state === SINGLE_COMMENT) {
            if ($token === "\n") {
                $context = popContext($context_stack, $context, $tree);
                $i --;
                continue;
            } else {
                continue;
            }
        } else if ($state === NEGATION) {
            if ($token === ";") {
                $context = popContext($context_stack, $context, $tree);
                $i --;
                continue;
            } else if (isMath($token)) {
                $oldContext = $context;
                $context = newContext();
                $context['state'] = MATH;
                $context['operator'] = $token;
                $context['left_hand'] = $oldContext;
                continue;
            } else {
                $context = pushContext($context_stack, $context);
                $i --;
                continue;
            }
        } else if ($state === INSTANCE) {
            if ($token === " ") {
                continue;
            } else if (isValidVariable($token, $context)) {
                $context['state'] = VAR_OR_FUNCTION;
                $context['instance'] = true;
                $context['var_or_function'] = $token;
                continue;
            }
        } else if ($state === TEMPLATE_STRING) {
            if (!array_key_exists("seen_dollar", $context)) {
                $context['seen_dollar'] = false;
            }
            if (!array_key_exists("children", $context)) {
                $context['children'] = array();
            }
            if (!array_key_exists("buffer", $context)) {
                $context['buffer'] = array();
            }
            if ($token === "$") {
                $context['seen_dollar'] = true;
                continue;
            } else if ($token === "{") {
                if ($context['seen_dollar']) {
                    $context['seen_dollar'] = false;
                    if (count($context['buffer']) > 0) {
                        $context['children'][] = array(
                            "state" => STRING,
                            "content" => implode("", $context['buffer']),
                        );
                        $context['buffer'] = array();
                    }
                    $context = pushContext($context_stack, $context);
                    $context['state'] = BLOCK;
                    continue;
                }
            } else if ($token === "}") {
                continue;
            } else if ($token === "`") {
                if (count($context['buffer']) > 0) {
                    $context['children'][] = array(
                        "state" => STRING,
                        "content" => implode("", $context['buffer']),
                    );
                    $context['buffer'] = array();
                }
                $context = popContext($context_stack, $context, $tree);
                continue;
            } else {
                if ($context['seen_dollar']) {
                    $context['buffer'][] = '$';
                }
                $context['buffer'][] = $token;
                continue;
            }
        }
        throw new Exception("Unexpected token \"$token\" in state \"$state\"");
    }

    while (count($context_stack) > 0) {
        $context = popContext($context_stack, $context, $tree);
    }

    if ($context['state'] !== START_STATE) {
        $tree[] = $context;
    }

    return $tree;
}

function tokenize($code) {
    $interesting_chars = array(".", "(", ")", ";", "\"", " ", "\n", "'", "=", "<", ">", "!", "+", "-", "/", "*", ",", "|", "&", ":", "$", "{", "}", "`");
    $tokens = array();
    $buffer = "";
    for ($i=0;$i<strlen($code);$i++) {
        $char = $code[$i];
        if (in_array($char, $interesting_chars)) {
            if (strlen($buffer) > 0) {
                $tokens[] = $buffer;
                $buffer = "";
            }
            $tokens[] = $char;
        } else {
            $buffer .= $char;
        }
    }
    if (strlen($buffer) > 0) {
        $tokens[] = $buffer;
    }

    return $tokens;
}

?>