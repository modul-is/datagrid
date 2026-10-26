<?php

declare(strict_types = 1);

namespace ModulIS\Datagrid;

use ModulIS\Datagrid\Action\Action;
use ModulIS\Datagrid\Action\CallbackAction;
use ModulIS\Datagrid\Action\GroupAction;
use ModulIS\Datagrid\Action\ToolbarButton;
use ModulIS\Datagrid\Column\BoolColumn;
use ModulIS\Datagrid\Column\Column;
use ModulIS\Datagrid\Column\DateTimeColumn;
use ModulIS\Datagrid\Column\LinkColumn;
use ModulIS\Datagrid\Column\NumberColumn;
use ModulIS\Datagrid\Column\StatusColumn;
use ModulIS\Datagrid\Export\CsvExport;
use ModulIS\Datagrid\Export\Export;
use ModulIS\Datagrid\Filter\DateFilter;
use ModulIS\Datagrid\Filter\DateRangeFilter;
use ModulIS\Datagrid\Filter\Filter;
use ModulIS\Datagrid\Filter\MultiSelectFilter;
use ModulIS\Datagrid\Filter\RangeFilter;
use ModulIS\Datagrid\Filter\SelectFilter;
use ModulIS\Datagrid\Filter\TextFilter;
use ModulIS\Form\Form;
use Nette\Application\Attributes\Persistent;
use Nette\Application\UI\Component;
use Nette\Application\UI\Control;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Utils\Html;
use Nette\Utils\Paginator;

/**
 * Grid of rows of a Nette Database Selection - columns, filters, sorting, pagination, actions,
 * group actions, exports, detail of a row, editable cells and status columns. Rendered for Bootstrap 6,
 * client side in assets/js (naja).
 *
 * State (page, per page, sort, filter) lives in persistent parameters, so it is a part of every link.
 * setRememberState() keeps it in the session too.
 */
class Datagrid extends Control
{
	#[Persistent]
	public int $page = 1;

	#[Persistent]
	public ?int $perPage = null;

	/** @var array<string, string> column key => ASC|DESC */
	#[Persistent]
	public array $sort = [];

	/** @var array<string, mixed> filter key => value ('' clears a default filter) */
	#[Persistent]
	public array $filter = [];

	/** @var array<string, mixed> */
	private static array $defaults = [
		'align' => Column::AlignStart,
		'perPageList' => [10, 25, 50, 100],
		'rememberState' => false,
		'texts' => [
			'all' => '~ Vše ~',
			'search' => '',
			'choose' => 'Vyberte',
			'from' => 'Od',
			'to' => 'Do',
			'yes' => 'Ano',
			'no' => 'Ne',
			'noData' => 'Žádné položky',
			'items' => 'Položky %d–%d z %d',
			'resetFilter' => 'Zrušit filtr',
			'perPage' => 'Položek na stránku',
			'actions' => 'Akce',
			'selectAll' => 'Vybrat vše',
			'selectRow' => 'Vybrat řádek',
			'selected' => 'Vybráno',
			'detail' => 'Detail',
			'confirm' => 'Potvrzení',
			'confirmOk' => 'Ano',
			'confirmCancel' => 'Zrušit',
			'previous' => 'Předchozí',
			'next' => 'Další'
		]
	];

	private string $primaryKey = 'id';

	/** @var array<string, Column> */
	private array $columns = [];

	/** @var array<string, Filter> */
	private array $filters = [];

	/** @var array<string, Action> */
	private array $actions = [];

	/** @var list<ToolbarButton> */
	private array $toolbarButtons = [];

	/** @var array<string, GroupAction> */
	private array $groupActions = [];

	/** @var array<string, Export> */
	private array $exports = [];

	/** @var array<string, string> */
	private array $defaultSort = [];

	/** @var array<string, mixed> */
	private array $defaultFilter = [];

	/** @var list<int> */
	private array $perPageList;

	private ?int $defaultPerPage = null;

	private bool $pagination = true;

	private bool $rememberState;

	private string $defaultAlign;

	private bool $outerFilters = false;

	/** @var array<string, string> */
	private array $texts;

	/** @var (\Closure(ActiveRow $row, Html $tr): void)|null */
	private ?\Closure $rowCallback = null;

	/** @var (\Closure(ActiveRow): bool)|null */
	private ?\Closure $groupSelectable = null;

	private ?Detail $detail = null;

	private ?string $templateFile = null;

	/** @var list<string>|null primary keys of rows redrawn by redrawItem() */
	private ?array $redrawIds = null;

	/** @var list<string> */
	private array $openDetails = [];

	private ?int $itemCount = null;


	public function __construct
	(
		private ?Selection $source = null
	)
	{
		$this->defaultAlign = self::$defaults['align'];
		$this->perPageList = self::$defaults['perPageList'];
		$this->rememberState = self::$defaults['rememberState'];
		$this->texts = self::$defaults['texts'];
	}


	/**
	 * Defaults of all grids of the application (DatagridExtension sets them from the configuration)
	 *
	 * @param array{align?: string, perPageList?: list<int>, rememberState?: bool, texts?: array<string, string>} $defaults
	 */
	public static function setDefaults(array $defaults): void
	{
		$defaults['texts'] = ($defaults['texts'] ?? []) + self::$defaults['texts'];

		self::$defaults = $defaults + self::$defaults;
	}


	/********************************************************************************
	 * Configuration
	 ********************************************************************************/


	public function setSource(Selection $source): static
	{
		$this->source = $source;

		return $this;
	}


	/**
	 * Clone of the source - the grid never changes the Selection given to it
	 */
	public function getSource(): Selection
	{
		if($this->source === null)
		{
			throw new \Nette\InvalidStateException('Source of the grid is not set, use the constructor or setSource().');
		}

		return clone $this->source;
	}


	/**
	 * Column identifying the rows (default "id") - also a related column like "car.id"
	 */
	public function setPrimaryKey(string $primaryKey): static
	{
		$this->primaryKey = $primaryKey;

		return $this;
	}


	public function getPrimaryKey(): string
	{
		return $this->primaryKey;
	}


	/**
	 * Default align of the columns - start, center or end
	 */
	public function setDefaultAlign(string $align): static
	{
		$this->defaultAlign = $align;

		return $this;
	}


	public function getDefaultAlign(): string
	{
		return $this->defaultAlign;
	}


	/**
	 * Sorting used until the user sorts the grid - column key (or a database column) => ASC|DESC
	 *
	 * @param array<string, string> $sort
	 */
	public function setDefaultSort(array $sort): static
	{
		$this->defaultSort = array_map(strtoupper(...), $sort);

		return $this;
	}


	/**
	 * Filter values used until the user changes the filter - filter key => value
	 *
	 * @param array<string, mixed> $filter
	 */
	public function setDefaultFilter(array $filter): static
	{
		$this->defaultFilter = $filter;

		return $this;
	}


	/**
	 * @param list<int> $perPageList
	 */
	public function setPerPageList(array $perPageList, ?int $default = null): static
	{
		$this->perPageList = $perPageList;
		$this->defaultPerPage = $default;

		return $this;
	}


	public function setPagination(bool $pagination = true): static
	{
		$this->pagination = $pagination;

		return $this;
	}


	/**
	 * Keep page, sorting, filter and items per page in the session - restored when the URL has no state of the grid
	 */
	public function setRememberState(bool $rememberState = true): static
	{
		$this->rememberState = $rememberState;

		return $this;
	}


	/**
	 * All filters above the table instead of the header of the columns
	 */
	public function setOuterFilters(bool $outerFilters = true): static
	{
		$this->outerFilters = $outerFilters;

		return $this;
	}


	/**
	 * Changes the <tr> of a row - e.g. a class by the state of the row
	 *
	 * @param \Closure(ActiveRow $row, Html $tr): void $callback
	 */
	public function setRowCallback(\Closure $callback): static
	{
		$this->rowCallback = $callback;

		return $this;
	}


	/**
	 * Own template - {extends $originalTemplate} and override its blocks
	 */
	public function setTemplateFile(?string $templateFile): static
	{
		$this->templateFile = $templateFile;

		return $this;
	}


	/**
	 * @param array<string, string> $texts key => text, see Datagrid::$defaults
	 */
	public function setTexts(array $texts): static
	{
		$this->texts = $texts + $this->texts;

		return $this;
	}


	public function getText(string $key): string
	{
		return $this->texts[$key] ?? $key;
	}


	/********************************************************************************
	 * Columns
	 ********************************************************************************/


	/**
	 * @param string|null $column path of the value in the row - default the key, "table.column" for a referenced row
	 */
	public function addText(string $key, string $label, ?string $column = null): Column
	{
		return $this->addColumn(new Column($this, $key, $label, $column ?? $key));
	}


	public function addNumber(string $key, string $label, ?string $column = null): NumberColumn
	{
		return $this->addColumn(new NumberColumn($this, $key, $label, $column ?? $key));
	}


	public function addDateTime(string $key, string $label, ?string $column = null): DateTimeColumn
	{
		return $this->addColumn(new DateTimeColumn($this, $key, $label, $column ?? $key));
	}


	public function addBool(string $key, string $label, ?string $column = null): BoolColumn
	{
		return $this->addColumn(new BoolColumn($this, $key, $label, $column ?? $key));
	}


	/**
	 * Value translated by a dial list (setReplacement())
	 *
	 * @param array<int|string, string> $list
	 */
	public function addDial(string $key, string $label, array $list, ?string $column = null): Column
	{
		return $this->addColumn(new Column($this, $key, $label, $column ?? $key))
			->setReplacement($list);
	}


	/**
	 * @param array<string, string>|null $parameters link parameter => column of the row (default id => primary key)
	 */
	public function addLink(string $key, string $label, string $destination, ?array $parameters = null, ?string $column = null): LinkColumn
	{
		return $this->addColumn(new LinkColumn($this, $key, $label, $column ?? $key, $destination, $parameters ?? ['id' => $this->primaryKey]));
	}


	/**
	 * @param array<int|string, string> $options value => text
	 */
	public function addStatus(string $key, string $label, array $options, ?string $column = null): StatusColumn
	{
		return $this->addColumn(new StatusColumn($this, $key, $label, $column ?? $key, $options));
	}


	/**
	 * @template T of Column
	 * @param T $column
	 * @return T
	 */
	public function addColumn(Column $column): Column
	{
		if(isset($this->columns[$column->getKey()]))
		{
			throw new \Nette\InvalidArgumentException("Column '{$column->getKey()}' already exists.");
		}

		return $this->columns[$column->getKey()] = $column;
	}


	/**
	 * @return array<string, Column>
	 */
	public function getColumns(): array
	{
		return $this->columns;
	}


	public function getColumn(string $key): Column
	{
		return $this->columns[$key] ?? throw new \Nette\InvalidArgumentException("Column '$key' does not exist.");
	}


	/********************************************************************************
	 * Filters - a filter with the key of a column is shown in the header of the column, others above the table
	 ********************************************************************************/


	/**
	 * @param string|list<string>|null $columns searched database columns (default the key)
	 */
	public function addFilterText(string $key, string $label, string|array|null $columns = null): TextFilter
	{
		return $this->addFilter(new TextFilter($key, $label, (array) ($columns ?? $key)));
	}


	/**
	 * @param array<int|string, string> $items
	 */
	public function addFilterSelect(string $key, string $label, array $items, ?string $column = null): SelectFilter
	{
		return $this->addFilter(new SelectFilter($key, $label, [$column ?? $key], $items));
	}


	/**
	 * @param array<int|string, string> $items
	 */
	public function addFilterMultiSelect(string $key, string $label, array $items, ?string $column = null): MultiSelectFilter
	{
		return $this->addFilter(new MultiSelectFilter($key, $label, [$column ?? $key], $items));
	}


	public function addFilterDate(string $key, string $label, ?string $column = null): DateFilter
	{
		return $this->addFilter(new DateFilter($key, $label, [$column ?? $key]));
	}


	public function addFilterDateRange(string $key, string $label, ?string $column = null): DateRangeFilter
	{
		return $this->addFilter(new DateRangeFilter($key, $label, [$column ?? $key]));
	}


	public function addFilterRange(string $key, string $label, ?string $column = null): RangeFilter
	{
		return $this->addFilter(new RangeFilter($key, $label, [$column ?? $key]));
	}


	/**
	 * @template T of Filter
	 * @param T $filter
	 * @return T
	 */
	public function addFilter(Filter $filter): Filter
	{
		if(isset($this->filters[$filter->getKey()]))
		{
			throw new \Nette\InvalidArgumentException("Filter '{$filter->getKey()}' already exists.");
		}

		foreach($this->filters as $existing)
		{
			if($existing->getControlName() === $filter->getControlName())
			{
				throw new \Nette\InvalidArgumentException("Filter '{$filter->getKey()}' has the same control name '{$filter->getControlName()}' as filter '{$existing->getKey()}'.");
			}
		}

		return $this->filters[$filter->getKey()] = $filter;
	}


	/**
	 * @return array<string, Filter>
	 */
	public function getFilters(): array
	{
		return $this->filters;
	}


	/**
	 * Control of the filter in the filter form - for own rendering of the filters
	 */
	public function getFilterControl(string $key): \Nette\Forms\Controls\BaseControl|\Nette\Forms\Container
	{
		$filter = $this->filters[$key] ?? throw new \Nette\InvalidArgumentException("Filter '$key' does not exist.");

		$container = $this->getComponent('filterForm')->getComponent('filter');
		\assert($container instanceof \Nette\Forms\Container);

		/** @var \Nette\Forms\Controls\BaseControl|\Nette\Forms\Container */
		return $container->getComponent($filter->getControlName());
	}


	/**
	 * Active filters with their normalized values - set by the user or default ones
	 *
	 * @return array<string, mixed>
	 */
	public function getFilterValues(): array
	{
		$values = [];

		foreach($this->filters as $key => $filter)
		{
			$value = array_key_exists($key, $this->filter)
				? $filter->normalize($this->filter[$key])
				: $filter->normalize($this->defaultFilter[$key] ?? null);

			if($value !== null)
			{
				$values[$key] = $value;
			}
		}

		return $values;
	}


	/********************************************************************************
	 * Actions
	 ********************************************************************************/


	/**
	 * Button in every row
	 *
	 * @param string $destination ":Module:Presenter:action", "action" of the current presenter or "signal!" of the parent
	 * @param array<string, string>|null $parameters link parameter => column of the row (default id => primary key)
	 */
	public function addAction(string $destination, ?array $parameters = null, ?string $caption = null): Action
	{
		$key = rtrim($destination, '!');

		return $this->addActionObject(new Action($this, $key, $destination, $parameters ?? ['id' => $this->primaryKey], $caption));
	}


	/**
	 * Button in every row handled by the grid - $action->onClick[] = fn($id) => ...
	 */
	public function addCallbackAction(string $key, ?string $caption = null): CallbackAction
	{
		return $this->addActionObject(new CallbackAction($this, $key, '', [], $caption));
	}


	/**
	 * @template T of Action
	 * @param T $action
	 * @return T
	 */
	public function addActionObject(Action $action): Action
	{
		if(isset($this->actions[$action->getKey()]))
		{
			throw new \Nette\InvalidArgumentException("Action '{$action->getKey()}' already exists - two actions with the same destination need setCaption() / own key via addActionObject().");
		}

		return $this->actions[$action->getKey()] = $action;
	}


	/**
	 * @return array<string, Action>
	 */
	public function getActions(): array
	{
		return $this->actions;
	}


	/**
	 * Button above the table
	 *
	 * @param array<string, mixed> $parameters link parameters (values)
	 */
	public function addToolbarButton(string $destination, ?string $caption = null, array $parameters = []): ToolbarButton
	{
		return $this->toolbarButtons[] = new ToolbarButton($this, $destination, $caption, $parameters);
	}


	/**
	 * Action with the checked rows - with $options it is a menu of the options
	 *
	 * @param array<int|string, string>|null $options
	 */
	public function addGroupAction(string $caption, ?array $options = null): GroupAction
	{
		$key = 'group' . count($this->groupActions);

		return $this->groupActions[$key] = new GroupAction($key, $caption, $options);
	}


	/**
	 * Only rows the callback returns true for can be checked for group actions
	 *
	 * @param \Closure(ActiveRow): bool $callback
	 */
	public function setGroupSelectable(\Closure $callback): static
	{
		$this->groupSelectable = $callback;

		return $this;
	}


	public function isGroupSelectable(ActiveRow $row): bool
	{
		return $this->groupSelectable === null || ($this->groupSelectable)($row);
	}


	/**
	 * Export by a callback - gets the sorted Selection (filtered with $filtered)
	 *
	 * @param \Closure(Selection $selection, Datagrid $grid): void $callback
	 */
	public function addExport(string $caption, \Closure $callback, bool $filtered = false): Export
	{
		$key = 'export' . count($this->exports);

		return $this->exports[$key] = new Export($this, $key, $caption, $callback, $filtered);
	}


	/**
	 * CSV of the columns of the grid
	 */
	public function addExportCsv(string $caption, string $fileName, bool $filtered = false, string $encoding = 'UTF-8', string $delimiter = ';', bool $bom = true): CsvExport
	{
		$key = 'export' . count($this->exports);

		return $this->exports[$key] = new CsvExport($this, $key, $caption, $fileName, $filtered, $encoding, $delimiter, $bom);
	}


	/**
	 * Detail of a row under the row - template file (gets $row and $parameters) or a callback returning the content
	 *
	 * @param string|\Closure(ActiveRow): (string|\Nette\HtmlStringable) $content
	 * @param array<string, mixed> $parameters
	 */
	public function setDetail(string|\Closure $content, array $parameters = []): Detail
	{
		return $this->detail = new Detail($this, $content, $parameters);
	}


	/********************************************************************************
	 * Data
	 ********************************************************************************/


	/**
	 * Source with the active filters
	 */
	public function getFilteredSource(): Selection
	{
		$selection = $this->getSource();

		foreach($this->getFilterValues() as $key => $value)
		{
			$this->filters[$key]->apply($selection, $value);
		}

		return $selection;
	}


	/**
	 * Active sorting - column key (or database column of the default sort) => ASC|DESC
	 *
	 * @return array<string, string>
	 */
	public function getSort(): array
	{
		foreach($this->sort as $key => $direction)
		{
			if(isset($this->columns[$key]) && $this->columns[$key]->isSortable() && in_array($direction, ['ASC', 'DESC'], true))
			{
				return [$key => $direction];
			}
		}

		return $this->defaultSort;
	}


	public function applySort(Selection $selection): void
	{
		$sort = $this->getSort();

		if(!$sort)
		{
			return;
		}

		$selection->getSqlBuilder()->setOrder([], []);

		foreach($sort as $key => $direction)
		{
			$column = isset($this->columns[$key]) ? $this->columns[$key]->getSortColumn() : $key;

			if($column instanceof \Closure)
			{
				$column($selection, $direction);
			}
			else
			{
				$selection->order($column . ' ' . $direction);
			}
		}
	}


	/**
	 * Number of filtered rows - DISTINCT of GROUP BY columns for grouped sources
	 */
	public function getItemCount(): int
	{
		if($this->itemCount === null)
		{
			$selection = $this->getFilteredSource();
			$group = $selection->getSqlBuilder()->getGroup();

			$this->itemCount = $group !== ''
				? $selection->count('DISTINCT ' . preg_replace('/\s+(ASC|DESC)\b/i', '', $group))
				: $selection->count('*');
		}

		return $this->itemCount;
	}


	public function getPerPage(): int
	{
		return in_array($this->perPage, $this->perPageList, true)
			? $this->perPage
			: ($this->defaultPerPage ?? $this->perPageList[0] ?? 10);
	}


	/**
	 * @return list<int>
	 */
	public function getPerPageList(): array
	{
		return $this->perPageList;
	}


	/**
	 * Primary key of a row as a string (it is a part of links and of the HTML)
	 */
	public function getRowId(ActiveRow $row): string
	{
		return (string) RowValue::get($row, $this->primaryKey);
	}


	/**
	 * Primary key column usable in where() of the source
	 */
	private function getPrimaryColumn(Selection $selection): string
	{
		return str_contains($this->primaryKey, '.') ? $this->primaryKey : $selection->getName() . '.' . $this->primaryKey;
	}


	/**
	 * Row by its primary key, with the filters ignored
	 */
	public function getRow(int|string $id): ?ActiveRow
	{
		$selection = $this->getSource();

		return $selection->where($this->getPrimaryColumn($selection), $id)->fetch();
	}


	/**
	 * Link of an action - signal of the parent ("x!"), or a presenter link
	 *
	 * @param array<string, mixed> $parameters link parameter => column of the row, or the value itself without a row
	 */
	public function createLink(string $destination, array $parameters, ?ActiveRow $row = null): string
	{
		$arguments = [];

		foreach($parameters as $name => $value)
		{
			$arguments[$name] = $row !== null && is_string($value) ? RowValue::get($row, $value) : $value;
		}

		$parent = $this->getParent();

		if(str_ends_with($destination, '!') && $parent instanceof Component)
		{
			return $parent->link($destination, $arguments);
		}

		return $this->getPresenter()->link($destination, $arguments);
	}


	/********************************************************************************
	 * Redraw
	 ********************************************************************************/


	/**
	 * Redraws the whole grid (AJAX), or reloads the page
	 */
	public function reload(): void
	{
		if($this->getPresenter()->isAjax())
		{
			$this->redrawControl('grid');

			return;
		}

		$this->getPresenter()->redirect('this');
	}


	/**
	 * Redraws one row (AJAX), or reloads the page
	 */
	public function redrawItem(int|string $id): void
	{
		if($this->getPresenter()->isAjax())
		{
			$this->redrawIds[] = (string) $id;
			$this->redrawControl('items');

			return;
		}

		$this->getPresenter()->redirect('this');
	}


	/********************************************************************************
	 * State
	 ********************************************************************************/


	/**
	 * @param array<string, mixed> $params
	 */
	public function loadState(array $params): void
	{
		parent::loadState($params);

		if($this->rememberState && !array_intersect_key($params, array_flip(['page', 'perPage', 'sort', 'filter'])))
		{
			$state = $this->getStateSection()->get('state');

			if(is_array($state))
			{
				$this->page = (int) ($state['page'] ?? 1);
				$this->perPage = isset($state['perPage']) ? (int) $state['perPage'] : null;
				$this->sort = (array) ($state['sort'] ?? []);
				$this->filter = (array) ($state['filter'] ?? []);
			}
		}
	}


	private function storeState(): void
	{
		$this->itemCount = null;

		if($this->rememberState)
		{
			$this->getStateSection()->set('state', [
				'page' => $this->page,
				'perPage' => $this->perPage,
				'sort' => $this->sort,
				'filter' => $this->filter
			]);
		}
	}


	private function getStateSection(): \Nette\Http\SessionSection
	{
		return $this->getPresenter()->getSession('datagrid/' . $this->getPresenter()->getName() . '/' . $this->getUniqueId());
	}


	/********************************************************************************
	 * Signals
	 ********************************************************************************/


	public function handleSort(string $key): void
	{
		if(isset($this->columns[$key]) && $this->columns[$key]->isSortable())
		{
			$this->sort = [$key => ($this->getSort()[$key] ?? null) === 'ASC' ? 'DESC' : 'ASC'];
			$this->page = 1;
		}

		$this->storeState();
		$this->reload();
	}


	public function handlePage(int $page): void
	{
		$this->page = max(1, $page);

		$this->storeState();
		$this->reload();
	}


	public function handleResetFilter(): void
	{
		$this->filter = [];
		$this->page = 1;

		$this->storeState();
		$this->reload();
	}


	public function handleExport(string $export): void
	{
		$export = $this->exports[$export] ?? $this->getPresenter()->error('Export does not exist.');

		$selection = $export->isFiltered() ? $this->getFilteredSource() : $this->getSource();

		$this->applySort($selection);

		$export->export($selection);
	}


	public function handleStatus(string $id, string $key, string $value): void
	{
		$column = $this->columns[$key] ?? null;
		$row = $this->getRow($id);

		if(!$column instanceof StatusColumn || !$row || !$column->hasOption($value) || !$column->isChangeable($row))
		{
			$this->getPresenter()->error('Status cannot be changed.');
		}

		$column->change($id, $value);

		$this->redrawItem($id);
	}


	public function handleEdit(string $id, string $key): void
	{
		$column = $this->columns[$key] ?? null;
		$editable = $column?->getEditable();
		$row = $this->getRow($id);
		$value = $this->getPresenter()->getRequest()->getPost('value');

		if(!$editable || !$row || !$editable->isEditable($row) || !is_string($value))
		{
			$this->getPresenter()->error('Value cannot be edited.');
		}

		if($editable->getItems() !== null && !array_key_exists($value, $editable->getItems()))
		{
			$this->getPresenter()->error('Value is not in the items.');
		}

		$editable->save($id, $value);

		$this->redrawItem($id);
	}


	public function handleDetail(string $id): void
	{
		$row = $this->getRow($id);

		if(!$this->detail || !$row || !$this->detail->isVisible($row))
		{
			$this->getPresenter()->error('Detail does not exist.');
		}

		$this->openDetails[] = $id;

		if($this->getPresenter()->isAjax())
		{
			$this->redrawIds[] = $id;
			$this->redrawControl('items');
		}
	}


	public function handleAction(string $action, string $id): void
	{
		$action = $this->actions[$action] ?? null;
		$row = $this->getRow($id);

		if(!$action instanceof CallbackAction || !$row || !$action->isVisible($row))
		{
			$this->getPresenter()->error('Action does not exist.');
		}

		$action->click($id);

		$this->redrawItem($id);
	}


	public function handleGroupAction(): void
	{
		$request = $this->getPresenter()->getRequest();
		[$key, $value] = explode('|', (string) $request->getPost('groupAction'), 2) + [1 => null];
		$ids = array_values(array_map(strval(...), array_filter((array) $request->getPost('ids'), is_scalar(...))));

		$action = $this->groupActions[$key] ?? null;
		$option = $action?->findOption($value);

		if(!$action || $option === false)
		{
			$this->getPresenter()->error('Group action does not exist.');
		}

		if($ids)
		{
			$action->select($ids, $option);
		}

		$this->reload();
	}


	/********************************************************************************
	 * Filter form
	 ********************************************************************************/


	protected function createComponentFilterForm(): Form
	{
		$form = new Form;
		$form->setAjax();
		$form->setSize('sm');
		$form->setHtmlAttribute('data-datagrid-filter', true);

		$container = $form->addContainer('filter');

		foreach($this->filters as $filter)
		{
			$filter->addControl($container, $this);
		}

		$form->addSelect('perPage', $this->getText('perPage'), array_combine($this->perPageList, $this->perPageList))
			->setHtmlAttribute('data-datagrid-autosubmit', 'change');

		/**
		 * The form is an empty <form> element, its controls are in the header of the table and belong to it by the form attribute
		 */
		$form->getElementPrototype()->id = $this->getFormId('filter');

		foreach($form->getControls() as $control)
		{
			if($control instanceof \Nette\Forms\Controls\BaseControl)
			{
				$control->setHtmlAttribute('form', $this->getFormId('filter'));
			}
		}

		$form->onAnchor[] = function(Form $form): void
		{
			$values = [];

			foreach($this->getFilterValues() as $key => $value)
			{
				$values[$this->filters[$key]->getControlName()] = $this->filters[$key]->getFormValue($value);
			}

			$form->setDefaults(['filter' => $values, 'perPage' => $this->getPerPage()]);
		};

		$form->onSuccess[] = $this->filterFormSucceeded(...);

		return $form;
	}


	private function filterFormSucceeded(Form $form): void
	{
		/** @var array{filter: array<string, mixed>, perPage: int|string|null} $values */
		$values = $form->getValues('array');
		$filter = [];

		foreach($this->filters as $key => $item)
		{
			$value = $item->normalize($values['filter'][$item->getControlName()] ?? null);

			if($value !== null)
			{
				$filter[$key] = $value;
			}
			elseif(array_key_exists($key, $this->defaultFilter))
			{
				/**
				 * Default filter cleared by the user
				 */
				$filter[$key] = '';
			}
		}

		$perPage = (int) $values['perPage'];

		if($filter !== $this->filter || $perPage !== $this->getPerPage())
		{
			$this->page = 1;
		}

		$this->filter = $filter;
		$this->perPage = $perPage === ($this->defaultPerPage ?? $this->perPageList[0] ?? null) ? null : $perPage;

		$this->storeState();
		$this->reload();
	}


	/********************************************************************************
	 * Render
	 ********************************************************************************/


	public function render(): void
	{
		$template = $this->getTemplate();
		\assert($template instanceof \Nette\Bridges\ApplicationLatte\Template);

		$paginator = null;

		if($this->redrawIds !== null)
		{
			/**
			 * Redrawn rows are updated in place even when they do not match the filter anymore
			 */
			$selection = $this->getSource();
			$selection->where($this->getPrimaryColumn($selection), $this->redrawIds);
		}
		else
		{
			$selection = $this->getFilteredSource();
		}

		if($this->redrawIds === null && $this->pagination)
		{
			$paginator = new Paginator;
			$paginator->setItemCount($this->getItemCount());
			$paginator->setItemsPerPage($this->getPerPage());
			$paginator->setPage($this->page);

			$selection->limit($paginator->getLength(), $paginator->getOffset());
		}

		$this->applySort($selection);

		$rows = [];

		foreach($selection as $row)
		{
			$rows[$this->getRowId($row)] = $row;
		}

		$columnFilters = $this->outerFilters ? [] : array_intersect_key($this->filters, $this->columns);
		$hasActions = $this->actions || $this->detail;

		$parameters = [
			'originalTemplate' => __DIR__ . '/templates/datagrid.latte',
			'grid' => $this,
			'rows' => $rows,
			'columns' => $this->columns,
			'actions' => $this->actions,
			'toolbarButtons' => $this->toolbarButtons,
			'groupActions' => $this->groupActions,
			'exports' => $this->exports,
			'detail' => $this->detail,
			'openDetails' => $this->openDetails,
			'columnFilters' => $columnFilters,
			'outerFilters' => array_diff_key($this->filters, $columnFilters),
			'filterValues' => $this->getFilterValues(),
			'hasFilter' => (bool) array_filter($this->filter, fn(mixed $value): bool => $value !== ''),
			'sort' => $this->getSort(),
			'paginator' => $paginator,
			'hasActions' => $hasActions,
			'colspan' => count($this->columns) + ($this->groupActions ? 1 : 0) + ($hasActions ? 1 : 0),
			'groupFormId' => $this->getFormId('group'),
			'texts' => $this->texts
		];

		foreach($parameters as $name => $value)
		{
			$template->$name = $value;
		}

		$template->setFile($this->templateFile ?? $parameters['originalTemplate']);
		$template->render();
	}


	/**
	 * Id of the filter form or of the form of group actions
	 */
	public function getFormId(string $form): string
	{
		return $this->getUniqueId() . '-' . $form;
	}


	/**
	 * Icon of the icon set of modul-is/form
	 */
	public function icon(string $icon): Html
	{
		return \ModulIS\Form\Renderer\Bootstrap6Renderer::getDefaultIconRenderer()->render($icon);
	}


	/**
	 * <tr> of a row with the row callback applied
	 */
	public function createRowElement(ActiveRow $row): Html
	{
		$tr = Html::el('tr');

		if($this->rowCallback)
		{
			($this->rowCallback)($row, $tr);
		}

		return $tr;
	}
}
