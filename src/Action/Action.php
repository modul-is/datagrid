<?php

declare(strict_types = 1);

namespace ModulIS\Datagrid\Action;

use ModulIS\Datagrid\Datagrid;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\Html;

/**
 * Button in the column of actions of every row
 *
 *     $grid->addAction(':User:User:edit')->setIcon('edit')->setTitle('Editovat')->setColor('info');
 *     $grid->addAction('delete!')->setIcon('trash')->setColor('danger')->setConfirm('Smazat %s?', 'name')
 *         ->setVisible(fn(ActiveRow $row) => !$row->locked);
 *
 * Destination with ":" is a link of the presenter, a signal ("delete!") belongs to the parent of the grid
 * (the component that created it), any other is an action of the current presenter.
 */
class Action
{
	use Button;

	/** @var (\Closure(ActiveRow): bool)|null */
	private ?\Closure $visible = null;

	/** @var (\Closure(ActiveRow): string)|null */
	private ?\Closure $href = null;


	/**
	 * @param array<string, string> $parameters link parameter => column of the row
	 */
	public function __construct
	(
		protected readonly Datagrid $grid,
		protected readonly string $key,
		protected readonly string $destination,
		protected array $parameters,
		protected ?string $caption = null
	)
	{
	}


	public function getKey(): string
	{
		return $this->key;
	}


	/**
	 * Action is shown only in rows the callback returns true for
	 *
	 * @param \Closure(ActiveRow): bool $visible
	 */
	public function setVisible(\Closure $visible): static
	{
		$this->visible = $visible;

		return $this;
	}


	public function isVisible(ActiveRow $row): bool
	{
		return $this->visible === null || ($this->visible)($row);
	}


	/**
	 * Additional link parameters - parameter => column of the row
	 *
	 * @param array<string, string> $parameters
	 */
	public function addParameters(array $parameters): static
	{
		$this->parameters = $parameters + $this->parameters;

		return $this;
	}


	/**
	 * URL computed from the row instead of the destination
	 *
	 * @param \Closure(ActiveRow): string $href
	 */
	public function setHref(\Closure $href): static
	{
		$this->href = $href;

		return $this;
	}


	public function render(ActiveRow $row): Html
	{
		$href = $this->href
			? ($this->href)($row)
			: $this->grid->createLink($this->destination, $this->parameters, $row);

		return $this->createButton($href, $row);
	}
}
