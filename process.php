<?php

include_once("types.php");

$LOG = true;

function _log($str) {
    global $LOG;
    if ($LOG) {
        print("PROCESS LOG ". $str);
    }
}

function processCode($code) {
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
        if (ctype_alpha($token) || is_numeric($token) || $token === "_") {
            continue;
        }
        return false;
    }
    return true;
}

function isComparitor($token) {
    if ($token === "===") {
        return true;
    }

    return false;
}

function buildTree($tokens) {
    $context = newContext();
    $context_stack = array();
    $tree = array();

    for ($i=0;$i<count($tokens);$i++) {
        $token = $tokens[$i];
        $state = $context['state'];
        _log($token . " " . var_export($context, true) . "\n");
        if ($state == START_STATE) {
            if ($token === "\"") {
                $context['state'] = STRING;
                $context['quote_type'] = "\"";
                $context['contents'] = array();
                continue;
            } else if ($token === "const") {
                $context['state'] = VAR_DEFINITION;
                $context['constant'] = true;
                continue;
            } else if ($token === " " || $token === "\n") {
                continue;
            } else if ($token === "if") {
                $context['state'] = CONDITIONAL;
                continue;
            } else if (isValidVariable($token)) {
                $context['var_or_function'] = $token;
                $context['state'] = VAR_OR_FUNCTION;
                continue;
            } else if ($token === "}") {
                $context = popContext($context_stack, $context, $tree);
                $i --;
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
                $context['function'] = $context['var_or_function'];
                $context = pushContext($context_stack, $context);
                $context['state'] = FUNCTION_PARAMS;
                continue;
            } else if ($token === " ") {
                continue;
            } else if (isComparitor($token)) {
                $oldContext = $context;
                $context = newContext();
                $context['state'] = COMPARISON;
                $context['operator'] = $token;
                $context['left_hand'] = $oldContext;
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
                $context =popContext($context_stack, $context, $tree);
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
                $context['in_body'] = true;
                continue;
            } else if ($token === "}") {
                $context = popContext($context_stack, $context, $tree);
                continue;
            } else {
                if (array_key_exists("in_body", $context) && $context['in_body']) {
                    $context = pushContext($context_stack, $context);
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
            } else if ($token === ")") {
                $context = popContext($context_stack, $context, $tree);
                $i--;
                continue;
            } else {
                $context = pushContext($context_stack, $context);
                $i --;
                continue;
            }
        }
        throw new Exception("Unexpected token \"$token\" in state \"$state\"");
    }

    return $tree;
}

function tokenize($code) {
    $interesting_chars = array(".", "(", ")", ";", "\"", " ", "\n");
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