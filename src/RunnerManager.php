<?php

namespace PrestaShop\Ptest;

class RunnerManager
{
	private $test_plans = [];
	private $bootstrap_file = null;
	private $max_processes = 5;
	private $running_processes = [];

	public function __construct(array $test_plans, array $options = array())
	{
		$this->test_plans = $test_plans;
		foreach (['bootstrap_file', 'max_processes'] as $option)
		{
			if (isset($options[$option]) && $options[$option])
				$this->$option = $options[$option];
		}
	}

	public function run()
	{
		while (count($this->test_plans) > 0 || count($this->running_processes) > 0)
		{
			if (count($this->test_plans) > 0 && count($this->running_processes) < $this->max_processes)
			{
				$this->startNewProcess();
			}

			$this->checkRunningProcesses();

			// If we're just waiting for processes to finish, slow down
			if (count($this->test_plans) === 0 || count($this->running_processes) == $this->max_processes)
				sleep(1);
		}
	}

	public function startNewProcess()
	{
		$stdout_file = tempnam(null, 'ptest_stdout');
		$stderr_file = tempnam(null, 'ptest_stderr');

		$io = [
			0 => ['file', '/dev/null', 'r'],
			1 => ['file', $stdout_file, 'w'],
			2 => ['file', $stderr_file, 'w']
		];

		$test_plan = array_shift($this->test_plans);
		
		$test_plan_file = tempnam(null, 'ptest_plan');
		$output_file = tempnam(null, 'ptest_results');

		$worker_path = realpath(__DIR__.'/../worker.php');

		$pipes = [];

		file_put_contents($test_plan_file, $test_plan->asJSON());

		$command_parts = [
			PHP_BINARY,
			$worker_path, 'work',
			$test_plan_file,
			$output_file,
		];

		if ($this->bootstrap_file)
		{
			$command_parts[] = '--bootstrap';
			$command_parts[] = $this->bootstrap_file;
		}

		$command = implode(' ', array_map(function($arg){
			return escapeshellcmd($arg);
		}, $command_parts));

		//echo $command."\n"; die();
		$res = proc_open($command, $io, $pipes);

		$this->running_processes[$test_plan_file] = [
			'stdout_file' => $stdout_file,
			'stderr_file' => $stderr_file,
			'output_file' => $output_file,
			'proc'        => $res,
			'test_plan'	  => $test_plan
		];
	}

	public function checkRunningProcesses()
	{
		foreach ($this->running_processes as $test_plan_file => $data)
		{
			$status = proc_get_status($data['proc']);
			if ($status['running'] === false)
			{
				echo "Test $test_plan_file finished:\n";
				echo file_get_contents($data['stdout_file']);
				echo "\n\n";


				unset($this->running_processes[$test_plan_file]);
			}
		}
	}
}