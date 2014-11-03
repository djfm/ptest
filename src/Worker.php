<?php

namespace PrestaShop\Ptest;

class Worker
{
	private $infile;
	private $outfile;
	private $instance;
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

	public function work()
	{
		$this->stack = json_decode(
			file_get_contents($this->infile),
			true
		);
		$ok = $this->processStack($this->stack, $logErrors = true);

		return $ok ? 0 : 1;
	}

	public function doProcessStack(array $stack, $logErrors)
	{
		$ok = true;

		if ($stack['before']) {
			try {
				$this->call($stack['before']['call'], 'before');
			} catch (\Exception $e) {
				$this->logException($e);
				$ok = false;
			}
		}

		if ($ok) {
			foreach ($stack['stack'] as $item) {
				if ($item['type'] === 'test') {
					$ok = $this->processTest($item, $logErrors);
				} elseif ($item['type'] === 'stack') {
					$ok = $this->processStack($item, $logErrors);
				}
			}
		}

		if ($stack['after']) {
			try {
				$this->call($stack['after']['call'], 'after');
			} catch (\Exception $e) {
				if ($ok) {
					$this->logException($e);
					$ok = false;
				}
			}
		}

		return $ok;
	}

	public function processStack(array $stack, $logErrors)
	{
		$maxAttempts = isset($stack['maxAttempts']) ? (int)$stack['maxAttempts'] : 1;

		if (getenv('NO_RETRY')) {
			$maxAttempts = 1;
		}

		for ($attempt = 0; $attempt < $maxAttempts; $attempt += 1) {
			$ok = $this->doProcessStack($stack, $logErrors = ($attempt + 1 >= $maxAttempts));
			if ($ok) {
				return true;
			} elseif ($ok === false) {
				$this->log('Retrying current stack...');
			} else {
				// if doProcessStack returned a falsey value
				// that is not === false, then by convention
				// we don't retry
				return null;
			}
		}

		return false;
	}

	public function logSuccess($test)
	{
		$this->sendMessage([
			'type' => 'test-success',
			'test' => $test
		]);
	}

	public function logError($test, \Exception $e)
	{
		$serializedException = [
			'class' => get_class($e),
			'message' => $e->getMessage(),
			'file' => $e->getFile(),
			'line' => $e->getLine(),
			'trace' => $e->getTrace()
		];

		$this->sendMessage([
			'type' => 'test-error',
			'test' => $test,
			'exception' => $serializedException
		]);
	}

	public function logException(\Exception $e)
	{
		$serializedException = [
			'class' => get_class($e),
			'message' => $e->getMessage(),
			'file' => $e->getFile(),
			'line' => $e->getLine(),
			'trace' => $e->getTrace()
		];

		$this->sendMessage([
			'type' => 'exception',
			'exception' => $serializedException
		]);
	}

	/**
	 * Returns true in case of success,
	 * false when failed but test may be retried,
	 * null when failed but test may not be retried.
	 */
	public function processTest(array $test, $logErrors)
	{
		$ok = true;

		try {
			$this->call($test['call'], 'test');
		} catch (\Exception $e) {
			if (isset($test['expectedException']) && $e instanceof $test['expectedException']) {
				// that's OK
			} else {
				$ok = false;

				$marksUndeniableTestFailure = [$e, 'marksUndeniableTestFailure'];
				$reallyDead = is_callable($marksUndeniableTestFailure) && $marksUndeniableTestFailure();

				if ($reallyDead) {
					$logErrors = true;
					$ok = null;
				}

				if ($logErrors) {
					$this->logError($test, $e);
				}
			}
		}

		if ($ok) {
			$this->logSuccess($test);
		}

		return $ok;
	}

	public function call($callDescription, $type)
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

			// We renew the instance on Before or if we have none
			if ($type === 'before' || !$this->instance) {
				$this->instance = new $className();
			}

			if ($type === 'test') {
				$aboutToStart = [$this->instance, 'aboutToStart'];
				if (is_callable($aboutToStart)) {
					$aboutToStart($methodName, $args);
				}
			}

			$ret = call_user_func_array([$this->instance, $methodName], $args);

			// Store for @depends tests!
			$this->references[$methodName] = $ret;

			// We kill it on After
			if ($type === 'after') {
				$this->instance = null;
			}

			return $ret;
		}

	}
}