<?php

declare(strict_types = 1);

namespace ModulIS\Datagrid\Column;

use Nette\HtmlStringable;

/**
 * Date and time - DateTimeInterface or a string accepted by DateTimeImmutable, other values are shown as they are
 */
class DateTimeColumn extends Column
{
	private string $format = 'j. n. Y';


	/**
	 * Format of date() - e.g. 'j. n. Y H:i', 'd.m.Y H:i:s'
	 */
	public function setFormat(string $format): static
	{
		$this->format = $format;

		return $this;
	}


	protected function format(mixed $value): string|HtmlStringable|null
	{
		if(is_string($value) && $value !== '')
		{
			try
			{
				$value = new \DateTimeImmutable($value);
			}
			catch(\Exception)
			{
				return $value;
			}
		}

		return $value instanceof \DateTimeInterface ? $value->format($this->format) : parent::format($value);
	}
}
