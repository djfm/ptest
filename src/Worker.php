<?php

namespace PrestaShop\Ptest;

class Worker
{
	private $infile;
	private $outfile;

	private $references = [];

	public function __construct($infile, $outfile)
	{
		$this->infile = $infile;
		$this->outfile = $outfile;
	}

	public function sendMessage($obj)
	{
		file_put_contents(
			$this->outfile,
			json_encode($obj)."\n",
			FILE_APPEND | LOCK_EX
		);
	}

	public function log($message) {
		$this->sendMessage([
			'type' => 'log',
			'message' => $message
		]);
	}

	public function call($callDescription)
	{
		list($fileName, $className, $methodName, $arguments, $isStatic) = $callDescription;

		$shortName = "$className::$methodName";

		$args = [];

		if (is_array($arguments)) {
			foreach ($arguments as $arg) {
				if (isset($arg['value'])) {
					$args[] = $arg['value'];
				} elseif (isset($arg['reference'])) {
					$args[] = $this->references[$arg['reference']];
				}
			}
		}

		if (!empty($args)) {
			$this->log(sprintf("Running $shortName with arguments: %s", json_encode($args)));
		} else {
			$this->log("Running $shortName...");
		}

		if (!class_exists($className)) {
			require $fileName;
		}
		
		if ($isStatic) {
			return call_user_func_array([$className, $methodName], $args);
		} else {
			return call_user_func_array([new $className(), $methodName], $args);
		}

	}

	public function work()
	{
		$this->stack = json_decode(file_get_contents($this->infile), true);

		foreach ($this->stack as $step) {

			if ($step['type'] === 'wrapper') {

				$this->call($step['call']);

			} elseif ($step['type'] === 'test') {

				$maxAttempts = isset($step['maxAttempts']) ? $step['maxAttempts'] : 1; 

				for ($attempt = 0; $attempt < $maxAttempts; $attempt++)
				{
					$canceled = false;

					if ($step['before']) {
						try {
							$this->call($step['before']['call']);
						} catch (\Exception $e) {
							$canceled = true;
							// TODO: log error
						}
					}

					$success = false;

					try {

						if (!$canceled) {

							$this->call($step['call']);
							$success = true;

						} else {

							// TODO: log cancelled

						}

					} catch (\Exception $e) {
						
						$marksUndeniableTestFailure = [$e, 'marksUndeniableTestFailure'];
						$canRetry = !is_callable($marksUndeniableTestFailure) || !$marksUndeniableTestFailure();
						
						if (!$canRetry || $attempt >= $maxAttempts) {
							
							// TODO: log error
							
							$canceled = true;

						}

					}

					if ($step['after']) {
						try {
							$this->call($step['after']['call']);
						} catch (\Exception $e) {
							// TODO: log error
						}
					}

					if ($success || $canceled) {
						break;
					}
				}
			}
		}
	}
}