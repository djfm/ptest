<?php

class SimpleTest implements \PrestaShop\Ptest\TestClass\Basic
{
	/**
	* @parallel
	*/
	public function testStuffz()
	{

	}

	/**
	* @parallel
	*/
	public function testOtherStuffz()
	{

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