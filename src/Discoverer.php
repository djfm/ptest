<?php

namespace PrestaShop\Ptest;

class Discoverer
{
	private $known_classes = [];
	private $test_plans = [];
	private $class_filter = null;
	private $method_filter = null;
	private $data_provider_filter = null;

	public function __construct($target, $bootstrap = null, $filter = null, $dataProviderFilter = null)
	{
		if (is_string($bootstrap) && $bootstrap)
			require $bootstrap;

		$this->data_provider_filter = $dataProviderFilter;

		if ($filter)
		{
			$tmp = explode('::', $filter);
			if (count($tmp) === 2)
			{
				$this->class_filter = $tmp[0];
				$this->method_filter = $tmp[1];
			}
			elseif (count($tmp) === 1)
			{
				$this->class_filter = $tmp[0];
			}
			else
			{
				throw new \Exeption(sprintf('Invalid filter `%s`', $filter));
			}
		}

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

			if (!preg_match('/Test$/', $class))
				continue;

			if ($this->class_filter)
			{
				if (!preg_match('#'.$this->class_filter.'#', $class))
					continue;
			}

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

			$loader->setFilter($this->method_filter);
			$loader->setDataProviderFilter($this->data_provider_filter);
			
			$this->loadTestsFromClass($rc, $loader);
		}

		$this->known_classes = get_declared_classes();
	}

	public function loadTestsFromClass(\ReflectionClass $rc, \PrestaShop\Ptest\Loader\LoaderInterface $loader)
	{
		// Make sure we merge numerical arrays, else new test plans will overwrite previously
		// discovered ones.
		$this->test_plans = array_merge($this->test_plans, array_values($loader->loadTests($rc)));
	}

	public function getTestPlans()
	{
		return $this->test_plans;
	}
}