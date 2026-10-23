<?php

declare(strict_types = 1);

namespace ModulIS\Datagrid\Action;

use Nette\Database\Table\ActiveRow;
use Nette\Utils\Html;

/**
 * Action handled by the grid itself - callbacks get the primary key of the row
 *
 *     $grid->addCallbackAction('approve')->setIcon('check')->setAjax()
 *         ->onClick[] = fn(int $id) => $this->approve($id);
 */
class CallbackAction extends Action
{
	/** @var array<\Closure(int|string $id): void> */
	public array $onClick = [];


	public function render(ActiveRow $row): Html
	{
		return $this->createButton($this->grid->link('action!', ['action' => $this->key, 'id' => $row->getPrimary()]), $row);
	}


	public function click(int|string $id): void
	{
		foreach($this->onClick as $callback)
		{
			$callback($id);
		}
	}
}
