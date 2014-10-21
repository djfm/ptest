<?php

namespace PrestaShop\Ptest\Testable;

/**
 * Interface for anything that can be tested.
 */
abstract class Testable
{
	private $id;
	private $parent;
	private $beforeWrapper;
	private $afterWrapper;

	public function __construct($id)
	{
		$this->setId($id);
	}

	public function getId()
	{
		return $this->id;
	}

	public function setId($id)
	{
		$this->id = $id;

		return $this;
	}

	public function getParent()
	{
		return $this->parent;
	}

	public function setParent(Testable $parent)
	{
		$this->parent = $parent;
	}

	public function setWrappers($before, $after)
	{
		$this->beforeWrapper = $before;
		$this->afterWrapper = $after;

		return $this;
	}

	public function getBeforeCall()
	{
		if ($this->beforeWrapper) {
			return [
				'type' => 'wrapper',
				'call' => $this->beforeWrapper
			];
		}

		return null;
	}

	public function getAfterCall()
	{
		if ($this->afterWrapper) {
			return [
				'type' => 'wrapper',
				'call' => $this->afterWrapper
			];
		}

		return null;
	}

	/**
	 * Returns an array of callstacks
	 */
	abstract public function unroll();
}