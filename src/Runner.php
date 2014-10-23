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

	private $dataProviderFilter;

	private $informationOnly = false;

	public function __construct()
	{
		
	}

	public function initLoaders()
	{
		$this->loaders = [];

		$phpunit = new \PrestaShop\Ptest\Loader\PHPUnitLike();

		$this->loaders[] = $phpunit;

		foreach ($this->loaders as $loader) {
			$loader->setDataProviderFilter($this->dataProviderFilter);
		}
	}

	public function setMaxProcesses($p)
	{
		$this->maxProcesses = $p;
	}

	public function setDataProviderFilter($z)
	{
		$this->dataProviderFilter = $z;
	}

	public function setInformationOnly($yes = false)
	{
		$this->informationOnly = $yes;
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
		$this->initLoaders();

		$callStacks = [];
		$this->testsCount = 0;

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

	public function countTests(array $callStack)
	{
		$count = 0;
		foreach ($callStack['stack'] as $elem) {
			if ($elem['type'] === 'test') {
				$count += 1;
			} elseif ($elem['type'] === 'stack') {
				$count += $this->countTests($elem);
			}
		}
		return $count;
	}

	public function run()
	{
		$this->runningProcesses = [];
		$this->stacks = $this->getCallStacks();

		$this->testsCount = 0;
		foreach ($this->stacks as $stack) {
			$this->testsCount += $this->countTests($stack);
		}

		
		echo sprintf("\nFound %1\$d tests (split into %2\$d test plan(s))!\n", $this->testsCount, count($this->stacks));
		echo sprintf("Going to run them %d at a time - when possible.\n", $this->maxProcesses);
		
		if ($this->informationOnly) {
			return;
		}

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
	}

	public function startProcess($n)
	{
		echo '>>> Starting process '.($n+1).' of '.count($this->stacks)."!\n";

		$infile = tempnam(null, 'ptest_input');
		$outfile = tempnam(null, 'ptest_output');

		$cmd = PHP_BINARY.' '.realpath(__DIR__.'/../worker').' '.escapeshellarg($infile).' '.escapeshellarg($outfile);

		$descriptorspec = [STDIN, STDOUT, STDERR];

		$pipes = [];

		file_put_contents($infile, json_encode($this->stacks[$n], JSON_PRETTY_PRINT));

		$process = proc_open($cmd, $descriptorspec, $pipes);

		$this->runningProcesses[] = [
			'process' => $process,
			'infile' => $infile,
			'outfile' => $outfile,
			'position' => $n
		];
	}

	public function handleMessage($process, $message)
	{
		if (is_scalar($message)) {
			echo "$message\n";
		} else {
			if (isset($message['type'])) {
				if ($message['type'] === 'log') {
					echo sprintf("::: Process %'04d says: %s\n", $process['position'] + 1, $message['message']);
				}
			}
		}
	}

	public function checkRunningProcesses()
	{
		foreach ($this->runningProcesses as $n => $process) {

			$h = fopen($process['outfile'], 'r+');
			flock($h, LOCK_EX);

			clearstatcache(); // WE NEED THIS
			$size = filesize($process['outfile']);

			if ($size > 0) {
				$contents = fread($h, $size);

				foreach (explode("\n", $contents) as $line) {
					if ($line) {
						$message = @json_decode($line, true);
						if ($message) {
							$this->handleMessage($process, $message);
						}
					}
				}

				ftruncate($h, 0);
			}

			flock($h, LOCK_UN);
			fclose($h);

			$status = proc_get_status($process['process']);

			if (!$status['running']) {
				$code = $status['exitcode'] === 0 ? '[ OK ]' : "[ FAIL ({$status['exitcode']}) ]";
				echo "<<< Subprocess ".($process['position'] + 1)." finished! $code\n";
				unset($this->runningProcesses[$n]);
			}
		}
	}
}