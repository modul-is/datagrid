<?php

declare(strict_types = 1);

namespace ModulIS\Datagrid\Filter;

use ModulIS\Datagrid\Datagrid;
use Nette\Database\Table\Selection;
use Nette\Forms\Container;

/**
 * Filter of the grid - a control of the filter form and a condition of the Selection
 *
 * The value lives in the persistent parameter "filter" of the grid (URL), normalize() validates it -
 * invalid values from the URL are ignored instead of failing.
 */
abstract class Filter
{
	/** @var (\Closure(Selection $selection, mixed $value): void)|null */
	protected ?\Closure $condition = null;

	protected ?string $placeholder = null;


	/**
	 * @param list<string> $columns database columns the filter applies to
	 */
	public function __construct
	(
		protected readonly string $key,
		protected readonly string $label,
		protected readonly array $columns
	)
	{
	}


	public function getKey(): string
	{
		return $this->key;
	}


	public function getLabel(): string
	{
		return $this->label;
	}


	/**
	 * Own condition instead of the default one - gets the Selection and the normalized value
	 *
	 * @param \Closure(Selection $selection, mixed $value): void $condition
	 */
	public function setCondition(\Closure $condition): static
	{
		$this->condition = $condition;

		return $this;
	}


	public function setPlaceholder(?string $placeholder): static
	{
		$this->placeholder = $placeholder;

		return $this;
	}


	public function apply(Selection $selection, mixed $value): void
	{
		if($this->condition)
		{
			($this->condition)($selection, $value);

			return;
		}

		$this->applyCondition($selection, $value);
	}


	/**
	 * Adds the control (or a container) named by the key of the filter
	 */
	abstract public function addControl(Container $container, Datagrid $grid): void;


	/**
	 * Valid value of the filter or null when the filter is not active
	 */
	abstract public function normalize(mixed $value): mixed;


	abstract protected function applyCondition(Selection $selection, mixed $value): void;


	/**
	 * Value of the control in the filter form
	 */
	public function getFormValue(mixed $value): mixed
	{
		return $value;
	}


	/**
	 * Condition "(col1 ... OR col2 ...)" for every column of the filter
	 *
	 * @param list<mixed> $parameters parameters of one column, repeated for every column
	 */
	protected function whereColumns(Selection $selection, string $expression, array $parameters): void
	{
		$conditions = [];
		$values = [];

		foreach($this->columns as $column)
		{
			$conditions[] = sprintf($expression, $column);
			array_push($values, ...$parameters);
		}

		$selection->where('(' . implode(' OR ', $conditions) . ')', ...$values);
	}
}
