<?php

namespace PrestaShop\Ptest\Loader;

use \PrestaShop\Ptest\Util\DocCommentParser;
use \PrestaShop\Ptest\TestPlan;

class Basic implements LoaderInterface
{
	protected $test_plans = [];
	protected $filter = null;

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

					if ($dataProvider)
					{
						$obj = $rc->newInstance();
						$data = $obj->$dataProvider();
						
						if ($dcp->getOption('parallelize', 1) > 1)
						{
							$n = $dcp->getOption('parallelize', 1);

							$keys = [];

							$i = 0;
							foreach ($data as $key => $value)
							{
								$b = $i % $n;
								$pgroup = "$group (parallel batch $b)";
								
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
							$this->test_plans[$group]->addMethod($method, ['testsCount' => count($data)]);
						}
					}
					else
					{
						if (!isset($this->test_plans[$group]))
						{
							$this->test_plans[$group] = new TestPlan($rc, $group);
						}
						$this->test_plans[$group]->addMethod($method);
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
}