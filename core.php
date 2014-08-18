<?php
// Work around some PHP language deficiencies

call_user_func(function() {
  // Function equivalents to operators
  // https://bugs.php.net/bug.php?id=66368
  $op = function($f) {
    $operators = array(
      // Unary
      '!'     => function($x) { return      !$x;  },
      'isset' => function($x) { return isset($x); },
      'empty' => function($x) { return empty($x); },
      'clone' => function($x) { return clone $x;  },
      'neg'   => function($x) { return      -$x;  },
      'throw' => function($x) { throw        $x;  },

      // Binary
      '+'   => function($x, $y) { return $x +   $y;  },
      '-'   => function($x, $y) { return $x -   $y;  },
      '*'   => function($x, $y) { return $x *   $y;  },
      '/'   => function($x, $y) { return $x /   $y;  },
      '%'   => function($x, $y) { return $x %   $y;  },
      '.'   => function($x, $y) { return $x .   $y;  },
      '^'   => function($x, $y) { return $x ^   $y;  },
      '||'  => function($x, $y) { return $x ||  $y;  },
      '&&'  => function($x, $y) { return $x &&  $y;  },
      '>'   => function($x, $y) { return $x >   $y;  },
      '<'   => function($x, $y) { return $x <   $y;  },
      '>='  => function($x, $y) { return $x >=  $y;  },
      '<='  => function($x, $y) { return $x <=  $y;  },
      '=='  => function($x, $y) { return $x ==  $y;  },
      '===' => function($x, $y) { return $x === $y;  },
      '[]'  => function($x, $y) { return $x    [$y]; },
      '->'  => function($x, $y) { return $x ->  $y;  },
      '**'  => function($x, $y) {
                 // Hide ** in an eval to avoid syntax errors in PHP < 5.6
                 $exp = eval('function($x, $y) { return $x ** $y; }');
                 return $exp($x, $y);
               },
      'instanceof' => function($x, $y) { return $x instanceof $y; },

      // N-ary
      'array' => function() { return func_get_args(); },
      'new'   => function($class) {
                   $rc = new ReflectionClass($class);
                   return $rc->newInstanceArgs(array_slice(func_get_args(), 1));
                 });
    return (is_string($f) && isset($operators[$f]))? $operators[$f] : $f;
  };

  // Partial application
  $papply = function() {
    $args    = func_get_args();
    $f       = $op(array_shift($args));
    return function() use ($args, $f) {
      static $curried = true;
      return call_user_func_array('call_user_func',
                                  array_merge($args, func_get_args()));
    };
  };

  // Curry functions: accumulates $n $args then calls $f
  $_curry = function($args, $n, $f) use (&$_curry, $op) {
    return (count($args) >= $n)
        // Send $n $args to $f, uncurry the result & apply it to remaining $args
      ? array_reduce(array_slice($args, $n),
                     function($x, $f) use ($op, $_curry) {
                       return call_user_func($_curry($op($f)), $x);
                     },
                     call_user_func_array($op($f), array_slice($args, 0, $n)))
        // Not enough $args, wait for some more
      : function() use ($args, $n, $f, &$_curry) {
          static $curried = true;  // Used by $arity
          return $_curry(array_merge($args, func_get_args()), $n, $f);
        };
  };

  // Find a function's arity, even if it's curried or defined as an expression
  $arity = function($f) use (&$arity, $op) {
    $rf = new ReflectionFunction($op($f));
    $sv = $rf->getStaticVariables();

    // If we're defined as $f, return $f's arity
    if (isset($sv['defined'])) return $arity($sv['f']);

    // If we're a curried version of $f, return $f's arity
    if (isset($sv['curried'])) {
        if ($sv['n']) return $sv['n'] - count($sv['args']);
        if (isset($sv['f'])) return $arity($sv['f']);
    }

    // Otherwise, reflect the arity
    return $rf->getNumberOfParameters();
  };

  // Guess $n using $arity
  $curry = function($f) use ($_curry, $arity, $op) {
    return is_callable($op($f))? $_curry(array(), $arity($f), $f)
                               : $f;
  };

  // Allow expressions (including Closures) to be named as functions
  $defun = function($name, $expr) use ($curry) {
    // Source: http://www.php.net/manual/en/functions.user-defined.php
    $valid = '/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]/';
    array_map(function($x) { if ($x[1]) trigger_error($x[0], E_USER_ERROR); },
        array(array("Invalid name for $name", !preg_match($valid, $name)),
              array("Cannot redeclare $name", function_exists($name)    ),
              array("Invalid expr for $name", !is_callable($expr, TRUE))));

    // Declare $name globally. The static $f is a poor man's lexical scope.
    eval("function {$name}() {
            static \$defined = true;  // Used by arity
            static \$f = NULL;
            \$args = func_get_args();
            return (is_null(\$f))? \$f = \$args[0]
                                 : call_user_func_array(\$f, \$args);
          }");

    // Initialise $f to $expr
    return $name($curry($expr));
  };

  // Make these functions available in curried form
 $defun('defun',   $defun);
  defun('op',      function($x) use ($op) {
                     return in_array($x, array('array', 'new'))? $op($x)
                                                               : curry($op($x));
                   });
  defun('curry',   $curry);
  defun('curry_n', $_curry);
  defun('arity',   $arity);
});

// Like call_user_func_array but works for operators too
defun('uncurry', curry(function($f, $args) {
  return call_user_func_array(op($f), $args);
}));

function compose($a, $b) {
  $funcs = array_reverse(func_get_args());
  $f     = op(array_shift($funcs));
  return function($x) use ($funcs, $f) {
    static $curried = true;
    return array_reduce($funcs,
                        function($x, $f) {
                          return call_user_func(op($f), $x);
                        },
                        call_user_func_array($f, func_get_args()));
  };
}

function call() {
  $args = func_get_args();
  $f    = op(array_shift($args));
  return curry(call_user_func_array($f, $args));
}

defun('up_to', function($n) { return $n? range(0, $n - 1) : array(); });
