<?php

declare(strict_types = 1);

require __DIR__ . '/../bootstrap.php';

use ModulIS\Datagrid\Datagrid;
use ModulIS\Datagrid\RowValue;
use Nette\Database\Table\ActiveRow;
use Tester\Assert;


/**
 * Columns over several tables - the key is the path (person -> insurance -> company), sort and filter use it too
 */
function createPathGrid(array $filter = []): Datagrid
{
	$grid = new Datagrid(createExplorer()->table('person'));
	$grid->setDefaultSort(['id' => 'ASC']);
	$grid->filter = $filter;

	$grid->addText('insurance.company.name', 'Skupina')
		->setSortable()
		->setFilterText();

	$grid->addText('insurance.name', 'Pojišťovna')
		->setFilterSelect([1 => 'Kooperativa', 2 => 'Allianz', 3 => 'Generali'], 'insurance.id');

	return $grid;
}


test('value of the column over three tables', function(): void
{
	$grid = createPathGrid();
	$column = $grid->getColumn('insurance.company.name');
	$rows = $grid->getFilteredSource()->fetchAll();

	Assert::same('insurance.company.name', $column->getColumn());
	Assert::same('VIG', $column->getValue($rows[1]));
	Assert::same('VIG', RowValue::get($rows[1], 'insurance.company.name'));

	// insurance without company, person without insurance
	Assert::null($column->getValue($rows[5]));
	Assert::null($column->getValue($rows[4]));
});


test('sort by the column over three tables', function(): void
{
	$grid = createPathGrid();
	$grid->sort = ['insurance.company.name' => 'DESC'];

	$selection = $grid->getFilteredSource();
	$grid->applySort($selection);

	$values = array_map(fn(ActiveRow $row): ?string => RowValue::get($row, 'insurance.company.name'), array_values($selection->fetchAll()));

	Assert::same(['VIG', 'VIG', 'Allianz SE', null, null, null], $values);
});


test('filter by the column over three tables', function(): void
{
	Assert::same([1, 3], gridIds(createPathGrid(['insurance.company.name' => 'vig'])));
	Assert::same([2], gridIds(createPathGrid(['insurance.company.name' => 'allianz se'])));
	Assert::same([5, 6], gridIds(createPathGrid(['insurance.name' => 3])));
});


test('filter form - control of the key with dots', function(): void
{
	$grid = createPathGrid();

	// components of the grid (filter form) need a presenter
	$presenter = new class extends Nette\Application\UI\Presenter {
	};
	$presenter->addComponent($grid, 'grid');

	$text = $grid->getFilterControl('insurance.company.name');
	$select = $grid->getFilterControl('insurance.name');

	Assert::type(Nette\Forms\Controls\TextInput::class, $text);
	Assert::type(Nette\Forms\Controls\SelectBox::class, $select);
	Assert::same('Skupina', $text->getCaption());
});


test('two keys with the same control name are refused', function(): void
{
	$grid = createPathGrid();

	Assert::exception(
		fn() => $grid->addFilterText('insurance__company__name', 'Duplicita'),
		Nette\InvalidArgumentException::class,
		'%a%insurance__company__name%a%'
	);
});
