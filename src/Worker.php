<?php

namespace PrestaShop\Ptest;

class Worker
{
	private $infile;
	private $outfile;

	public function __construct($infile, $outfile)
	{
		$this->infile = $infile;
		$this->outfile = $outfile;
	}

	public function work()
	{
		echo "Hoi!!\n";
	}
}