<?php

namespace PrestaShop\Ptest;

class Runner
{
	protected $job_description_file;
	protected $output_file;
	protected $instance;
	protected $classsetup_ok = false;
	protected $classteardown_ok = false;

	public function __construct($job_description_file, $output_file, $bootstrap_file)
	{
		$this->job_description_file = $job_description_file;
		$this->job = json_decode(file_get_contents($this->job_description_file), true);
		$this->output_file = $output_file;
		$this->bootstrap_file = $bootstrap_file;
	}

	private function log(array $data)
	{
		file_put_contents($this->output_file, json_encode($data)."\n", FILE_APPEND);
	}

	private function logException(\Exception $e, $step)
	{
		$this->log([
			'type' => 'error',
			'kind' => 'exception',
			'exception_class' => get_class($e),
			'step' => $step,
			'message' => $e->getMessage(),
			'file' => $e->getFile(),
			'line' => $e->getLine(),
			'trace' => $e->getTrace()
		]);
	}

	private function makeInstance()
	{
		$class = '\\'.$this->job['class'];
		if (!class_exists($class, false))
			require_once $this->job['class_path'];

		return new $class;
	}

	private function getInstance()
	{
		if (!$this->instance)
			$this->instance = $this->makeInstance();
		return $this->instance;
	}

	public function run()
	{
		if ($this->bootstrap_file)
		{
			require $bootstrap_file;
		}

		$call_before = ['setUpBeforeClass', 'beforeAll'];
		$call_after = ['afterAll', 'tearDownAfterClass'];

		$this->classsetup_ok = $this->callMethods($call_before, true);
		
		foreach ($this->job['methods'] as $method)
		{
			if (!isset($method['dataProvider']))
			{
				$this->runTestMethod($method);
			}
			else
			{
				$obj = $this->getInstance();
				$data = $obj->{$method['dataProvider']}();
				
				$n = 0;
				foreach ($data as $arguments)
				{
					if (
						!isset($method['dataProviderBatchCount']) ||
						!isset($method['dataProviderBatch']) ||
						$n % (int)$method['dataProviderBatchCount'] === (int)$method['dataProviderBatch']
					)
					{
						$this->runTestMethod($method, $arguments);
					}

					$n++;
				}
			}
		}

		$this->classteardown_ok = $this->callMethods($call_after, true);

		$this->log(['type' => 'successful_exit']);
	}

	public function callMethods(array $names, $tryStaticFirst = false)
	{
		$ok = true;
		$obj = $this->getInstance();
		foreach ($names as $name)
		{
			try {
				if ($tryStaticFirst)
				{
					$rc = new \ReflectionClass($obj);
					if ($rc->hasMethod($name)) 
					{
						$m = $rc->getMethod($name);

						if ($m->isPublic() && $m->isStatic())
						{
							$m->invoke(null);
							continue;
						}
					}
				}

				$callable = [$obj, $name];
				if (is_callable($callable))
				{
					call_user_func($callable);
				}
			} catch (\Exception $e) {
				$ok = false;
				$this->logException($e, $name);
			}
		}

		return $ok;
	}

	public function runTestMethod($method, array $arguments = array())
	{
		$name = $method['method'];
		$test_name = '\\'.$this->job['class'].'::'.$name;
		if (count($arguments) > 0)
			$test_name .= ' with data: '.json_encode($arguments);

		if (!$this->classsetup_ok)
		{
			$this->log([
				'test_name' => $test_name,
				'type' => 'result',
				'status' => 'B'
			]);
			return;
		}

		$call_before = ['setUp', 'beforeEach', 'before'.ucfirst($name)];
		$call_after = ['after'.ucfirst($name), 'afterEach', 'tearDown'];

		$setup_ok = $this->callMethods($call_before, false);
		$execution_ok = false;

		$obj = $this->getInstance();

		if ($setup_ok)
		{
			try {
				$res = call_user_func_array([$obj, $name], $arguments);
				$execution_ok = true;
			} catch (\Exception $e) {
				if (isset($method['expectedException']) && ($e instanceof $method['expectedException']))
				{
					$execution_ok = true;
				}
				else
				{
					$this->logException($e, $test_name);
				}
			}
		}

		$teardown_ok = $this->callMethods($call_after, false);

		$status = '?';

		if ($setup_ok)
		{
			if ($execution_ok && $teardown_ok)
				$status = '.';
			elseif ($execution_ok && !$teardown_ok)
				$status = 'a';
			elseif (!$execution_ok && $teardown_ok)
				$status = 'E';
			elseif (!$execution_ok && !$teardown_ok)
				$status = 'x';
		}
		elseif (!$setup_ok)
		{
			if ($teardown_ok)
				$status = 'b';
			elseif (!$teardown_ok)
				$status = 'd';
		}

		$this->log([
			'test_name' => $test_name,
			'type' => 'result',
			'status' => $status
		]);
	}
}