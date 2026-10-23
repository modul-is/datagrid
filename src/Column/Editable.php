<?php

declare(strict_types = 1);

namespace ModulIS\Datagrid\Column;

use Nette\Database\Table\ActiveRow;

/**
 * Value of a cell edited in place - a click on the cell shows an input, the value is sent by AJAX (assets/js)
 */
final class Editable
{
	private const array Types = ['text', 'number', 'date', 'textarea', 'select'];

	/** @var (\Closure(ActiveRow): mixed)|null */
	private ?\Closure $valueCallback = null;

	/** @var (\Closure(ActiveRow): bool)|null */
	private ?\Closure $condition = null;


	/**
	 * @param \Closure(int|string $id, string $value): void $callback
	 * @param array<int|string, string>|null $items
	 */
	public function __construct
	(
		private readonly \Closure $callback,
		private readonly string $type,
		private readonly ?array $items
	)
	{
		if(!in_array($type, self::Types, true))
		{
			throw new \Nette\InvalidArgumentException('Editable type has to be one of ' . implode(', ', self::Types) . ", '$type' given.");
		}

		if(($type === 'select') !== ($items !== null))
		{
			throw new \Nette\InvalidArgumentException('Items are required for the select type of an editable cell (and only for it).');
		}
	}


	/**
	 * Value put into the input - default is the value of the column
	 *
	 * @param \Closure(ActiveRow): mixed $callback
	 */
	public function setValue(\Closure $callback): self
	{
		$this->valueCallback = $callback;

		return $this;
	}


	/**
	 * The cell is editable only in rows the condition returns true for
	 *
	 * @param \Closure(ActiveRow): bool $condition
	 */
	public function setCondition(\Closure $condition): self
	{
		$this->condition = $condition;

		return $this;
	}


	public function isEditable(ActiveRow $row): bool
	{
		return $this->condition === null || ($this->condition)($row);
	}


	public function getType(): string
	{
		return $this->type;
	}


	/**
	 * @return array<int|string, string>|null
	 */
	public function getItems(): ?array
	{
		return $this->items;
	}


	public function getInputValue(ActiveRow $row, Column $column): string
	{
		$value = $this->valueCallback ? ($this->valueCallback)($row) : $column->getValue($row);

		if($value instanceof \DateTimeInterface)
		{
			return $value->format('Y-m-d');
		}

		return is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
	}


	public function save(int|string $id, string $value): void
	{
		($this->callback)($id, $value);
	}
}
