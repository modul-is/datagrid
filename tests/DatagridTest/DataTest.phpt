<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use ModulIS\Datagrid\Datagrid;
use ModulIS\Datagrid\RowValue;
use Nette\Database\Table\Selection;
use Tester\Assert;


test('sort - column, its sort column, invalid sorts ignored', function(): void
{
	$grid = new Datagrid(createExplorer()->table('person'));
	$grid->setDefaultSort(['id' => 'DESC']);
	$grid->addNumber('salary', 'Plat')->setSortable();
	$grid->addText('name', 'Jméno', 'lastname')->setSortable('firstname');
	$grid->addText('note', 'Poznámka');

	Assert::same([6, 5, 4, 3, 2, 1], gridIds($grid));

	$grid->sort = ['salary' => 'ASC'];

	// NULL first in SQLite
	Assert::same([4, 6, 1, 2, 3, 5], gridIds($grid));

	$grid->sort = ['name' => 'DESC'];

	Assert::same([6, 3, 5, 2, 1, 4], gridIds($grid));

	$grid->sort = ['note' => 'ASC'];

	Assert::same(['id' => 'DESC'], $grid->getSort());

	$grid->sort = ['salary' => 'RANDOM()'];

	Assert::same(['id' => 'DESC'], $grid->getSort());
});


test('sort - callback', function(): void
{
	$grid = new Datagrid(createExplorer()->table('person'));
	$grid->addText('name', 'Jméno')
		->setSortable(function(Selection $selection, string $direction): void
		{
			$selection->order('lastname ' . $direction)->order('firstname ' . $direction);
		});

	$grid->sort = ['name' => 'ASC'];

	// binary collation of SQLite - "Černý" after ASCII letters
	Assert::same([4, 6, 1, 2, 3, 5], gridIds($grid));
});


test('sort replaces the order of the source', function(): void
{
	$grid = new Datagrid(createExplorer()->table('person')->order('id DESC'));
	$grid->addNumber('id', 'ID')->setSortable();
	$grid->sort = ['id' => 'ASC'];

	Assert::same([1, 2, 3, 4, 5, 6], gridIds($grid));
});


test('count of filtered rows, grouped source counts groups', function(): void
{
	$explorer = createExplorer();

	$grid = new Datagrid($explorer->table('person'));
	$grid->addFilterSelect('status', 'Stav', ['A' => 'A', 'B' => 'B']);
	$grid->filter = ['status' => 'A'];

	Assert::same(3, $grid->getItemCount());

	$grid = new Datagrid($explorer->table('person')->select('status, COUNT(*) AS count')->group('status'));

	Assert::same(3, $grid->getItemCount());
});


test('the source given to the grid is not changed', function(): void
{
	$source = createExplorer()->table('person');

	$grid = new Datagrid($source);
	$grid->addFilterSelect('status', 'Stav', ['A' => 'A']);
	$grid->filter = ['status' => 'A'];

	gridIds($grid);

	Assert::same(6, $source->count('*'));
});


test('row by the primary key ignores filters', function(): void
{
	$grid = new Datagrid(createExplorer()->table('person'));
	$grid->addFilterSelect('status', 'Stav', ['A' => 'A']);
	$grid->filter = ['status' => 'A'];

	Assert::same('Svoboda', $grid->getRow(3)?->lastname);
	Assert::null($grid->getRow(99));
});


test('value of a row - column, referenced and related row', function(): void
{
	$explorer = createExplorer();
	$person = $explorer->table('person')->get(1);
	$insurance = $explorer->table('insurance')->get(3);

	Assert::same('Novák', RowValue::get($person, 'lastname'));
	Assert::same('Kooperativa', RowValue::get($person, 'insurance.name'));
	Assert::null(RowValue::get($explorer->table('person')->get(4), 'insurance.name'));
	Assert::same('Černý', RowValue::get($insurance, ':person.lastname'));
});


test('columns render values', function(): void
{
	$explorer = createExplorer();
	$grid = new Datagrid($explorer->table('person'));
	$person = $explorer->table('person')->get(5);

	Assert::same("61\u{a0}000", $grid->addNumber('salary', 'Plat')->render($person));
	Assert::same('61 000,00', $grid->addNumber('salary2', 'Plat', 'salary')->setFormat(2, ',', ' ')->render($person));
	Assert::same('20. 3. 2026', $grid->addDateTime('created_at', 'Vytvořeno')->render($person));
	Assert::same('Aktivní', $grid->addDial('status', 'Stav', ['A' => 'Aktivní'])->render($person));
	Assert::same('Generali', $grid->addText('insurance', 'Pojišťovna', 'insurance.name')->render($person));
	Assert::same('Černý Novák', $grid->addText('name', 'Jméno')->setRenderer(fn($row) => $row->lastname . ' ' . $row->firstname)->render($person));
});


test('text of the cells for the CSV export', function(): void
{
	$explorer = createExplorer();
	$grid = new Datagrid($explorer->table('person'));
	$person = $explorer->table('person')->get(5);

	// number without the thousands separator, decimals by the format
	Assert::same('61000', $grid->addNumber('salary', 'Plat')->renderText($person));
	Assert::same('61000,00', $grid->addNumber('salary2', 'Plat', 'salary')->setFormat(2)->renderText($person));

	// blocks of HTML separated by a space, entities decoded
	Assert::same('Černý Novák & syn', $grid->addText('name', 'Jméno')
		->setRenderer(fn($row) => Nette\Utils\Html::el()
			->addHtml(Nette\Utils\Html::el('div')->setText($row->lastname))
			->addHtml(Nette\Utils\Html::el('div')->setText($row->firstname . ' & syn')))
		->renderText($person));
});


test('align - center by default, own align of the grid and of the column', function(): void
{
	$grid = new Datagrid(createExplorer()->table('person'));
	$name = $grid->addText('lastname', 'Příjmení');
	$salary = $grid->addNumber('salary', 'Plat')->setAlign('end');

	Assert::same('center', $name->getAlign());
	Assert::same('end', $salary->getAlign());

	$grid->setDefaultAlign('start');

	Assert::same('start', $name->getAlign());
	Assert::same('end', $salary->getAlign());

	Assert::exception(fn() => $name->setAlign('left'), Nette\InvalidArgumentException::class);
});


test('status - current option compared as strings', function(): void
{
	$explorer = createExplorer();
	$grid = new Datagrid($explorer->table('person'));
	$column = $grid->addStatus('insurance_id', 'Pojišťovna', ['1' => 'Kooperativa', 3 => 'Generali']);

	Assert::same(3, $column->getCurrentOption($explorer->table('person')->get(5)));
	Assert::same(1, $column->getCurrentOption($explorer->table('person')->get(1)));
	Assert::null($column->getCurrentOption($explorer->table('person')->get(2)));
	Assert::true($column->hasOption('1'));
	Assert::false($column->hasOption('2'));
});


test('state - per page only from the list', function(): void
{
	$grid = new Datagrid(createExplorer()->table('person'));
	$grid->setPerPageList([10, 20], 20);

	Assert::same(20, $grid->getPerPage());

	$grid->perPage = 10;

	Assert::same(10, $grid->getPerPage());

	$grid->perPage = 1000;

	Assert::same(20, $grid->getPerPage());
});


test('outer filters - count of columns divides the row', function(): void
{
	$grid = new Datagrid(createExplorer()->table('person'));

	Assert::same($grid, $grid->setOuterFilterColumns(3));

	Assert::exception(
		fn() => $grid->setOuterFilterColumns(5),
		Nette\InvalidArgumentException::class,
		'Count of columns of outer filters must be 1, 2, 3, 4, 6 or 12, 5 given.'
	);
});
