<?php

declare(strict_types = 1);

namespace ModulIS\Datagrid\Filter;

use ModulIS\Datagrid\Datagrid;
use Nette\Database\Table\Selection;
use Nette\Forms\Container;

/**
 * Range of numbers - two inputs (from, to), each of them is optional, decimal comma is accepted
 */
class RangeFilter extends Filter
{
	public function addControl(Container $container, Datagrid $grid): void
	{
		$range = $container->addContainer($this->getControlName());

		$range->addText('from', $grid->getText('from'))
			->setHtmlAttribute('placeholder', $grid->getText('from'))
			->setHtmlAttribute('inputmode', 'decimal')
			->setHtmlAttribute('data-datagrid-autosubmit', 'input');

		$range->addText('to', $grid->getText('to'))
			->setHtmlAttribute('placeholder', $grid->getText('to'))
			->setHtmlAttribute('inputmode', 'decimal')
			->setHtmlAttribute('data-datagrid-autosubmit', 'input');
	}


	/**
	 * @return array{from: ?string, to: ?string}|null
	 */
	public function normalize(mixed $value): ?array
	{
		if(!is_array($value))
		{
			return null;
		}

		$range = [
			'from' => self::normalizeNumber($value['from'] ?? null),
			'to' => self::normalizeNumber($value['to'] ?? null)
		];

		return $range['from'] === null && $range['to'] === null ? null : $range;
	}


	protected function applyCondition(Selection $selection, mixed $value): void
	{
		if($value['from'] !== null)
		{
			$this->whereColumns($selection, '%s >= ?', [$value['from']]);
		}

		if($value['to'] !== null)
		{
			$this->whereColumns($selection, '%s <= ?', [$value['to']]);
		}
	}


	private static function normalizeNumber(mixed $value): ?string
	{
		if(!is_scalar($value))
		{
			return null;
		}

		$value = str_replace([' ', "\u{a0}", ','], ['', '', '.'], trim((string) $value));

		return is_numeric($value) ? $value : null;
	}
}
