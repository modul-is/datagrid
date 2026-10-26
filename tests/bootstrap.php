<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

Tester\Environment::setup();
Tester\Environment::setupFunctions();
date_default_timezone_set('Europe/Prague');


/**
 * Explorer over an SQLite database in memory - company (2 rows), insurance (3 rows) and person (6 rows)
 */
function createExplorer(): Nette\Database\Explorer
{
	$connection = new Nette\Database\Connection('sqlite::memory:');
	$connection->query('CREATE TABLE company (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
	$connection->query('CREATE TABLE insurance (id INTEGER PRIMARY KEY, name TEXT NOT NULL, company_id INTEGER REFERENCES company(id))');
	$connection->query('
		CREATE TABLE person (
			id INTEGER PRIMARY KEY,
			firstname TEXT NOT NULL,
			lastname TEXT NOT NULL,
			status TEXT NOT NULL,
			salary INTEGER,
			created_at TEXT NOT NULL,
			note TEXT,
			insurance_id INTEGER REFERENCES insurance(id)
		)
	');

	$connection->query('INSERT INTO company', [
		['id' => 1, 'name' => 'VIG'],
		['id' => 2, 'name' => 'Allianz SE']
	]);

	$connection->query('INSERT INTO insurance', [
		['id' => 1, 'name' => 'Kooperativa', 'company_id' => 1],
		['id' => 2, 'name' => 'Allianz', 'company_id' => 2],
		['id' => 3, 'name' => 'Generali', 'company_id' => null]
	]);

	$connection->query('INSERT INTO person', [
		['id' => 1, 'firstname' => 'Jan', 'lastname' => 'Novák', 'status' => 'A', 'salary' => 30000, 'created_at' => '2026-01-10 08:00:00', 'note' => '100% hotovo', 'insurance_id' => 1],
		['id' => 2, 'firstname' => 'Jana', 'lastname' => 'Nováková', 'status' => 'A', 'salary' => 45000, 'created_at' => '2026-02-15 12:30:00', 'note' => null, 'insurance_id' => 2],
		['id' => 3, 'firstname' => 'Petr', 'lastname' => 'Svoboda', 'status' => 'B', 'salary' => 52000, 'created_at' => '2026-02-15 18:00:00', 'note' => 'a_b', 'insurance_id' => 1],
		['id' => 4, 'firstname' => 'Eva', 'lastname' => 'Dvořák', 'status' => 'N', 'salary' => null, 'created_at' => '2026-03-01 09:00:00', 'note' => 'axb', 'insurance_id' => null],
		['id' => 5, 'firstname' => 'Novák', 'lastname' => 'Černý', 'status' => 'A', 'salary' => 61000, 'created_at' => '2026-03-20 10:00:00', 'note' => null, 'insurance_id' => 3],
		['id' => 6, 'firstname' => 'Tomáš', 'lastname' => 'Marek', 'status' => 'B', 'salary' => 28000, 'created_at' => '2026-04-02 14:00:00', 'note' => '100 kusů', 'insurance_id' => 3]
	]);

	$storage = new Nette\Caching\Storages\DevNullStorage;
	$structure = new Nette\Database\Structure($connection, $storage);

	return new Nette\Database\Explorer($connection, $structure, new Nette\Database\Conventions\DiscoveredConventions($structure), $storage);
}


/**
 * Ids of the rows of the filtered and sorted source of the grid
 *
 * @return list<int>
 */
function gridIds(ModulIS\Datagrid\Datagrid $grid): array
{
	$selection = $grid->getFilteredSource();
	$grid->applySort($selection);

	return array_values(array_map(fn(Nette\Database\Table\ActiveRow $row): int => $row->id, $selection->fetchAll()));
}
