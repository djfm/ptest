<?php

namespace PrestaShop\Ptest;

class Runner
{
	/**
	 * Where to look for tests.
	 * Could be a file or a directory.
	 */
	private $root;

	public function setRoot($root)
	{
		$this->root = $root;

		return $this;
	}

	public function scan()
	{
		$files = [];

		if (is_dir($this->root)) {
			$rdi = new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS);
			$rii = new \RecursiveIteratorIterator($rdi, \RecursiveIteratorIterator::CHILD_FIRST);

			foreach ($rii  as $name => $info) {
				if ($info->isFile()) {
					$files[] = $info;
				}
			}

		} else {
			$files[] = new \SplFileInfo($this->root);
		}

		return $files;
	}

	public function run()
	{
		$this->scan();

		echo "hi";
	}
}