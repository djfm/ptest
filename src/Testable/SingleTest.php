<?php

namespace PrestaShop\Ptest\Testable;

class SingleTest extends Testable
{
	private $fileName;
	private $className;
	private $methodName;
	private $examples;
	private $parallelizable;
	private $maxAttempts = 1;

	public function setFileName($fileName)
	{
		$this->fileName = $fileName;

		return $this;
	}

	public function setClassName($className)
	{
		$this->className = $className;

		return $this;
	}

	public function setMethodName($methodName)
	{
		$this->methodName = $methodName;

		return $this;
	}

	public function setExamples(array $examples)
	{
		$this->examples = $examples;

		return $this;
	}

	public function setParallelizable($yes = true)
	{
		$this->parallelizable = $yes;

		return $this;
	}

	public function setMaxAttempts($n)
	{
		$this->maxAttempts = $n;

		return $this;
	}

	public function getStack($example)
	{
		return [
			'type' => 'stack',
			'before' => $this->getBeforeCall(),
			'after' => $this->getAfterCall(),
			'stack' => [[
				'type' => 'test',
				'call' => [$this->fileName, $this->className, $this->methodName, $example, false],
				'maxAttempts' => $this->maxAttempts
			]]
		];
	}

	public function unroll()
	{
		$callStacks = [];

		if ($this->parallelizable) {
			foreach ($this->examples as $example) {
				$callStacks[] = $this->getStack($example);
			}
		} else {

			$stack = [
				'type' => 'stack',
				'before' => null,
				'after' => null,
				'stack' => []
			];

			foreach ($this->examples as $example) {
				$stack['stack'][] = $this->getStack($example);
			}

			$callStacks[] = $stack;
		}

		return $callStacks;
	}
}