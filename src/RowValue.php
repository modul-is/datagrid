<?php

declare(strict_types = 1);

namespace ModulIS\Datagrid;

use Nette\Database\Table\ActiveRow;

/**
 * Value of a row by a column path of Nette Database:
 *
 *     'name'                 - column of the row (also columns added by select(), e.g. 'COUNT(*) AS count')
 *     'insurance.name'       - column of the referenced row ($row->ref('insurance')->name), may be chained
 *     ':car.plate'           - column of the first related row ($row->related('car')->fetch()->plate)
 */
final class RowValue
{
	public static function get(ActiveRow $row, string $path): mixed
	{
		if(!str_contains($path, '.'))
		{
			return $row[$path] ?? null;
		}

		$parts = explode('.', $path);
		$column = array_pop($parts);
		$current = $row;

		foreach($parts as $table)
		{
			$current = str_starts_with($table, ':')
				? $current->related(substr($table, 1))->fetch()
				: $current->ref($table);

			if(!$current instanceof ActiveRow)
			{
				return null;
			}
		}

		return $current[$column] ?? null;
	}
}
