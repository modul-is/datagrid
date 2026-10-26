<?php

declare(strict_types = 1);

namespace ModulIS\Datagrid\Filter;

use ModulIS\Datagrid\Datagrid;
use Nette\Database\Table\Selection;
use Nette\Forms\Container;

/**
 * Range of days - two date inputs (from, to), each of them is optional
 */
class DateRangeFilter extends Filter
{
	public function addControl(Container $container, Datagrid $grid): void
	{
		$range = $container->addContainer($this->getControlName());
		\assert($range instanceof \ModulIS\Form\Container);

		$range->addDate('from', $grid->getText('from'))
			->setHtmlAttribute('title', $this->label . ' – ' . $grid->getText('from'))
			->setHtmlAttribute('data-datagrid-autosubmit', 'change');

		$range->addDate('to', $grid->getText('to'))
			->setHtmlAttribute('title', $this->label . ' – ' . $grid->getText('to'))
			->setHtmlAttribute('data-datagrid-autosubmit', 'change');
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
			'from' => DateFilter::normalizeDate($value['from'] ?? null),
			'to' => DateFilter::normalizeDate($value['to'] ?? null)
		];

		return $range['from'] === null && $range['to'] === null ? null : $range;
	}


	protected function applyCondition(Selection $selection, mixed $value): void
	{
		if($value['from'] !== null)
		{
			$this->whereColumns($selection, 'DATE(%s) >= ?', [$value['from']]);
		}

		if($value['to'] !== null)
		{
			$this->whereColumns($selection, 'DATE(%s) <= ?', [$value['to']]);
		}
	}
}
