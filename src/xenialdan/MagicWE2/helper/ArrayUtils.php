<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\helper;

use function current;
use function reset;

class ArrayUtils{

	public static function advanceWrap(&$array) : array{
		$result = [key($array), current($array)];
		if(!self::hasNext($array)){
			reset($array);
		}else{
			next($array);
		}
		return $result;
	}

	public static function regressWrap(&$array) : array{
		$return = [key($array), current($array)];
		if(!self::hasPrev($array)){
			end($array);
		}else{
			prev($array);
		}
		return $return;
	}

	public static function hasNext(array $array) : bool{
		return next($array) !== false || key($array) !== null;
	}

	public static function hasPrev(array $array) : bool{
		return prev($array) !== false || key($array) !== null;
	}

	public static function setPointerToValue(array &$array, $value) : void{
		reset($array);
		#var_dump($array,current($array),$value);
		while(current($array) !== $value && self::hasNext($array)) next($array);
	}
}