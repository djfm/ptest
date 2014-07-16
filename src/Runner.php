<?php

namespace PrestaShop\Ptest;

class Runner
{
	protected $job_description_file;
	protected $output_file;
	protected $instance;

	public function __construct($job_description_file, $output_file, $bootstrap_file)
	{
		$this->job_description_file = $job_description_file;
		$this->job = json_decode(file_get_contents($this->job_description_file), true);
		$this->output_file = $output_file;
		$this->bootstrap_file = $bootstrap_file;
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

		require $this->job['class_path'];
		
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
					if ($n % (int)$method['dataProviderBatchCount'] === (int)$method['dataProviderBatch'])
					{
						$this->runTestMethod($method, $arguments);
					}

					$n++;
				}
			}
		}
	}

	public function runTestMethod($method, array $arguments = array())
	{
		echo $method['method']."\n";
	}
}