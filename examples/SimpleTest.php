<?php

class SimpleTest implements \PrestaShop\Ptest\TestClass\Basic
{
	public function beforeAll()
	{
	}

	/**
	* @parallel
	*/
	public function testStuffz()
	{
		throw new \Exception("OOPS");
	}

	/**
	* @parallel
	*/
	public function testOtherStuffz()
	{

	}

	public function beforeTestHello()
	{
		throw new \Exception('Grmbl!');
	}
	/**
	* @parallel group 1
	*/
	public function testHello()
	{

	}

	/**
	* @test
	* @parallel group 1
	*/
	public function forcedTest()
	{

	}

	public function testFoo()
	{
	}

	public function lotsOfData()
	{
		return [[0], [1], [2], [3], [4]];
	}

	/**
	* @dataProvider lotsOfData
	* @parallelize 4
	*/
	public function testWithDataProvider()
	{

	}
}