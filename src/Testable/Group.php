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

			$callStacks = null;

			if (($b = $this->getBeforeCall())) {
				$callStacks = [[$b]];
			}

			foreach ($this->getChildren() as $child) {
				$childCallStacks = $child->unroll();

				if ($callStacks === null) {
					$callStacks = $childCallStacks;
				} else {
					$newCallStacks = [];
					foreach ($callStacks as $callStack) {
						foreach ($childCallStacks as $childCallStack) {
							$newCallStacks[] = array_merge($callStack, $childCallStack);
						}
					}
					$callStacks = $newCallStacks;
				}
			}

			if (($a = $this->getAfterCall())) {
				foreach ($callStacks as $i => $callStack) {
					$callStacks[$i][] = $a;
				}
			}

			$unrolled = $callStacks;

		} elseif ($this->type === GROUP::PARALLEL) {
			foreach ($this->getChildren() as $child) {
				foreach ($child->unroll() as $callStack) {
					if (($b = $this->getBeforeCall())) {
						array_unshift($callStack, $b);
					}
					if (($a = $this->getAfterCall())) {
						$callStack[] = $a;
					}
					$unrolled[] = $callStack;
				}
			}
		}

		return $unrolled;
	}
}