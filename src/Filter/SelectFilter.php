<?php

declare(strict_types = 1);

namespace ModulIS\Datagrid\Filter;

use ModulIS\Datagrid\Datagrid;
use Nette\Database\Table\Selection;
use Nette\Forms\Container;

/**
 * One value of a list - the prompt "~ Vše ~" (no filter) is added by the grid, unless the items have the key ''
 */
class SelectFilter extends Filter
{
	private ?string $prompt = null;

	private bool $search = false;


	/**
	 * @param list<string> $columns
	 * @param array<int|string, string> $items
	 */
	public function __construct
	(
		string $key,
		string $label,
		array $columns,
		protected array $items
	)
	{
		parent::__construct($key, $label, $columns);
	}


	public function setPrompt(?string $prompt): static
	{
		$this->prompt = $prompt;

		return $this;
	}


	/**
	 * Combobox with search in the items (modul-is/form)
	 */
	public function setSearch(bool $search = true): static
	{
		$this->search = $search;

		return $this;
	}


	public function addControl(Container $container, Datagrid $grid): void
	{
		$items = $this->items;
		unset($items['']);

		$control = $container->addSelect($this->key, $this->label, $items);
		\assert($control instanceof \ModulIS\Form\Control\SelectBox);
		$control->setPrompt($this->items[''] ?? $this->prompt ?? $grid->getText('all'))
			->setHtmlAttribute('data-datagrid-autosubmit', 'change');

		if($this->search)
		{
			$control->setSearch();
		}
	}


	public function normalize(mixed $value): int|string|null
	{
		if(!is_scalar($value) || (string) $value === '')
		{
			return null;
		}

		foreach(array_keys($this->items) as $item)
		{
			if((string) $item === (string) $value)
			{
				return $item;
			}
		}

		return null;
	}


	protected function applyCondition(Selection $selection, mixed $value): void
	{
		$this->whereColumns($selection, '%s = ?', [$value]);
	}
}
