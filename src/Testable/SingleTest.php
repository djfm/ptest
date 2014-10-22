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

	public function getCall($example)
	{
		return [
			'type' => 'test',
			'call' => [$this->fileName, $this->className, $this->methodName, $example, false],
			'before' => $this->getBeforeCall(),
			'after' => $this->getAfterCall(),
			'maxAttempts' => $this->maxAttempts
		];
	}

	public function unroll()
	{
		$callStacks = [];

		if ($this->parallelizable) {
			foreach ($this->examples as $example) {
				$callStacks[] = [$this->getCall($example)];
			}
		} else {
			$callStack = [];
			foreach ($this->examples as $example) {
				$callStack[] = $this->getCall($example);
			}

			$callStacks[] = $callStack;
		}

		return $callStacks;
	}
}