<?php

require('core.php');

function random() { return abs(mt_rand()); }

function test($test) {
  return call_user_func_array($test,
                              array_map('random', up_to(arity($test))));
}

function failures($tests) { return array_filter(array_map('test', $tests)); }

$failures = failures([
  'call calls' => function($x, $y) {
    $f = function($a) use ($x) { return $x; };
    $lhs = call($f, $y);
    return ($lhs === $x)? [] : get_defined_vars();
  },

  '+ is callable' => function($x, $y) {
    list($lhs, $rhs) = [call('+', $x, $y), $x + $y];
    return ($lhs === $rhs)? [] : get_defined_vars();
  },

  'instanceof spots instances' => function() {
    $o = new stdClass;
    $result = call('instanceof', $o, 'stdClass');
    return $result? [] : get_defined_vars();
  },

  'instanceof spots non-instances' => function() {
    $o = new Exception;
    $result = call('instanceof', $o, 'stdClass');
    return $result? get_defined_vars() : [];
  },

  'array is callable' => function($x, $y, $z) {
    $lhs = call('array', $x, $y, $z);
    return ($lhs === [$x, $y, $z])? [] : get_defined_vars();
  },

  'can define functions' => function($x, $y, $z) {
    $name = "func{$x}";
    $f = defun($name, function($n) use ($y) { return $y + $n; });
    $result = $name($z);
    return ($result === $y + $z)? [] : get_defined_vars();
  },
]);

$failures? var_dump(['Test failures' => $failures])
         : (print "All tests passed\n");
