<?php

namespace PrestaShop\Ptest\Testable;

class SingleTest extends Testable
{
	private $fileName;
	private $className;
	private $methodName;
	private $examples;
	private $parallelizable;

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

	public function getCall($example)
	{
		return [
			'type' => 'test',
			'call' => [$this->className, $this->methodName, $example, false]
		];
	}

	public function unroll()
	{
		$callStacks = [];

		if ($this->parallelizable) {
			foreach ($this->examples as $example) {
				$callStack = [];

				if (($b = $this->getBeforeCall())) {
					$callStack[] = $b;
				}

				$callStack[] = $this->getCall($example);

				if (($a = $this->getAfterCall())) {
					$callStack[] = $a;
				}

				$callStacks[] = $callStack;
			}
		} else {
			$callStack = [];
			foreach ($this->examples as $example) {
				
				if (($b = $this->getBeforeCall())) {
					$callStack[] = $b;
				}

				$callStack[] = $this->getCall($example);

				if (($a = $this->getAfterCall())) {
					$callStack[] = $a;
				}

			}
			
			$callStacks[] = $callStack;
		}

		return $callStacks;
	}
}