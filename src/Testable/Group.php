<?php

namespace PrestaShop\Ptest\Testable;

class Group extends Testable
{
	private $children = [];
	private $type;

	const SEQUENCE = 0;
	const PARALLEL = 1;

	public function __construct($id, $type)
	{
		parent::__construct($id);
		$this->type = $type;
	}

	public function getType()
	{
		return $this->type;
	}

	public function addChild($child)
	{
		$child->setParent($this);
		$this->children[] = $child;

		return $this;
	}

	public function getChildren()
	{
		return $this->children;
	}

	public function unroll()
	{
		$unrolled = [];

		if ($this->type === Group::SEQUENCE) {

			foreach ($this->getChildren() as $child) {
				$childCallStacks = $child->unroll();

				if (empty($unrolled)) {
					$unrolled = $childCallStacks;
				} else {
					$newUnrolled = [];
					foreach ($unrolled as $prev) {
						foreach ($childCallStacks as $next) {
							$newUnrolled[] = [
								'type' => 'stack',
								'stack' => [$prev, $next],
								'before' => $this->getBeforeCall(),
								'after' => $this->getAfterCall()
							];
						}
					}
					$unrolled = $newUnrolled;
				}
			}

		} elseif ($this->type === GROUP::PARALLEL) {
			foreach ($this->getChildren() as $child) {
				foreach ($child->unroll() as $callStack) {
					$unrolled[] = [
						'type' => 'stack',
						'stack' => [$callStack],
						'before' => $this->getBeforeCall(),
						'after' => $this->getAfterCall()
					];
				}
			}
		}

		return $unrolled;
	}
}