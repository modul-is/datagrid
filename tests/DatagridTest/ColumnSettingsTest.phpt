<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use ModulIS\Datagrid\Datagrid;
use Tester\Assert;


function createColumnGrid(): Datagrid
{
	$grid = new Datagrid(createExplorer()->table('person'));
	$grid->setDefaultSort(['id' => 'ASC']);
	$grid->addText('firstname', 'Jméno')->setSortable()->setFilterText();
	$grid->addText('lastname', 'Příjmení')->setSortable()->setFilterText();
	$grid->addNumber('salary', 'Plat')->setSortable();

	return $grid;
}


test('without settings - columns in the order they were added, all visible, no button', function(): void
{
	$grid = createColumnGrid();

	Assert::same(['firstname', 'lastname', 'salary'], array_keys($grid->getVisibleColumns()));
	Assert::false($grid->hasColumnSettings());
});


test('order and hidden columns of the settings', function(): void
{
	$grid = createColumnGrid();
	$grid->setColumnSettings(['order' => ['salary', 'lastname', 'firstname'], 'hidden' => ['lastname']], function(?array $settings): void
	{
	});

	Assert::true($grid->hasColumnSettings());
	Assert::same(['salary', 'lastname', 'firstname'], array_keys($grid->getOrderedColumns()));
	Assert::same(['salary', 'firstname'], array_keys($grid->getVisibleColumns()));
	Assert::false($grid->isColumnVisible('lastname'));
});


test('unknown columns of stored settings are ignored, new columns are behind the ordered ones', function(): void
{
	$grid = createColumnGrid();
	$grid->applyColumnSettings(['order' => ['removed', 'lastname'], 'hidden' => ['removed']]);

	Assert::same(['lastname', 'firstname', 'salary'], array_keys($grid->getVisibleColumns()));
});


test('filter and sort of a hidden column are not applied', function(): void
{
	$grid = createColumnGrid();
	$grid->filter = ['lastname' => 'novák'];
	$grid->sort = ['lastname' => 'DESC'];

	Assert::same(['lastname' => 'novák'], $grid->getFilterValues());
	Assert::same(['lastname' => 'DESC'], $grid->getSort());

	$grid->applyColumnSettings(['hidden' => ['lastname']]);

	Assert::same([], $grid->getFilterValues());
	Assert::same(['id' => 'ASC'], $grid->getSort());
	Assert::same([1, 2, 3, 4, 5, 6], gridIds($grid));
});


test('null settings - back to the defaults', function(): void
{
	$grid = createColumnGrid();
	$grid->applyColumnSettings(['order' => ['salary'], 'hidden' => ['firstname']]);
	$grid->applyColumnSettings(null);

	Assert::same(['firstname', 'lastname', 'salary'], array_keys($grid->getVisibleColumns()));
});
