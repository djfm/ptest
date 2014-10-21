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

	public function sendMessage($obj)
	{
		file_put_contents(
			$this->outfile,
			json_encode($obj)."\n",
			FILE_APPEND | LOCK_EX
		);
	}

	public function log($message) {
		$this->sendMessage([
			'type' => 'log',
			'message' => $message
		]);
	}

	public function work()
	{
		$this->stack = json_decode(file_get_contents($this->infile), true);

		$references = [];

		foreach ($this->stack as $step) {
			
			$stepName = $step['call'][0].'::'.$step['call'][1];
			
			$args = [];

			if (is_array($step['call'][2])) {
				foreach ($step['call'][2] as $arg) {
					if (isset($arg['value'])) {
						$args[] = $arg['value'];
					} elseif (isset($arg['reference'])) {
						$args[] = $references[$arg['reference']];
					}
				}
			}

			if (!empty($args)) {
				$this->log(sprintf("Running $stepName with arguments: %s", json_encode($args)));
			} else {
				$this->log("Running $stepName...");
			}
			
			//print_r($step);
		}
	}
}