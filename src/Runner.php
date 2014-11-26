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

	private $filter;

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
			$loader->setFilter($this->filter);
			$loader->setDataProviderFilter($this->dataProviderFilter);
		}
	}

	public function setMaxProcesses($p)
	{
		$this->maxProcesses = $p;
		return $this;
	}

	public function setFilter($filter)
	{
		$this->filter = $filter;
		return $this;
	}

	public function setDataProviderFilter($z)
	{
		$this->dataProviderFilter = $z;
		return $this;
	}

	public function setInformationOnly($yes = false)
	{
		$this->informationOnly = $yes;
		return $this;
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

		// die(json_encode($callStacks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

		return $callStacks;
	}

	public function countAndNumberTests(array &$callStack)
	{
		foreach ($callStack['stack'] as $i => $elem) {
			if ($elem['type'] === 'test') {
				$callStack['stack'][$i]['position'] = $this->testsCount;

				$this->results[$this->testsCount] = [
					'testName' => $this->makeTestShortName($elem),
					'testClass' => $elem['call'][1],
					'testMethod' => $elem['call'][2]
				];

				if (isset($elem['call'][3])) {
					$this->results[$this->testsCount]['testArguments'] = $elem['call'][3];
				}

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
				return isset($v['value']) ? json_encode($v['value'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $v['reference'];
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

	public function handleLog($process, $message)
	{
		if (is_string($message)) {
			echo sprintf("::: Process %'04d says: %s\n", $process['position'] + 1, $message);
		} else {
			echo sprintf(
				"::: Process %'04d says:\n%s\n\n", $process['position'] + 1,
				json_encode($message, JSON_PRETTY_PRINT)
			);
		}
	}

	public function handleTestStartMessage($process, $message)
	{
		$position = $message['test']['position'];

		$this->results[$position]['startedAt'] = time();
		$this->results[$position]['artefactsDir'] = $message['artefactsDir'];
	}

	public function handleTestSuccessMessage($process, $message)
	{
		$this->testsFinishedCount += 1;
		echo sprintf(
			"\n:-) Test %s completed with success! (%s)\n\n",
			$this->makeTestShortName($message['test']),
			$this->getProgressString()
		);

		if (!empty($message['artefactsDir'])) {
			$errorFile = $message['artefactsDir'].'/error.txt';
			if (file_exists($errorFile)) {
				@unlink($errorFile);
			}
			@file_put_contents($message['artefactsDir'].'/ok.txt', 'OK! '.date('d M Y h:i:s'));
		}

		$position = $message['test']['position'];
		$this->results[$position]['statusChar'] = '.';
		$this->results[$position]['finishedAt'] = time();
	}

	public function handleTestErrorMessage($process, $message)
	{
		$this->testsFinishedCount += 1;
		echo sprintf(
			"\n:+( Test %s failed! (%s)\n",
			$this->makeTestShortName($message['test']),
			$this->getProgressString()
		);
		$this->printSerializedException($message['exception']);
		echo "\n";

		if (!empty($message['artefactsDir'])) {
			$successFile = $message['artefactsDir'].'/ok.txt';
			if (file_exists($successFile)) {
				@unlink($successFile);
			}
			$exception = Helper\ExceptionFormatter::formatSerializedException(
				$message['exception'],
				''
			);
			@file_put_contents($message['artefactsDir'].'/error.txt', $exception);
		}

		$error = $message['exception'];
		$error['testName'] = $this->makeTestShortName($message['test']);
		
		$position = $message['test']['position'];

		$this->results[$position]['statusChar'] = 'E';
		$this->results[$position]['error'] = $error;
		$this->results[$position]['finishedAt'] = time();
	}

	public function handleMessage($process, $message)
	{
		if (is_scalar($message)) {
			echo "$message\n";
		} else {
			if (isset($message['type'])) {
				if ($message['type'] === 'log') {
					$this->handleLog($process, $message['message']);
				} elseif ($message['type'] === 'testStart') {
					$this->handleTestStartMessage($process, $message);
				} elseif ($message['type'] === 'testSuccess') {
					$this->handleTestSuccessMessage($process, $message);
				} elseif ($message['type'] === 'testError') {
					$this->handleTestErrorMessage($process, $message);
				} elseif ($message['type'] === 'exception') {
					$this->printSerializedException($message['exception']);
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
		echo Helper\ExceptionFormatter::formatSerializedException($e, $padding);
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

		$statusString = '';
		for ($p = 0; $p < $this->testsCount; $p += 1) {
			if (isset($this->results[$p]['statusChar'])) {
				$statusString .= $this->results[$p]['statusChar'];
				if (isset($this->results[$p]['error'])) {
					$errors[] = $this->results[$p]['error'];
				}
			} else {
				$unknownCount += 1;
				$statusString .= '?';
			}
		}

		echo "$statusString\n\n";

		if ($unknownCount > 0) {
			echo "WARNING, $unknownCount tests did not yield a status, the processes probably died for some reason:\n";
			for ($p = 0; $p < $this->testsCount; $p += 1) {
				if (!isset($this->results[$p]['statusChar'])) {
					echo "\t".$this->results[$p]['testName']."\n";
				}
			}
			echo "\n\n";
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
					$error['testName']
				);
				$this->printSerializedException($error, ">>\t\t");
				echo "\n\n";
			}
			echo "$statusString\n\n";
		}

		/**
		 * Save statistics
		 */

		$jsonResults = json_encode($this->results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		$historyDir = 'test-history';
		if (!is_dir($historyDir)) {
			mkdir($historyDir, 0777, true);
		}
		$token = date("d M Y h.i.s").'_'.md5($jsonResults);
		$statsFile = $historyDir.'/'.$token.'.json';
		file_put_contents($statsFile, $jsonResults);


		/**
		 * Save screencasts of errors if available
		 */
		foreach ($this->results as $position => $data) {
			
			if (empty($data['artefactsDir'])) {
				continue;
			}

			$backupDir = $historyDir.'/'.$token.'_'.$position;

			Helper\FileSystem::cpr($data['artefactsDir'], $backupDir);

			// Compress screenshots
			$screenshotsDir = $backupDir.'/screenshots';
			if (is_dir($screenshotsDir)) {
				foreach (scandir($screenshotsDir) as $entry) {
					if (preg_match('/\.png$/', $entry)) {
						$screenshotPng = $screenshotsDir.'/'.$entry;
						$screenshotJpg = $screenshotsDir.'/'.basename($entry, '.png').'.jpg';
						$image = imagecreatefrompng($screenshotPng);
					    imagejpeg($image, $screenshotJpg, 50);
					    imagedestroy($image);
					    unlink($screenshotPng);
					}
				}
			}
		}

		if ($unknownCount > 0 || count($errors) > 0) {
			return 1;
		} else {
			return 0;
		}
	}
}