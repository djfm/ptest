<?php

class SimpleTest implements \PrestaShop\Ptest\TestClass\Basic
{
	public function beforeAll()
	{
	}

	/**
	* @parallel
	* @group A
	*/
	public function testStuffz()
	{
		throw new \Exception("OOPS");
	}

	/**
	* @group B
	*/
	public function testOtherStuffz()
	{

	}

	/**
	 * @group B
	 */
	public function scopedTest()
	{

	}

	/**
	* @test
	* @parallel
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
	*/
	public function testWithDataProvider()
	{

	}

	/**
	 * @dataProvoder lotsOfData
	 */
	public function otherBigTest()
	{

	}
}