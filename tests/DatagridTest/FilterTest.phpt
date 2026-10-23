<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use ModulIS\Datagrid\Datagrid;
use Nette\Database\Table\Selection;
use Tester\Assert;


/**
 * Grid over all people sorted by id with the given filter state (persistent parameter "filter")
 *
 * @param array<string, mixed> $filter
 */
function createGrid(array $filter = []): Datagrid
{
	$grid = new Datagrid(createExplorer()->table('person'));
	$grid->setDefaultSort(['id' => 'ASC']);
	$grid->filter = $filter;

	return $grid;
}


test('text - every word in one of the columns', function(): void
{
	$grid = createGrid(['name' => 'novák']);
	$grid->addFilterText('name', 'Jméno', ['lastname', 'firstname']);

	// "Novák" in lastname (1, 2) and in firstname (5)
	Assert::same([1, 2, 5], gridIds($grid));

	$grid->filter = ['name' => 'jan  novák'];

	// both words, each in any of the columns, double space ignored
	Assert::same([1, 2], gridIds($grid));
});


test('text - LIKE wildcards are searched literally', function(): void
{
	$grid = createGrid(['note' => '100%']);
	$grid->addFilterText('note', 'Poznámka');

	Assert::same([1], gridIds($grid));

	$grid->filter = ['note' => 'a_b'];

	Assert::same([3], gridIds($grid));
});


test('text - exact value', function(): void
{
	$grid = createGrid(['name' => 'Novák']);
	$grid->addFilterText('name', 'Jméno', ['lastname', 'firstname'])
		->setExact();

	Assert::same([1, 5], gridIds($grid));
});


test('select - only values of the items, compared as strings', function(): void
{
	$grid = createGrid(['insurance' => '3']);
	$grid->addFilterSelect('insurance', 'Pojišťovna', [1 => 'Kooperativa', 2 => 'Allianz', 3 => 'Generali'], 'insurance_id');

	Assert::same(['insurance' => 3], $grid->getFilterValues());
	Assert::same([5, 6], gridIds($grid));

	$grid->filter = ['insurance' => '99'];

	Assert::same([], $grid->getFilterValues());
	Assert::same([1, 2, 3, 4, 5, 6], gridIds($grid));
});


test('select - column of a referenced table', function(): void
{
	$grid = createGrid(['insurance' => 'Kooperativa']);
	$grid->addFilterSelect('insurance', 'Pojišťovna', ['Kooperativa' => 'Kooperativa', 'Generali' => 'Generali'], 'insurance.name');

	Assert::same([1, 3], gridIds($grid));
});


test('multiselect - IN of the valid values', function(): void
{
	$grid = createGrid(['status' => ['A', 'N', 'X', ['nested']]]);
	$grid->addFilterMultiSelect('status', 'Stav', ['A' => 'Aktivní', 'B' => 'Zablokovaný', 'N' => 'Nový']);

	Assert::same(['status' => ['A', 'N']], $grid->getFilterValues());
	Assert::same([1, 2, 4, 5], gridIds($grid));
});


test('date and date range - DATE() of the column, invalid dates ignored', function(): void
{
	$grid = createGrid(['created' => '2026-02-15']);
	$grid->addFilterDate('created', 'Vytvořeno', 'created_at');

	Assert::same([2, 3], gridIds($grid));

	$grid = createGrid(['created' => ['from' => '2026-02-15', 'to' => '2026-03-01']]);
	$grid->addFilterDateRange('created', 'Vytvořeno', 'created_at');

	Assert::same([2, 3, 4], gridIds($grid));

	$grid->filter = ['created' => ['from' => '2026-02-30', 'to' => '2026-03-01']];

	Assert::same(['created' => ['from' => null, 'to' => '2026-03-01']], $grid->getFilterValues());
	Assert::same([1, 2, 3, 4], gridIds($grid));
});


test('range - numbers with spaces and decimal comma', function(): void
{
	$grid = createGrid(['salary' => ['from' => '30 000', 'to' => '52000,5']]);
	$grid->addFilterRange('salary', 'Plat');

	Assert::same([1, 2, 3], gridIds($grid));

	$grid->filter = ['salary' => ['from' => 'abc', 'to' => '']];

	Assert::same([], $grid->getFilterValues());
});


test('own condition gets the normalized value', function(): void
{
	$grid = createGrid(['paid' => '1']);
	$grid->addFilterSelect('paid', 'Plat vyplněn', [1 => 'Ano', 0 => 'Ne'])
		->setCondition(function(Selection $selection, int|string $value): void
		{
			$selection->where($value === 1 ? 'salary IS NOT NULL' : 'salary IS NULL');
		});

	Assert::same([1, 2, 3, 5, 6], gridIds($grid));
});


test('default filter applies until the user changes it, empty string clears it', function(): void
{
	$grid = createGrid();
	$grid->addFilterSelect('status', 'Stav', ['A' => 'Aktivní', 'B' => 'Zablokovaný']);
	$grid->setDefaultFilter(['status' => 'B']);

	Assert::same([3, 6], gridIds($grid));

	$grid->filter = ['status' => 'A'];

	Assert::same([1, 2, 5], gridIds($grid));

	$grid->filter = ['status' => ''];

	Assert::same([1, 2, 3, 4, 5, 6], gridIds($grid));
});
