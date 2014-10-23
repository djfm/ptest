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

	public function countAndNumberTests(array &$callStack)
	{
		foreach ($callStack['stack'] as $i => $elem) {
			if ($elem['type'] === 'test') {
				$callStack['stack'][$i]['position'] = $this->testsCount;
				$this->testsCount += 1;
			} elseif ($elem['type'] === 'stack') {
				$this->countAndNumberTests($callStack['stack'][$i]);
			}
		}
	}

	public function run()
	{
		$this->startedAt = time();
		$this->runningProcesses = [];
		$this->stacks = $this->getCallStacks();
		$this->results = [];

		$this->testsCount = 0;
		$this->testsFinishedCount = 0;
		foreach ($this->stacks as $i => $stack) {
			$this->countAndNumberTests($this->stacks[$i]);
		}

		echo sprintf("\nFound %1\$d tests (split into %2\$d test plan(s))!\n", $this->testsCount, count($this->stacks));
		echo sprintf("Going to run them %d at a time - when possible.\n\n", $this->maxProcesses);
		
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

		return $this->afterRun();
	}

	public function afterRun()
	{
		$elapsed = time() - $this->startedAt;
		$minutes = floor($elapsed / 60);
		$seconds = $elapsed - 60 * $minutes;


		echo sprintf(
			"\n\nFinished %d tests in %d minutes and %d seconds.\n",
			$this->testsCount,
			$minutes,
			$seconds
		);

		$unknownCount = 0;
		$errors = [];

		for ($p = 0; $p < $this->testsCount; $p += 1) {
			if (isset($this->results[$p])) {
				echo $this->results[$p]['statusChar'];
				if (isset($this->results[$p]['error'])) {
					$errors[] = $this->results[$p]['error'];
				}
			} else {
				$unknownCount += 1;
				echo "?";
			}
		}
		echo "\n\n";

		if ($unknownCount > 0) {
			echo "WARNING: $unknownCount tests did not yield a status, the processes probably died for some reason.\n";
		}

		if (count($errors) > 0) {
			echo sprintf(
				"ATTENTION: there were %d error(s)!\n\n",
				count($errors)
			);
			foreach ($errors as $n => $error) {
				echo sprintf(
					"%d) %s:\n\n",
					$n + 1,
					$error['test-name']
				);
				$this->printSerializedException($error, ">>\t\t");
				echo "\n\n";
			}
		}
		

		if ($unknownCount > 0 || count($errors) > 0) {
			return 1;
		} else {
			return 0;
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

	public function makeTestShortName($test)
	{
		if(isset($test['call'][3])) {
			$args = array_map(function($v) {
				return isset($v['value']) ? json_encode($v['value']) : $v['reference'];
			}, $test['call'][3]);

			$args = implode(', ', $args);
		} else {
			$args = '';
		}


		return sprintf(
			"%s::%s(%s)",
			$test['call'][1],
			$test['call'][2],
			$args
		);
	}

	public function handleMessage($process, $message)
	{
		if (is_scalar($message)) {
			echo "$message\n";
		} else {
			if (isset($message['type'])) {
				if ($message['type'] === 'log') {
					if (is_string($message['message'])) {
						echo sprintf("::: Process %'04d says: %s\n", $process['position'] + 1, $message['message']);
					} else {
						echo sprintf(
							"::: Process %'04d says:\n%s\n\n", $process['position'] + 1,
							json_encode($message['message'], JSON_PRETTY_PRINT)
						);
					}
				} elseif ($message['type'] === 'test-success') {
					$this->testsFinishedCount += 1;
					echo sprintf(
						"\n:-) Test %s completed with success! (%s)\n\n",
						$this->makeTestShortName($message['test']),
						$this->getProgressString()
					);

					$this->results[$message['test']['position']] = ['statusChar' => '.'];

				} elseif ($message['type'] === 'test-error') {
					$this->testsFinishedCount += 1;
					echo sprintf(
						"\n:+( Test %s failed! (%s)\n",
						$this->makeTestShortName($message['test']),
						$this->getProgressString()
					);
					$this->printSerializedException($message['exception']);
					echo "\n";

					$error = $message['exception'];
					$error['test-name'] = $this->makeTestShortName($message['test']);
					$this->results[$message['test']['position']] = [
						'statusChar' => 'E',
						'error' => $error
					];
				}
			}
		}
	}

	public function getProgressString()
	{
		return sprintf(
			"finished %d/%d tests (%.2f%%)",
			$this->testsFinishedCount,
			$this->testsCount,
			(100 * $this->testsFinishedCount) / $this->testsCount
		);
	}

	public function printSerializedException($e, $padding='')
	{
		echo sprintf(
			"{$padding}At line %d in file `%s`:\n{$padding}\t%s\n",
			$e['line'], $e['file'], $e['message']
		);
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