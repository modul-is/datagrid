<?php

declare(strict_types = 1);

namespace ModulIS\Datagrid\Column;

use ModulIS\Datagrid\Datagrid;
use ModulIS\Datagrid\Filter;
use ModulIS\Datagrid\RowValue;
use Nette\Database\Table\ActiveRow;
use Nette\HtmlStringable;
use Nette\Utils\Html;

/**
 * Text column - base of all columns
 *
 * The value is taken from the row by the column path (see RowValue) and shown as it is. setReplacement()
 * translates it by a list, setRenderer() / setTemplate() render the cell completely.
 */
class Column
{
	public const string AlignStart = 'start';

	public const string AlignCenter = 'center';

	public const string AlignEnd = 'end';

	protected ?string $align = null;

	protected int|string|null $width = null;

	protected ?string $class = null;

	/** @var array<string, mixed> */
	protected array $attributes = [];

	/** @var (\Closure(ActiveRow): (string|HtmlStringable|int|float|null))|null */
	protected ?\Closure $renderer = null;

	protected ?string $template = null;

	/** @var array<string, mixed> */
	protected array $templateParameters = [];

	/** @var array<int|string, string|HtmlStringable>|null */
	protected ?array $replacement = null;

	/** @var bool|string|\Closure(\Nette\Database\Table\Selection, string): void */
	protected bool|string|\Closure $sortable = false;

	protected ?Editable $editable = null;


	public function __construct
	(
		protected readonly Datagrid $grid,
		protected readonly string $key,
		protected readonly string $label,
		protected readonly string $column
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
	 * Path of the value in the row - also the default database column for sorting and filtering
	 */
	public function getColumn(): string
	{
		return $this->column;
	}


	/**
	 * start, center or end - default is the align of the grid (Datagrid::setDefaultAlign())
	 */
	public function setAlign(string $align): static
	{
		if(!in_array($align, [self::AlignStart, self::AlignCenter, self::AlignEnd], true))
		{
			throw new \Nette\InvalidArgumentException("Align has to be start, center or end, '$align' given.");
		}

		$this->align = $align;

		return $this;
	}


	public function getAlign(): string
	{
		return $this->align ?? $this->grid->getDefaultAlign();
	}


	/**
	 * Width of the column - percent as a number (setWidth(15)), or any CSS width as a string (setWidth('8rem'))
	 */
	public function setWidth(int|string $width): static
	{
		$this->width = $width;

		return $this;
	}


	public function getWidth(): ?string
	{
		return is_int($this->width) ? $this->width . '%' : $this->width;
	}


	/**
	 * Class of the cells (header and body)
	 */
	public function setClass(?string $class): static
	{
		$this->class = $class;

		return $this;
	}


	public function getClass(): ?string
	{
		return $this->class;
	}


	/**
	 * HTML attributes of the body cells
	 *
	 * @param array<string, mixed> $attributes
	 */
	public function setAttributes(array $attributes): static
	{
		$this->attributes = $attributes;

		return $this;
	}


	/**
	 * @return array<string, mixed>
	 */
	public function getAttributes(): array
	{
		return $this->attributes;
	}


	/**
	 * Content of the cell - string (escaped), Nette\Utils\Html or a number
	 *
	 * @param \Closure(ActiveRow): (string|HtmlStringable|int|float|null) $renderer
	 */
	public function setRenderer(\Closure $renderer): static
	{
		$this->renderer = $renderer;

		return $this;
	}


	/**
	 * Latte template of the cell - gets $row, $column and the parameters
	 *
	 * @param array<string, mixed> $parameters
	 */
	public function setTemplate(string $template, array $parameters = []): static
	{
		$this->template = $template;
		$this->templateParameters = $parameters;

		return $this;
	}


	public function getTemplate(): ?string
	{
		return $this->template;
	}


	/**
	 * @return array<string, mixed>
	 */
	public function getTemplateParameters(): array
	{
		return $this->templateParameters;
	}


	/**
	 * Values shown instead of the value of the row - e.g. a dial list, values missing in the list are shown as they are
	 *
	 * @param array<int|string, string|HtmlStringable> $replacement
	 */
	public function setReplacement(array $replacement): static
	{
		$this->replacement = $replacement;

		return $this;
	}


	/**
	 * true - sorted by the column, string - by another database column / expression, Closure - custom sorting
	 *
	 * @param bool|string|\Closure(\Nette\Database\Table\Selection $selection, string $direction): void $sortable
	 */
	public function setSortable(bool|string|\Closure $sortable = true): static
	{
		$this->sortable = $sortable;

		return $this;
	}


	public function isSortable(): bool
	{
		return $this->sortable !== false;
	}


	/**
	 * @return string|\Closure(\Nette\Database\Table\Selection, string): void
	 */
	public function getSortColumn(): string|\Closure
	{
		return is_string($this->sortable) || $this->sortable instanceof \Closure ? $this->sortable : $this->column;
	}


	/**
	 * Value edited in the cell - the callback saves it, the row is redrawn afterwards
	 *
	 * @param \Closure(int|string $id, string $value): void $callback
	 * @param string $type text, number, date, textarea or select
	 * @param array<int|string, string>|null $items items of select
	 */
	public function setEditable(\Closure $callback, string $type = 'text', ?array $items = null): Editable
	{
		return $this->editable = new Editable($callback, $type, $items);
	}


	public function getEditable(): ?Editable
	{
		return $this->editable;
	}


	/**
	 * Raw value of the row
	 */
	public function getValue(ActiveRow $row): mixed
	{
		return RowValue::get($row, $this->column);
	}


	/**
	 * Content of the cell (not used for columns with a template)
	 */
	public function render(ActiveRow $row): string|HtmlStringable|null
	{
		if($this->renderer)
		{
			$value = ($this->renderer)($row);

			return $value instanceof HtmlStringable || $value === null ? $value : (string) $value;
		}

		$value = $this->getValue($row);

		if($this->replacement !== null && (is_int($value) || is_string($value)) && array_key_exists($value, $this->replacement))
		{
			return $this->replacement[$value];
		}

		return $this->format($value);
	}


	/**
	 * Text of the cell for the CSV export - block elements and line breaks of an HTML value are separated by a space,
	 * otherwise e.g. a name and an identifier in two <div>s would be glued together
	 */
	public function renderText(ActiveRow $row): string
	{
		$value = $this->render($row);

		if(!$value instanceof HtmlStringable)
		{
			return (string) $value;
		}

		$html = (string) preg_replace('~<(br|/div|/p|/li|/tr|/td|/th|/h[1-6])\b[^>]*>~i', ' ', (string) $value);
		$text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);

		return trim((string) preg_replace('~\s+~u', ' ', $text));
	}


	protected function format(mixed $value): string|HtmlStringable|null
	{
		if($value === null)
		{
			return null;
		}

		if($value instanceof \DateTimeInterface)
		{
			return $value->format('j. n. Y H:i');
		}

		return is_scalar($value) || $value instanceof \Stringable ? (string) $value : null;
	}


	/**
	 * Icon of the icon set of modul-is/form
	 */
	protected function icon(string $icon): Html
	{
		return \ModulIS\Form\Renderer\Bootstrap6Renderer::getDefaultIconRenderer()->render($icon);
	}


	/**
	 * Text filter in the header of the column
	 *
	 * @param string|list<string>|null $columns database columns searched (default the column)
	 */
	public function setFilterText(string|array|null $columns = null): Filter\TextFilter
	{
		return $this->grid->addFilterText($this->key, $this->label, $columns ?? $this->column);
	}


	/**
	 * @param array<int|string, string> $items
	 */
	public function setFilterSelect(array $items, ?string $column = null): Filter\SelectFilter
	{
		return $this->grid->addFilterSelect($this->key, $this->label, $items, $column ?? $this->column);
	}


	/**
	 * @param array<int|string, string> $items
	 */
	public function setFilterMultiSelect(array $items, ?string $column = null): Filter\MultiSelectFilter
	{
		return $this->grid->addFilterMultiSelect($this->key, $this->label, $items, $column ?? $this->column);
	}


	public function setFilterDate(?string $column = null): Filter\DateFilter
	{
		return $this->grid->addFilterDate($this->key, $this->label, $column ?? $this->column);
	}


	public function setFilterDateRange(?string $column = null): Filter\DateRangeFilter
	{
		return $this->grid->addFilterDateRange($this->key, $this->label, $column ?? $this->column);
	}


	public function setFilterRange(?string $column = null): Filter\RangeFilter
	{
		return $this->grid->addFilterRange($this->key, $this->label, $column ?? $this->column);
	}
}
