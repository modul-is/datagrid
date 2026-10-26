# Datagrid for Nette with Bootstrap 6

Simple datagrid for Nette - rows of a **Nette Database `Selection`**, rendered for **Bootstrap 6**, client side in plain
JavaScript ([naja](https://naja.js.org)). Filters are controls of [modul-is/form](https://github.com/modul-is/form).

Features: columns (text, number, date, bool, dial, link, status), filters in the header of the columns or above the table
(text, select, multiselect, date, date range, range), sorting, pagination, row actions with confirmation, toolbar buttons,
group actions, CSV and custom exports, detail of a row, editable cells, remembered state.

## Getting started

```php
use ModulIS\Datagrid\Datagrid;
use Nette\Database\Table\ActiveRow;

protected function createComponentGrid(): Datagrid
{
	$grid = new Datagrid($this->Explorer->table('user'));
	$grid->setDefaultSort(['lastname' => 'ASC']);

	$grid->addText('lastname', 'Příjmení')
		->setSortable()
		->setFilterText();

	$grid->addText('insurance.name', 'Pojišťovna')                 // column of the referenced row
		->setSortable()
		->setFilterSelect($insurancePairs, 'insurance_id');

	$grid->addText('insurance.company.name', 'Skupina')           // over three tables - sorted and filtered by the same path
		->setSortable()
		->setFilterText();

	$grid->addDateTime('created', 'Vytvořeno')
		->setFormat('j. n. Y H:i')
		->setFilterDateRange();

	$grid->addAction(':Admin:User:edit')
		->setIcon('edit')
		->setTitle('Editovat')
		->setColor('info');

	$grid->addAction('delete!')                                      // signal of the parent of the grid
		->setIcon('trash')
		->setColor('danger')
		->setAjax()
		->setConfirm('Smazat uživatele %s?', 'lastname')
		->setVisible(fn(ActiveRow $row): bool => !$row->locked);

	return $grid;
}
```

The grid never changes the `Selection` given to it - it works with clones.

### Installation

```
composer require modul-is/datagrid
npm install @modul-is/datagrid @modul-is/form bootstrap@6.0.0-alpha.1 naja nette-forms
```

```js
import naja from 'naja';
import { initForms } from '@modul-is/form';
import { initDatagrid } from '@modul-is/datagrid';
import '@modul-is/form/css';
import '@modul-is/datagrid/css';

initForms(naja);
initDatagrid(naja);

document.addEventListener('DOMContentLoaded', () => naja.initialize());
```

Optional DI extension with defaults of all grids:

```neon
extensions:
	modulisDatagrid: ModulIS\Datagrid\DI\DatagridExtension

modulisDatagrid:
	align: center                # default align of the columns (start, center, end)
	perPageList: [10, 25, 50]
	rememberState: false         # state of the grids in the session
	texts:                       # texts of the grid, see Datagrid::$defaults
		noData: Nic nenalezeno
```

Icons are taken from modul-is/form (`modulisForm: icons: fontawesome`), the grid uses `check`, `remove`, `eye` and `download`.

## Columns

| Method | Column |
| --- | --- |
| `addText($key, $label, ?$column)` | value as it is |
| `addNumber(...)` | `setFormat($decimals, $decimalSeparator, $thousandsSeparator)` |
| `addDateTime(...)` | `setFormat('j. n. Y')` - DateTime or a string |
| `addBool(...)` | check / cross icon, `setIconTrue()`, `setIconFalse()` |
| `addDial($key, $label, $list, ?$column)` | value translated by a list |
| `addLink($key, $label, $destination, ?$parameters, ?$column)` | value as a link, `setNewTab()`, `setLinkClass()` |
| `addStatus($key, $label, $options, ?$column)` | button with a menu of the options, `setColors()`, `setConfirm()`, `onChange[]` |

`$column` is the path of the value in the row - default the key, `table.column` for a referenced row (chained
`a.b.column`), `:table.column` for the first related row. The key itself can be the path, `$column` is then needed
only when the key differs - sorting and filtering of the column use the path too (`addText('insurance.company.name', 'Skupina')->setSortable()->setFilterText()`).
Own cell block of such a column is `{define col-insurance.company.name}`. Settings of all columns:

- `setSortable(true|string|Closure)` - by the column, by another database column / expression, or by own sorting `fn(Selection $selection, string $direction)`
- `setRenderer(fn(ActiveRow $row): string|Html|null)`, `setTemplate($file, $parameters)` (gets `$row`, `$column`), `setReplacement($list)`
- `setAlign('start'|'center'|'end')`, `setWidth(15)` (percent) or `setWidth('8rem')`, `setClass()`, `setAttributes()`
- `setEditable(fn($id, string $value) => ..., 'text'|'number'|'date'|'textarea'|'select', ?$items)` - edited by a click, `->setValue(fn($row))`, `->setCondition(fn($row): bool)`
- `setFilterText()`, `setFilterSelect()`, `setFilterMultiSelect()`, `setFilterDate()`, `setFilterDateRange()`, `setFilterRange()` - filter in the header of the column

## Filters

A filter with the key of a column is shown in the header of the column, other filters (`addFilterText()`,
`addFilterSelect()`, ... of the grid) above the table - `setOuterFilters()` shows all of them above the table.

| Filter | Condition |
| --- | --- |
| text | every word in one of the columns (`LIKE %word%`), `setExact()`, `setSplitWords(false)` |
| select | `column = value`, prompt "~ Vše ~" added (unless the items have the key `''`), `setPrompt()`, `setSearch()` |
| multiselect | `column IN (...)` |
| date / date range | `DATE(column) = / >= / <=` |
| range | `column >= / <=`, decimal comma accepted |

`setCondition(fn(Selection $selection, mixed $value): void)` replaces the condition - the value is normalized (valid item of
a select, `Y-m-d` date, `['from' => ?, 'to' => ?]` of ranges). `setDefaultFilter(['active' => 1])` applies until the user
changes the filter.

## Grid

```php
$grid->setPrimaryKey('id');                         // also 'car.id'
$grid->setDefaultSort(['created' => 'DESC']);       // column key or a database column
$grid->setPerPageList([10, 20, 50], default: 20);
$grid->setPagination(false);
$grid->setRememberState();
$grid->setRowCallback(fn(ActiveRow $row, Html $tr) => $tr->class($row->active ? null : 'opacity-50'));
$grid->setTemplateFile(__DIR__ . '/grid.latte');    // {extends $originalTemplate}, override blocks
$grid->addToolbarButton(':Admin:User:add', 'Přidat')->setIcon('plus')->setColor('primary');
```

### Actions

`addAction($destination, ?$parameters, ?$caption)` - destination with `:` is a presenter link, `signal!` belongs to the parent
of the grid, other destinations are actions of the current presenter. Parameters map link parameters to columns of the row
(default `['id' => primary key]`). `setIcon()`, `setTitle()`, `setColor()`, `setClass()` accept a `Closure(ActiveRow)` too.

`addCallbackAction($key)->onClick[] = fn($id) => ...` is handled by the grid itself, the row is redrawn afterwards.

### Group actions

```php
$grid->addGroupAction('Smazat')->setColor('danger')->setConfirm('Smazat vybrané?')
	->onSelect[] = fn(array $ids) => ...;

$grid->addGroupAction('Přesunout', $categoryPairs)            // menu of the options
	->onSelect[] = fn(array $ids, int|string $categoryId) => ...;

$grid->setGroupSelectable(fn(ActiveRow $row): bool => !$row->locked);
```

### Exports

```php
$grid->addExportCsv('CSV', 'users.csv', filtered: true, encoding: 'windows-1250');
$grid->addExport('XLSX', fn(Selection $selection, Datagrid $grid) => $this->sendXlsx($selection), filtered: true);
```

### Detail of a row

```php
$grid->setDetail(__DIR__ . '/detail.latte', ['types' => $types]);      // template gets $row
$grid->setDetail(fn(ActiveRow $row) => Html::el('p')->setText($row->note))
	->setVisible(fn(ActiveRow $row): bool => $row->note !== null);
```

### Redraw

`$grid->reload()` redraws the grid (AJAX) or reloads the page, `$grid->redrawItem($id)` redraws one row.

## Client side

`assets/js` - filters submitted while typing (focus and typed text survive the redraw), confirmation dialog of actions
(`data-datagrid-confirm`), checkboxes of group actions, detail of a row and editable cells. All listeners are on the
document, grids in redrawn snippets need no initialization.

## Development

```
composer install
npm install
npm run demo          # demo page in demo/www (SQLite database created in demo/temp)
vendor/bin/tester -C tests/
vendor/bin/phpstan analyse
php vendor/modul-is/coding-standard/ecs check src demo/app --preset php84
```
