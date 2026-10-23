<?php

declare(strict_types = 1);

namespace ModulIS\Datagrid\Column;

use ModulIS\Datagrid\Datagrid;
use Nette\Database\Table\ActiveRow;
use Nette\HtmlStringable;
use Nette\Utils\Html;

/**
 * Value as a link - destination like Datagrid::addAction() (presenter action or signal of the parent)
 */
class LinkColumn extends Column
{
	private ?string $linkClass = null;

	private bool $newTab = false;


	/**
	 * @param array<string, string> $parameters link parameter => column of the row
	 */
	public function __construct
	(
		Datagrid $grid,
		string $key,
		string $label,
		string $column,
		private readonly string $destination,
		private readonly array $parameters
	)
	{
		parent::__construct($grid, $key, $label, $column);
	}


	public function setLinkClass(?string $class): static
	{
		$this->linkClass = $class;

		return $this;
	}


	public function setNewTab(bool $newTab = true): static
	{
		$this->newTab = $newTab;

		return $this;
	}


	public function render(ActiveRow $row): string|HtmlStringable|null
	{
		$content = parent::render($row);

		if($content === null || $content === '')
		{
			return $content;
		}

		return Html::el('a')
			->href($this->grid->createLink($this->destination, $this->parameters, $row))
			->class($this->linkClass)
			->target($this->newTab ? '_blank' : null)
			->addHtml($content instanceof HtmlStringable ? $content : Html::el()->setText($content));
	}
}
