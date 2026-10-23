<?php

declare(strict_types = 1);

namespace ModulIS\Datagrid\Demo\Model;

use Nette\Database\Explorer;

/**
 * Tables of the demo with generated rows - created on the first request in the temp directory
 */
class DemoDatabase
{
	public const array Statuses = ['A' => 'Aktivní', 'B' => 'Zablokovaný', 'N' => 'Nový'];

	public const array Roles = ['admin' => 'Administrátor', 'sales' => 'Obchodník', 'support' => 'Podpora'];

	private const array FirstNames = ['Jan', 'Petr', 'Jana', 'Eva', 'Tomáš', 'Lucie', 'Martin', 'Tereza', 'Jakub', 'Hana', 'Karel', 'Zuzana'];

	private const array LastNames = ['Novák', 'Svoboda', 'Dvořák', 'Černý', 'Procházka', 'Kučera', 'Veselý', 'Horák', 'Němec', 'Marek'];

	private const array Insurances = ['Kooperativa', 'Allianz', 'Generali', 'ČPP', 'Direct'];


	public function __construct
	(
		private readonly Explorer $Explorer
	)
	{
	}


	public function create(): void
	{
		$connection = $this->Explorer->getConnection();

		if($connection->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'person'")->fetch())
		{
			return;
		}

		$connection->query('PRAGMA foreign_keys = ON');
		$connection->query('CREATE TABLE insurance (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
		$connection->query('
			CREATE TABLE person (
				id INTEGER PRIMARY KEY,
				firstname TEXT NOT NULL,
				lastname TEXT NOT NULL,
				email TEXT NOT NULL,
				role TEXT NOT NULL,
				status TEXT NOT NULL,
				active INTEGER NOT NULL,
				salary REAL,
				birth_date TEXT,
				created_at TEXT NOT NULL,
				note TEXT,
				insurance_id INTEGER REFERENCES insurance(id)
			)
		');

		foreach(self::Insurances as $index => $name)
		{
			$this->Explorer->table('insurance')->insert(['id' => $index + 1, 'name' => $name]);
		}

		mt_srand(42);
		$roles = array_keys(self::Roles);
		$statuses = array_keys(self::Statuses);

		for($id = 1; $id <= 137; $id++)
		{
			$firstname = self::FirstNames[mt_rand(0, count(self::FirstNames) - 1)];
			$lastname = self::LastNames[mt_rand(0, count(self::LastNames) - 1)];

			$this->Explorer->table('person')->insert([
				'id' => $id,
				'firstname' => $firstname,
				'lastname' => $lastname,
				'email' => \Nette\Utils\Strings::webalize($firstname . '.' . $lastname, '.') . $id . '@example.com',
				'role' => $roles[mt_rand(0, 2)],
				'status' => $statuses[mt_rand(0, 2)],
				'active' => mt_rand(0, 1),
				'salary' => mt_rand(250, 1200) * 100,
				'birth_date' => date('Y-m-d', mt_rand(strtotime('1960-01-01'), strtotime('2004-12-31'))),
				'created_at' => date('Y-m-d H:i:s', mt_rand(strtotime('2024-01-01'), strtotime('2026-10-01'))),
				'note' => mt_rand(0, 3) ? null : 'Poznámka k osobě ' . $id,
				'insurance_id' => mt_rand(0, 5) ?: null
			]);
		}
	}
}
