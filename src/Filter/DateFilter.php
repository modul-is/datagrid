<?php

declare(strict_types = 1);

namespace ModulIS\Datagrid\Filter;

use ModulIS\Datagrid\Datagrid;
use Nette\Database\Table\Selection;
use Nette\Forms\Container;

/**
 * One day - native date input, condition "DATE(column) = ?"
 */
class DateFilter extends Filter
{
	public function addControl(Container $container, Datagrid $grid): void
	{
		/** @var \ModulIS\Form\Container $container */
		$container->addDate($this->getControlName(), $this->label)
			->setHtmlAttribute('data-datagrid-autosubmit', 'change');
	}


	public function normalize(mixed $value): ?string
	{
		return self::normalizeDate($value);
	}


	protected function applyCondition(Selection $selection, mixed $value): void
	{
		$this->whereColumns($selection, 'DATE(%s) = ?', [$value]);
	}


	/**
	 * Date in the format Y-m-d or null
	 */
	public static function normalizeDate(mixed $value): ?string
	{
		if($value instanceof \DateTimeInterface)
		{
			return $value->format('Y-m-d');
		}

		if(!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value))
		{
			return null;
		}

		$date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

		return $date && $date->format('Y-m-d') === $value ? $value : null;
	}
}
