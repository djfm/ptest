<?php

namespace PrestaShop\Ptest\Loader;

use \PrestaShop\Ptest\Util\DocCommentParser;
use \PrestaShop\Ptest\TestPlan;

class Basic implements LoaderInterface
{
	protected $test_plans = [];

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
					$group = $dcp->getOption('parallel', $method->getName());

					if ($dcp->getOption('dataProvider') && $dcp->getOption('parallelize', 1) > 1)
					{
						$n = $dcp->getOption('parallelize', 1);
						for ($i = 0; $i < $n; $i++)
						{
							$pgroup = "$group (parallel batch $i)";
							if (!isset($this->test_plans[$pgroup]))
							{
								$this->test_plans[$pgroup] = new TestPlan($rc, $pgroup);
							}
							$this->test_plans[$pgroup]->addMethod($method, [
								'dataProviderBatch' => $i,
								'dataProviderBatchCount' => $n
							]);
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
}