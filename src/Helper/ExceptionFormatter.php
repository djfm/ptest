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

		$skipped = 0;
		foreach (array_reverse($e['trace']) as $n => $t) {

			$n = count($e['trace']) - $n;

			$args = implode(', ', array_map('json_encode', $t['args']));
			if (strlen($args) >= 50) {
				$args = substr($args, 0, 47).'...';
			}

			$fun = $t['function'].'('.$args.')';
			if (isset($t['class'])) {
				// Not intersted in tedious internal details
				if ($t['class'] === 'PrestaShop\Ptest\Worker') {
					++$skipped;
					continue;
				}
				$fun = $t['class'].$t['type'].$fun;
			}

			if ($n === 1) {
				if ($skipped > 0) {
					$out .= sprintf("$padding\t     [skipped %d unintersting frames]\n", $skipped);
				}
				$bullet = '[E]';
			} else {
				$bullet = '[.]';
			}

			if (isset($t['file']) && isset($t['line'])) {
				$out .= sprintf(
					"$padding\t%s At %s:%s in %s\n",
					$bullet,
					$t['file'], $t['line'], $fun
				);
			} else {
				$out .= sprintf(
					"$padding\t%s In %s\n",
					$bullet, $fun
				);
			}
		}

		return $out;
	}
}