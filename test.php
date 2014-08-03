<?php

require('core.php');

function random() { return abs(mt_rand()); }

function up_to($n) { return $n? range(0, $n - 1) : []; }

function test($test) {
  return call_user_func_array($test,
                              array_map('random', up_to(arity($test))));
}

function failures($tests) { return array_filter(array_map('test', $tests)); }

var_dump(['failures' => failures([
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

  'can define functions' => function($x, $y, $z) {
    $name = "func{$x}";
    $f = defun($name, function($n) use ($y) { return $y + $n; });
    $result = $name($z);
    return ($result === $y + $z)? [] : get_defined_vars();
  },
])]);
