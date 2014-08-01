<?php

namespace PrestaShop\Ptest;

class RunnerManager
{
	private $test_plans = [];
	private $cases = [];
	private $tests_count = 0;
	private $plans_count;
	private $bootstrap_file = null;
	private $max_processes = 5;
	private $running_processes = [];
	private $stdout = [];
	private $stderr = [];
	private $errors = [];
	private $results = [];
	private $test_token = 1;
	private $started_at;
	private $only_display_info = false;

	public function __construct(array $test_plans, array $options = array())
	{
		$this->test_plans = $test_plans;
		$n = 0;
		foreach ($test_plans as $k => $test_plan)
		{
			$cases = $test_plan->listCases();
			foreach ($cases as $name => $count)
			{
				$this->cases[$name] = (isset($this->cases[$name]) ? $this->cases[$name] : 0) + $count;
				$this->tests_count += $count;
			}

			$test_plans[$k]->setPosition($n);
			$n++;
		}
		$this->plans_count = $n;

		foreach (['bootstrap_file', 'max_processes', 'only_display_info'] as $option)
		{
			if (isset($options[$option]) && $options[$option])
				$this->$option = $options[$option];
		}
	}

	public function run()
	{
		$msg = sprintf(
			"Found %1\$d test cases to run, totalling %2\$d tests, split across %3\$d execution plans (gonna run them %4\$d by %4\$d).\n",
			count($this->cases),
			$this->tests_count,
			$this->plans_count,
			$this->max_processes
		);

		echo $msg;
		echo "Results will be displayed as they come in, then summarized when every process has endend.\n\n";

		if ($this->only_display_info)
		{
			echo "\n[Returning without running tests, as the --info/-i flag was provided]\n";
			return;
		}

		$this->started_at = time();

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

		return $this->afterRun();
	}

	public function afterRun()
	{
		echo "\n\n";
		
		ksort($this->stdout);
		ksort($this->stderr);
		ksort($this->errors);
		ksort($this->results);

		if (count($this->errors) > 0)
			$this->errors = call_user_func_array('array_merge', array_values($this->errors));
		if (count($this->results) > 0)
			$this->results = call_user_func_array('array_merge', $this->results);

		
		for ($position = 0; $position < $this->plans_count; $position++)
		{
			$output = trim($this->stdout[$position]);
			if ($output !== '')
			{
				echo sprintf("Output of TestPlan #%d:\n\n%s\n\n\n", $position, $output);
			}
			$error = trim($this->stderr[$position]);
			if ($error !== '')
			{
				echo sprintf("Stderr of TestPlan #%d:\n\n%s\n\n\n", $position, $error);
			}
		}

		if (count($this->errors) > 0)
		{
			echo sprintf("Oops! There were %d errors: \n\n", count($this->errors));
		}

		foreach ($this->errors as $n => $error)
		{
			echo sprintf("%d) %s\n\n", $n+1, $error['step']);
			echo sprintf("\t%s\n\n", $error['message']);
			if (isset($error['kind']) && $error['kind'] === 'exception')
			{
				echo sprintf("\tException '%s' (at `%s:%s`): \n", $error['exception_class'], $error['file'], $error['line']);
				foreach ($error['trace'] as $t)
				{
					if (!empty($t['file']) && !empty($t['line']) && !empty($t['class']) && !empty($t['type']))
					{
						echo sprintf("\t\tat `%s:%s` in '%s%s%s'\n",
							$t['file'], $t['line'], $t['class'], $t['type'], $t['function']
						);
					}
					elseif (!empty($t['class']) && !empty($t['type']))
					{
						echo sprintf("\t\tin '%s%s%s'\n",
							$t['class'], $t['type'], $t['function']
						);
					}
					elseif (!empty($t['function']))
					{
						echo sprintf("\t\tin '%s'\n",
							$t['function']
						);
					}
				}
			}
			echo "\n";
		}

		echo "Execution Summary:\n";
		foreach ($this->results as $result)
		{
			echo $result['status'];
		}
		echo "\n";
		$elapsed = time() - $this->started_at;
		$minutes = floor($elapsed / 60);
		$seconds = $elapsed - 60 * $minutes;
		echo sprintf("Tests: %d, Errors: %d, Time: %dm:%ds\n", count($this->results), count($this->errors), $minutes, $seconds);

		echo "\n";

		if (count($this->errors) === 0)
			return 0;
		else
			return 1;
	}

	public function startNewProcess()
	{
		$stdout_file = tempnam(null, 'ptest_stdout');
		$stderr_file = tempnam(null, 'ptest_stderr');

		$io = [
			0 => STDIN,
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

		$res = proc_open($command, $io, $pipes, null, ['TEST_TOKEN' => $this->test_token]);

		$this->test_token++;

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
			// used to display stuff in a consistent order
			$pos = $data['test_plan']->getPosition();

			$status = proc_get_status($data['proc']);
			if ($status['running'] === false)
			{
				$exited_successfully = false;

				$this->stdout[$pos] = file_get_contents($data['stdout_file']);
				$this->stderr[$pos] = file_get_contents($data['stderr_file']);
				$this->results[$pos] = [];

				foreach (explode("\n", file_get_contents($data['output_file'])) as $line)
				{
					$result = json_decode($line, true);
					if (isset($result['type']) && $result['type'] === 'result')
					{
						echo sprintf("%s: %s\n", $result['test_name'], $result['status']);
						$this->results[$pos][] = $result;
					}
					elseif (isset($result['type']) && $result['type'] === 'error')
					{
						if (!isset($this->errors[$pos]))
							$this->errors[$pos] = [];
						$this->errors[$pos][] = $result;
					}
					elseif (isset($result['type']) && $result['type'] === 'successful_exit')
					{
						$exited_successfully = true;
					}
				}

				if (!$exited_successfully)
				{
					if (!isset($this->errors[$pos]))
							$this->errors[$pos] = [];

					$this->errors[$pos][] = [
						'step' => 'He\'s dead, Jim!',
						'message' => 'Test process died, and we can\'t easily know why.'
					];
				}

				unset($this->running_processes[$test_plan_file]);
			}
		}
	}
}