<?php

namespace PrestaShop\Ptest\Helper;

class ExceptionFormatter
{
	public function formatSerializedException($e, $padding = '')
	{
		$out = sprintf(
			"{$padding}[%s] At line %d in file `%s`:\n{$padding}\n{$padding}\t%s\n{$padding}\n{$padding}\n",
			$e['class'], $e['line'], $e['file'], $e['message']
		);

		foreach (array_reverse($e['trace']) as $n => $t) {

			$n = count($e['trace']) - $n;

			$args = implode(', ', array_map('json_encode', $t['args']));
			if (strlen($args) >= 50) {
				$args = substr($args, 0, 47).'...';
			}

			$fun = $t['function'].'('.$args.')';
			if (isset($t['class'])) {
				$fun = $t['class'].$t['type'].$fun;
			}

			if (isset($t['file']) && isset($t['line'])) {
				$out .= sprintf(
					"$padding\t%d) At %s:%s in %s\n",
					$n,
					$t['file'], $t['line'], $fun
				);
			} else {
				$out .= sprintf(
					"$padding\t%d) In %s\n",
					$n, $fun
				);
			}
		}

		return $out;
	}
}