<?php

namespace PrestaShop\Ptest;

class Discoverer
{
	private $known_classes = [];
	private $test_plans = [];

	public function __construct($target)
	{
		$this->known_classes = get_declared_classes();
		$this->discoverTestClassesOnFileSystem($target);
	}

	public function discoverTestClassesOnFileSystem($target)
	{
		if (is_dir($target))
		{
			$rdi = new \RecursiveDirectoryIterator($target, \FilesystemIterator::SKIP_DOTS);
			$rii = new \RecursiveIteratorIterator($rdi, \RecursiveIteratorIterator::CHILD_FIRST);
		}
		else
		{
			$rii = [$target => new \SplFileInfo($target)];
		}

		foreach($rii as $path => $file)
		{
		    if (
		    	$file->isFile() &&
		    	$file->getExtension() === 'php' &&
		    	preg_match('/Test$/', $file->getBaseName('.php'))
		    )
		    {
		    	require $path;
		    }
		}
		$this->discoverLoadedClasess($path);
	}

	public function discoverLoadedClasess()
	{
		$loaded_classes = array_diff(get_declared_classes(), $this->known_classes);
		
		foreach ($loaded_classes as $class)
		{
			$rc = new \ReflectionClass($class);

			$loaders = [];

			foreach ($rc->getInterfaceNames() as $iface)
			{
				$loader_class = str_replace('\\TestClass\\', '\\Loader\\', $iface);
				if (class_exists($loader_class))
					$loaders[] = $loader_class;
			}

			if (count($loaders) === 0)
				throw new \Exception(sprintf('No loader found for class %s.', $class));

			if (count($loaders) > 1)
				throw new \Exception(sprintf('Ambiguous loader for class %s. TestClasses must implement exactly one interface from PrestaShop\\Ptest\\TestClass.', $class));
		
			$loader = new $loaders[0];
			$this->loadTestsFromClass($rc, $loader);
		}

		$this->known_classes = get_declared_classes();
	}

	public function loadTestsFromClass(\ReflectionClass $rc, \PrestaShop\Ptest\Loader\LoaderInterface $loader)
	{
		$this->test_plans = array_merge($this->test_plans, $loader->loadTests($rc));
	}

	public function getTestPlans()
	{
		return $this->test_plans;
	}
}