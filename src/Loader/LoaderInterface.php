<?php

namespace PrestaShop\Ptest\Loader;

interface LoaderInterface
{
	public function loadTests(\ReflectionClass $rc);
}