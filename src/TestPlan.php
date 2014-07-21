<?php

namespace PrestaShop\Ptest;

use \PrestaShop\Ptest\Util\DocCommentParser;

/**
* A TestPlan describes a set of tests.
* TestPlan's may be run by the runner in any order it chooses,
* hence also in parallel.
*/
class TestPlan
{
	protected $rc;
	protected $position;
	protected $group = '';
	protected $methods = [];
	protected $must_have_just_one_method = false;

	public function __construct(\ReflectionClass $rc, $group)
	{
		$this->rc = $rc;
		$this->group = $group;
	}

	public function addMethod(\ReflectionMethod $m, array $options = array())
	{
		$dcp = new DocCommentParser($m->getDocComment());
		
		$settings = [
			'method' => $m->getName()
		];

		if ($data_provider_name = $dcp->getOption('dataProvider'))
		{
			$settings['dataProvider'] = $data_provider_name;
			if (isset($options['dataProviderKeys']))
			{
				$this->must_have_just_one_method = true;
			}
		}


		if ($expected_exception = $dcp->getOption('expectedException'))
		{
			$settings['expectedException'] = $expected_exception;
		}

		$settings = array_merge($settings, $options);

		$this->methods[$m->getName()] = $settings;
	}

	/**
	* Returns the number of individual tests,
	* i.e. the number of dots you expect to see if everything is successful.
	*/
	public function countTests()
	{
		$n = 0;
		foreach ($this->methods as $method)
		{
			if (isset($method['dataProviderKeys']))
				$n += count($method['dataProviderKeys']);
			else
				$n += 1;
		}
		return $n;
	}

	/**
	* Returns an array of strings like ["class::method" => integer_count].
	* This is useful to count the tests for reporting, as it
	* allows counting things more uniquely
	*/
	public function listCases()
	{
		$cases = [];
		foreach ($this->methods as $method)
		{
			$name = $method['class'].'::'.$method['method'];
			$n = 1;
			if (isset($method['dataProviderKeys']))
				$n = count($method['dataProviderKeys']);
			if (!isset($cases[$name]))
				$cases[$name] = 0;
			$cases[$name] += $n;
		}
		return $cases;
	}

	public function asJSON()
	{
		return json_encode([
			'class_path' => $this->rc->getFileName(),
			'class' => $this->rc->getName(),
			'group' => $this->group,
			'methods' => array_values($this->methods)
		], JSON_PRETTY_PRINT);
	}

	public function getPosition()
	{
		return $this->position;
	}

	public function setPosition($pos)
	{
		$this->position = $pos;
	}
}