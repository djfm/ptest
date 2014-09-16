<?php

namespace PrestaShop\Ptest\Loader;

use \PrestaShop\Ptest\Util\DocCommentParser;
use \PrestaShop\Ptest\TestPlan;

class Basic implements LoaderInterface
{
	protected $test_plans = [];
	protected $filter = null;
	protected $dp_filters = [];

	public function loadTests(\ReflectionClass $rc)
	{
		foreach ($rc->getMethods() as $method)
		{
			if (
				$method->isPublic() &&
				!$method->isAbstract() &&
				!$method->isStatic() &&
				!$method->isConstructor() &&
				!$method->isDestructor()
			)
			{
				$dcp = new DocCommentParser($method->getDocComment());

				if (
					preg_match('/^test/', $method->getName()) ||
					$dcp->hasOption('test')
				)
				{
					if ($this->filter)
					{
						if (!preg_match('#'.$this->filter.'#', $method->getName()))
							continue;
					}

					$group = $dcp->getOption('parallel', 'default');
					$dataProvider = $dcp->getOption('dataProvider', null);

					if ($dataProvider && $dcp->hasOption('parallelize'))
					{
						$obj = $rc->newInstance();
						$data = $obj->$dataProvider();
						
						$n = $dcp->getOption('parallelize', 1);

						$keys = [];

						$i = 0;
						foreach ($data as $key => $value)
						{
							if (isset($this->dp_filters[$method->getName()]))
							{
								$accept = false;

								foreach ($this->dp_filters[$method->getName()] as $filters)
								{
									foreach ($filters as $p => $regexp)
									{
										if (isset($value[$p]) && 
											(is_string($value[$p]) || is_numeric($value[$p])) &&
											preg_match("/$regexp/", $value[$p])
										)
										{
											$accept = true;
										}
									}
								}

								if (!$accept)
									continue;
							}

							$b = $i % $n;
							$pgroup = "$group (parallel batch " . ($b + 1) . " of max $n)";
							
							if (!isset($keys[$pgroup]))
								$keys[$pgroup] = [];

							$keys[$pgroup][$key] = $b;

							$i++;
						}

						foreach ($keys as $pgroup => $pkeys)
						{
							$this->test_plans[$pgroup] = new TestPlan($rc, $pgroup);
							$this->test_plans[$pgroup]->addMethod($method, [
								'dataProviderKeys' => $pkeys,
								'testsCount' => count($pkeys)
							]);
						}
					}
					else
					{
						if (!isset($this->test_plans[$group]))
						{
							$this->test_plans[$group] = new TestPlan($rc, $group);
						}

						$tests_count = 1;

						if ($dataProvider)
						{
							$obj = $rc->newInstance();
							$data = $obj->$dataProvider();
							$tests_count = count($data);
						}

						$this->test_plans[$group]->addMethod($method, [
							'testsCount' => $tests_count
						]);
					}
				}
			}
		}

		return $this->test_plans;
	}

	public function setFilter($string)
	{
		$this->filter = $string;
		return $this;
	}

	public function setDataProviderFilter(array $arr)
	{
		foreach ($arr as $a)
		{
			list($method, $filters) = explode(':', $a);

			if (!isset($this->dp_filters[$method]))
				$this->dp_filters[$method] = [];

			$this->dp_filters[$method][] = explode(',', $filters);
		}
	}
}