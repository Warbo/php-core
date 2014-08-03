<?php

// Work around some PHP language bugs

// Allow expressions (including Closures) to be named as functions
function defun($name, $expr) {
  // Source: http://www.php.net/manual/en/functions.user-defined.php
  $valid = '/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]/';
  array_map(function($x) { if ($x[1]) trigger_error($x[0], E_USER_ERROR); },
            [["Invalid name for $name", !preg_match($valid, $name)],
             ["Cannot redeclare $name", function_exists($name)    ],
             ["Invalid expr for $name", !is_callable($expr, TRUE)]]);

  // Declare $name globally. The static $f is a poor man's lexical scope.
  eval("function {$name}() {
          static \$defined = true;  // Used by arity
          static \$f = NULL;
          \$args = func_get_args();
          return (is_null(\$f))? \$f = \$args[0]
                               : call_user_func_array(\$f, \$args);
        }");

  // Initialise $f to expr (optionally, applied to our extra arguments)
  $args = array_slice(func_get_args(), 2);
  return $name($args? call_user_func_array($expr, $args)
                    : $expr);
}

// Function equivalents to operators
// https://bugs.php.net/bug.php?id=66368
function op($f) {
  // Hide ** in an eval to avoid syntax errors in PHP < 5.6
  $exp = function() { return eval('function($x, $y) { return $x ** $y; }'); };

  $operators = [
    // Unary
    '!'     => function($x) { return      !$x;  },
    'isset' => function($x) { return isset($x); },
    'empty' => function($x) { return empty($x); },
    'clone' => function($x) { return clone $x;  },

    // Unary/binary
    '-' => function($x, $y=null) { return is_null($y)? -$x : $x - $y; },

    // Binary
    '+'   => function($x, $y) { return $x +   $y;  },
    '*'   => function($x, $y) { return $x *   $y;  },
    '/'   => function($x, $y) { return $x /   $y;  },
    '%'   => function($x, $y) { return $x %   $y;  },
    '.'   => function($x, $y) { return $x .   $y;  },
    '^'   => function($x, $y) { return $x ^   $y;  },
    '||'  => function($x, $y) { return $x ||  $y;  },
    '&&'  => function($x, $y) { return $x &&  $y;  },
    '**'  => function($x, $y) use ($exp) { return call($exp(), $x, $y); },
    '>'   => function($x, $y) { return $x >   $y;  },
    '<'   => function($x, $y) { return $x <   $y;  },
    '>='  => function($x, $y) { return $x >=  $y;  },
    '<='  => function($x, $y) { return $x <=  $y;  },
    '=='  => function($x, $y) { return $x ==  $y;  },
    '===' => function($x, $y) { return $x === $y;  },
    '[]'  => function($x, $y) { return $x    [$y]; },
    '->'  => function($x, $y) { return $x ->  $y;  },
    'instanceof' => function($x, $y) { return $x instanceof $y; },

    // N-ary
    'array' => function() { return func_get_args(); },
    'new'   => function($class) {
                 $ref = new ReflectionClass($cls);
                 return $ref->newInstanceArgs(array_slice(func_get_args(), 1));
               }];
  return (is_string($f) && isset($operators[$f]))? $operators[$f] : $f;
}

// Like call_user_func but works for operators too
function call($f) {
  return call_user_func_array(op($f), array_slice(func_get_args(), 1));
}

// Like call_user_func_array but works for operators too
defun('uncurry', curry(function($f, $args) {
                         return call_user_func_array(op($f), $args);
                       }));

// Curry functions: accumulates $n $args then calls $f
function _curry($args, $n, $f) {
  return (count($args) >= $n)
      // Send $n $args to $f, uncurry the result and apply it to any extra $args
    ? array_reduce(array_slice($args, $n),
                  'call',
                   call_user_func_array($f, array_slice($args, 0, $n)))
      // Not enough $args, wait for some more
    : function() use ($args, $n, $f) {
        static $curried = true;  // Used by arity
        return _curry(array_merge($args, func_get_args()), $n, $f);
      };
}

// Start with given arguments, guess $n using arity
function curry($f) {
  return _curry(array_slice(func_get_args(), 1), arity($f), $f);
}

// Find a function's arity, even if it's curried or defined as an expression
function arity($f) {
  $rf = new ReflectionFunction($f);
  $sv = $rf->getStaticVariables();

  // If we're a defined as $f, return $f's arity
  if (isset($sv['defined'])) return arity($sv['f']);

  // If we're a curried version of $f, return $f's arity
  if (isset($sv['curried'])) {
    if ($sv['n']) return $sv['n'] - count($sv['args']);
    if (isset($sv['f'])) return arity($sv['f']);
  }

  // Otherwise, reflect the arity
  return $rf->getNumberOfParameters();
}
