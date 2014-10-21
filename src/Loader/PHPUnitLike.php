<?php

namespace PrestaShop\Ptest\Loader;

use PrestaShop\Ptest\Testable\SingleTest;
use PrestaShop\Ptest\Testable\Group;

use PrestaShop\Ptest\Helper\DocCommentParser;

class PHPUnitLike
{
	public function load(\SplFileInfo $file)
	{
		if ($file->getExtension() !== 'php') {
			return;
		}

		$className = $file->getBasename('.php');
		$known = get_declared_classes();
		require $file->getPathname();
		$new = array_diff(get_declared_classes(), $known);

		foreach ($new as $class) {
			if (preg_match('/'.preg_quote($className).'$/', $class)) {
				return $this->loadTestablesFromClass($file->getPathname(), $class);
			}
		}

		return false;
	}

	public function loadTestablesFromClass($path, $className)
	{
		$refl = new \ReflectionClass($className);

		$defaultGroupName = $className;

		$root = new Group(null, Group::PARALLEL);
		$root->setWrappers(array($className, 'setupBeforeClass', null, true), array($className, 'tearDownAfterClass', null, true));
		
		$groups = [];

		foreach ($refl->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
			if (preg_match('/^test/', $method->getName())) {

				$test = new SingleTest($method->getName());
				$test
				->setFileName($path)
				->setClassName($className)
				->setMethodName($method->getName());

				$dcp = new DocCommentParser($method->getDocComment());
				$groupName = $dcp->getOption('group', $defaultGroupName);

				$args = [[]];

				if (($dataProvider = $dcp->getOption('dataProvider'))) {
					$args = array_map(function($args) {
						return array_map(function($arg) {
							return ['value' => $arg];
						}, $args);
					}, (new $className())->$dataProvider());

					if ($dcp->hasOption('parallelize')) {
						$test->setParallelizable(true);
					}
				}

				if (($depends = $dcp->getOption('depends'))) {
					$argsFromDepends = array_map(function($a) {
						return ['reference' => $a];
					}, preg_split('/\s+/', $depends));

					$args = array_map(function ($args) use ($argsFromDepends) {
						return array_merge($args, $argsFromDepends);
					}, $args);
				}

				$test->setExamples($args);

				$test->setWrappers(
					array($className, 'setUp', null, false),
					array($className, 'tearDown', null, false)
				);


				if (!isset($groups[$groupName])) {
					$group = new Group($groupName, Group::SEQUENCE);
					$root->addChild($group);
					$groups[$groupName] = $group;
				}

				$groups[$groupName]->addChild($test);
				
			}
		}

		return $root;
	}
}