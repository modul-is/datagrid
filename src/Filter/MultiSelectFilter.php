<?php

declare(strict_types = 1);

namespace ModulIS\Datagrid\Filter;

use ModulIS\Datagrid\Datagrid;
use Nette\Database\Table\Selection;
use Nette\Forms\Container;

/**
 * Several values of a list - combobox of modul-is/form, condition "column IN (...)"
 */
class MultiSelectFilter extends Filter
{
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


	public function addControl(Container $container, Datagrid $grid): void
	{
		$control = $container->addMultiSelect($this->getControlName(), $this->label, $this->items);
		\assert($control instanceof \ModulIS\Form\Control\MultiSelectBox);
		$control->setSearch()
			->setHtmlAttribute('placeholder', $this->placeholder ?? $grid->getText('choose'))
			->setHtmlAttribute('data-datagrid-autosubmit', 'change');
	}


	/**
	 * @return list<int|string>|null
	 */
	public function normalize(mixed $value): ?array
	{
		if(!is_array($value))
		{
			return null;
		}

		$selected = [];

		foreach(array_keys($this->items) as $item)
		{
			if(in_array((string) $item, array_map(strval(...), array_filter($value, is_scalar(...))), true))
			{
				$selected[] = $item;
			}
		}

		return $selected ?: null;
	}


	protected function applyCondition(Selection $selection, mixed $value): void
	{
		$this->whereColumns($selection, '%s IN ?', [$value]);
	}
}
