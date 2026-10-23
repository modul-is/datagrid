<?php

declare(strict_types = 1);

namespace ModulIS\Datagrid\Export;

use ModulIS\Datagrid\Action\Button;
use ModulIS\Datagrid\Datagrid;
use Nette\Database\Table\Selection;
use Nette\Utils\Html;

/**
 * Export button - the callback gets the sorted Selection (filtered when $filtered) and sends a response
 *
 *     $grid->addExport('XLSX', function(Selection $selection, Datagrid $grid) {
 *         $this->getPresenter()->sendResponse(...);
 *     }, filtered: true);
 */
class Export
{
	use Button;

	/**
	 * @param \Closure(Selection $selection, Datagrid $grid): void $callback
	 */
	public function __construct
	(
		protected readonly Datagrid $grid,
		protected readonly string $key,
		protected ?string $caption,
		protected readonly \Closure $callback,
		protected readonly bool $filtered
	)
	{
		$this->icon = 'download';
	}


	public function isFiltered(): bool
	{
		return $this->filtered;
	}


	public function export(Selection $selection): void
	{
		($this->callback)($selection, $this->grid);
	}


	public function render(): Html
	{
		return $this->createButton($this->grid->link('export!', ['export' => $this->key]), null)
			->setAttribute('data-naja', 'off');
	}
}
