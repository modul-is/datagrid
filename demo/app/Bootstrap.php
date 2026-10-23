<?php

declare(strict_types = 1);

namespace ModulIS\Datagrid\Demo;

use Nette\Bootstrap\Configurator;

/**
 * Minimal Nette application for developing the library - one page with grids over an SQLite database
 */
class Bootstrap
{
	public static function boot(): Configurator
	{
		$rootDir = dirname(__DIR__);

		$configurator = new Configurator;
		$configurator->setDebugMode(true);
		$configurator->enableTracy($rootDir . '/log');
		$configurator->setTempDirectory($rootDir . '/temp');
		$configurator->addStaticParameters(['rootDir' => $rootDir]);
		$configurator->addConfig($rootDir . '/config/common.neon');

		return $configurator;
	}
}
