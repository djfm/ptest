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
			if (isset($options['dataProviderBatch']))
			{
				$settings['dataProviderBatch'] = $options['dataProviderBatch'];
				$settings['dataProviderBatchCount'] = $options['dataProviderBatchCount'];
				$must_have_just_one_method = true;
			}
		}

		$this->methods[$m->getName()] = $settings;
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
}