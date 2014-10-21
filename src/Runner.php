<?php

namespace PrestaShop\Ptest;

class Runner
{
	/**
	 * Where to look for tests.
	 * Could be a file or a directory.
	 */
	private $root;

	/**
	 * The available loaders.
	 */
	private $loaders = [];

	private $maxProcesses = 1;

	public function __construct()
	{
		$this->loaders[] = new \PrestaShop\Ptest\Loader\PHPUnitLike();
	}

	public function setMaxProcesses($p)
	{
		$this->maxProcesses = $p;
	}

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

	public function getCallStacks()
	{
		$callStacks = [];

		foreach ($this->scan() as $file) {
			foreach ($this->loaders as $loader) {
				$testable = $loader->load($file);
				if ($testable) {
					$callStacks = array_merge($callStacks, $testable->unroll());
					break;
				}
			}
		}

		return $callStacks;
	}

	public function run()
	{
		$this->runningProcesses = [];
		$this->stacks = $this->getCallStacks();
		$s = 0;

		while ($s < count($this->stacks) || !empty($this->runningProcesses)) {

			$this->checkRunningProcesses();

			if ($s < count($this->stacks)) {
				if (count($this->runningProcesses) < $this->maxProcesses) {
					$this->startProcess($s);
					$s += 1;
				} else {
					sleep(1);
				}

			} else {
				sleep(1);
			}
		}
		// PHP_BINARY
	}

	public function startProcess($n)
	{
		echo 'Starting process '.($n+1).' of '.count($this->stacks)."!\n";

		$infile = tempnam(null, 'ptest_input');
		$outfile = tempnam(null, 'ptest_output');

		$cmd = PHP_BINARY.' '.realpath(__DIR__.'/../worker').' '.escapeshellarg($infile).' '.escapeshellarg($outfile);

		echo "$cmd\n";

		$descriptorspec = [STDIN, STDOUT, STDERR];

		$pipes = [];

		$process = proc_open($cmd, $descriptorspec, $pipes);

		$this->runningProcesses[] = [
			'process' => $process
		];
	}

	public function checkRunningProcesses()
	{
		foreach ($this->runningProcesses as $n => $process) {
			if (!proc_get_status($process['process'])['running']) {
				echo "(subprocess finished)\n";
				unset($this->runningProcesses[$n]);
			}
		}
	}
}