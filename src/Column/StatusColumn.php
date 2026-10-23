<?php

declare(strict_types = 1);

namespace ModulIS\Datagrid\Column;

use ModulIS\Datagrid\Datagrid;
use Nette\Database\Table\ActiveRow;

/**
 * Status changed right in the grid - button with the current option, menu with the others
 *
 *     $grid->addStatus('status', 'Stav', UserStatusDial::getList())
 *         ->setColors(['A' => 'success', 'B' => 'danger'])
 *         ->onChange[] = fn(int $id, string $value) => ...;
 *
 * The row is redrawn after the change (AJAX), or the page is reloaded.
 */
class StatusColumn extends Column
{
	/** @var array<\Closure(int|string $id, string $value): void> */
	public array $onChange = [];

	/** @var array<int|string, string> */
	private array $colors = [];

	private ?string $confirm = null;

	/** @var (\Closure(ActiveRow): bool)|null */
	private ?\Closure $condition = null;


	/**
	 * @param array<int|string, string> $options value => text
	 */
	public function __construct
	(
		Datagrid $grid,
		string $key,
		string $label,
		string $column,
		private array $options
	)
	{
		parent::__construct($grid, $key, $label, $column);
	}


	/**
	 * Theme colors of the options - value => primary, success, danger... (default secondary)
	 *
	 * @param array<int|string, string> $colors
	 */
	public function setColors(array $colors): static
	{
		$this->colors = $colors;

		return $this;
	}


	/**
	 * Question confirmed before the change - %s is replaced by the text of the new option
	 */
	public function setConfirm(?string $confirm): static
	{
		$this->confirm = $confirm;

		return $this;
	}


	/**
	 * The status can be changed only in rows the condition returns true for, other rows show it as a badge
	 *
	 * @param \Closure(ActiveRow): bool $condition
	 */
	public function setCondition(\Closure $condition): static
	{
		$this->condition = $condition;

		return $this;
	}


	public function isChangeable(ActiveRow $row): bool
	{
		return $this->onChange && ($this->condition === null || ($this->condition)($row));
	}


	/**
	 * @return array<int|string, string>
	 */
	public function getOptions(): array
	{
		return $this->options;
	}


	/**
	 * Option of the row - values from the database are compared as strings (1 and '1' are the same option)
	 */
	public function getCurrentOption(ActiveRow $row): int|string|null
	{
		$value = $this->getValue($row);

		foreach(array_keys($this->options) as $option)
		{
			if((string) $option === (string) $value)
			{
				return $option;
			}
		}

		return null;
	}


	public function getColor(int|string|null $option): string
	{
		return $option === null ? 'secondary' : ($this->colors[$option] ?? 'secondary');
	}


	public function getConfirm(int|string $option): ?string
	{
		return $this->confirm === null ? null : sprintf($this->confirm, $this->options[$option]);
	}


	public function hasOption(string $value): bool
	{
		foreach(array_keys($this->options) as $option)
		{
			if((string) $option === $value)
			{
				return true;
			}
		}

		return false;
	}


	public function change(int|string $id, string $value): void
	{
		foreach($this->onChange as $callback)
		{
			$callback($id, $value);
		}
	}


	public function renderText(ActiveRow $row): string
	{
		$option = $this->getCurrentOption($row);

		return $option === null ? (string) $this->getValue($row) : $this->options[$option];
	}
}
