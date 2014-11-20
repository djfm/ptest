<?php

namespace PrestaShop\Ptest\Helper;

class FileSystem
{
	public static function cpr($source, $dest, $perms = 0777)
	{
		mkdir($dest, $perms, true);
		$rdi = new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS);
		$iterator = new \RecursiveIteratorIterator($rdi, \RecursiveIteratorIterator::SELF_FIRST);
				
		foreach ($iterator as $item) {
			if ($item->isDir()) {
				mkdir($dest.DIRECTORY_SEPARATOR.$iterator->getSubPathName(), $perms);
			} else {
				copy($item, $dest.DIRECTORY_SEPARATOR.$iterator->getSubPathName());
			}
		}
	}
}