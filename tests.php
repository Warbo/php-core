<?php

require(__DIR__ . '/vendor/autoload.php');

// Perform some rudimentary property checks

function test($test) {
  return call_user_func_array($test,
                              array_map('random', up_to(arity($test))));
}

function failures($tests) { return array_filter(array_map('test', $tests)); }

$failures = failures(array(
  'call calls' => function($x, $y) {
    $f = function($a) use ($x) { return $x; };
    $lhs = call($f, $y);
    return ($lhs === $x)? 0 : get_defined_vars();
  },

  '+ is callable' => function($x, $y) {
    list($lhs, $rhs) = array(call('+', $x, $y), $x + $y);
    return ($lhs === $rhs)? 0 : get_defined_vars();
  },

  'instanceof spots instances' => function() {
    $o = new stdClass;
    $result = call('instanceof', $o, 'stdClass');
    return $result? 0 : get_defined_vars();
  },

  'instanceof spots non-instances' => function() {
    $o = new Exception;
    $result = call('instanceof', $o, 'stdClass');
    return $result? get_defined_vars() : 0;
  },

  'array is callable' => function($x, $y, $z) {
    $lhs = call('array', $x, $y, $z);
    return ($lhs === array($x, $y, $z))? 0 : get_defined_vars();
  },

  'can define functions' => function($x, $y, $z) {
    $name = "func{$x}";
    $f = defun($name, function($n) use ($y) { return $y + $n; });
    $result = $name($z);
    return ($result === $y + $z)? 0 : get_defined_vars();
  },

  'uncurry uncurries' => function($x, $y, $z) {
    $f = uncurry(function($a, $b, $c) { return $a + $b + $c; });
    $result = $f(func_get_args());
    return ($result === $x + $y + $z)? 0 : get_defined_vars();
  },
));

$failures? var_dump(array('Test failures' => $failures))
         : (print "All tests passed\n");
