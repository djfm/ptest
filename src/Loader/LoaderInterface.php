<?php

namespace PrestaShop\Ptest\Loader;


interface LoaderInterface
{
	/**
	* Takes a reflection class, returns an array of \PrestaShop\Ptest\TestPlan
	*/
	public function loadTests(\ReflectionClass $rc);

	public function setFilter($string);
	public function setDataProviderFilter(array $array);
}