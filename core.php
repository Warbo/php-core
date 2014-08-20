<?php
// Work around some PHP language deficiencies

function error($msg) { trigger_error($msg, E_USER_ERROR); }

call_user_func(function() {
  // Function equivalents to operators
  // https://bugs.php.net/bug.php?id=66368
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

  $op = function($f_) use ($operators) {
          $f = (is_string($f_) && isset($operators[$f_]))? $operators[$f_]
                                                         : $f_;
          return is_callable($f)? $f
                                : error(var_export($f, true) .
                                        ' is not an operator or function');
        };

  // Curry functions: accumulates $n $args then calls $f
  $curry_ = function($args, $n, $f_) use (&$curry_, $op) {
              $f = $op($f_);
              if (!is_callable($f)) error("Cannot curry {$f_}");

              return (count($args) >= $n)
                // Send $n $args to $f, uncurry & apply to remaining $args
                ? array_reduce(array_slice($args, $n),
                               function($f, $x) use ($op, $curry_) {
                                 return call_user_func($op($f), $x);
                               },
                               call_user_func_array($f,
                                                    array_slice($args, 0, $n)))
                // Not enough $args, wait for some more
                : function() use ($args, $n, $f, &$curry_) {
                    static $curried = true;  // Used by $arity
                    return $curry_(array_merge($args, func_get_args()), $n, $f);
                  };
            };

  // Find a function's arity, even if it's curried or defined as an expression
  $arity = function($f_) use (&$arity, $op) {
             $f = $op($f_);
             if (!is_callable($f)) error("Can't get arity of {$f_}");
             $rf = new ReflectionFunction($f);
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
  $curry = function($f_) use ($curry_, $arity, $op) {
             $f = $op($f_);
             if (!is_callable($f)) error("Can't curry {$f_}");
             return $curry_(array(), $arity($f), $f);
           };

  // Allow expressions (including Closures) to be named as functions
  $defun = function($name, callable $expr) use ($curry) {
             // Source: http://www.php.net/manual/en/functions.user-defined.php
             $valid = '/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/';
             array_map(function($x) { if ($x[1]) error($x[0]); },
                       array(array("Invalid name for $name",
                                   !preg_match($valid, $name)),
                             array("Cannot redeclare $name",
                                   function_exists($name))));

             // Declare $name globally; static $f is a poor man's lexical scope.
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
  defun('curry',  $curry);
  defun('curry_', $curry_);
  defun('arity',  $arity);
});

defun('key_map', function($f, $a) {
                   return array_combine(array_keys($a),
                                        array_map(op($f), array_keys($a), $a));
                 });

defun('defuns', key_map('defun'));

defuns(array(
  // Like call_user_func_array but works for operators too
  'uncurry' => function($f, $args) {
                 return call_user_func_array(op($f), $args);
               },

  // Like range but handles 0 correctly
  'up_to' => function($n) { return $n? range(0, $n - 1) : array(); },

  // Random Naturals
  'random' => function($_) { return abs(mt_rand()); }
));

// Like call_user_func but uses curry & op. We don't curry call since it's nary.
function call() {
  $args = func_get_args();
  $f    = op(array_shift($args));
  return call_user_func_array($f, $args);
}
