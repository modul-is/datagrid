<?php

declare(strict_types = 1);

namespace ModulIS\Datagrid\DI;

use ModulIS\Datagrid\Datagrid;
use Nette\DI\CompilerExtension;
use Nette\Schema\Expect;
use Nette\Schema\Schema;

/**
 * Defaults of all grids of the application
 *
 *     extensions:
 *         modulisDatagrid: ModulIS\Datagrid\DI\DatagridExtension
 *
 *     modulisDatagrid:
 *         align: center               # default align of the columns (start, center, end)
 *         perPageList: [10, 25, 50]
 *         rememberState: false        # state of grids in the session
 *         texts:                      # texts of the grid, see Datagrid::$defaults
 *             noData: Nic nenalezeno
 *
 * Icons are taken from modul-is/form (modulisForm: icons: fontawesome).
 *
 * @property-read \stdClass $config
 */
final class DatagridExtension extends CompilerExtension
{
	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'align' => Expect::anyOf('start', 'center', 'end')->nullable(),
			'perPageList' => Expect::listOf('int')->nullable(),
			'rememberState' => Expect::bool()->nullable(),
			'texts' => Expect::arrayOf('string', 'string')
		]);
	}


	public function loadConfiguration(): void
	{
		$defaults = array_filter([
			'align' => $this->config->align,
			'perPageList' => $this->config->perPageList,
			'rememberState' => $this->config->rememberState,
			'texts' => $this->config->texts
		], fn(mixed $value): bool => $value !== null && $value !== []);

		if($defaults)
		{
			$this->initialization->addBody('?::setDefaults(?);', [
				new \Nette\PhpGenerator\Literal('\\' . Datagrid::class),
				$defaults
			]);
		}
	}
}
