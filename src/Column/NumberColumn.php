<?php

declare(strict_types = 1);

namespace ModulIS\Datagrid\Column;

use Nette\Database\Table\ActiveRow;
use Nette\HtmlStringable;

/**
 * Number formatted by number_format() - default without decimals, space as the thousands separator
 */
class NumberColumn extends Column
{
	private int $decimals = 0;

	private string $decimalSeparator = ',';

	private string $thousandsSeparator = "\u{a0}";


	public function setFormat(int $decimals = 0, string $decimalSeparator = ',', string $thousandsSeparator = "\u{a0}"): static
	{
		$this->decimals = $decimals;
		$this->decimalSeparator = $decimalSeparator;
		$this->thousandsSeparator = $thousandsSeparator;

		return $this;
	}


	/**
	 * Number without the thousands separator in the CSV export - a spreadsheet reads it as a number, not as a text
	 */
	public function renderText(ActiveRow $row): string
	{
		$value = $this->getValue($row);

		if($this->renderer || !is_numeric($value))
		{
			return parent::renderText($row);
		}

		return number_format((float) $value, $this->decimals, $this->decimalSeparator, '');
	}


	protected function format(mixed $value): string|HtmlStringable|null
	{
		if(!is_numeric($value))
		{
			return parent::format($value);
		}

		return number_format((float) $value, $this->decimals, $this->decimalSeparator, $this->thousandsSeparator);
	}
}
