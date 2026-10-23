<?php

declare(strict_types = 1);

namespace ModulIS\Datagrid\Column;

use Nette\HtmlStringable;

/**
 * Yes / no as an icon - truthy value is a green check, falsy a red cross, null is empty
 */
class BoolColumn extends Column
{
	private string $iconTrue = 'check';

	private string $iconFalse = 'remove';

	private string $colorTrue = 'success';

	private string $colorFalse = 'danger';


	public function setIconTrue(string $icon, string $color = 'success'): static
	{
		$this->iconTrue = $icon;
		$this->colorTrue = $color;

		return $this;
	}


	public function setIconFalse(string $icon, string $color = 'danger'): static
	{
		$this->iconFalse = $icon;
		$this->colorFalse = $color;

		return $this;
	}


	protected function format(mixed $value): string|HtmlStringable|null
	{
		if($value === null)
		{
			return null;
		}

		$true = (bool) $value;

		return $this->icon($true ? $this->iconTrue : $this->iconFalse)
			->appendAttribute('class', 'fg-' . ($true ? $this->colorTrue : $this->colorFalse))
			->setAttribute('title', $this->grid->getText($true ? 'yes' : 'no'));
	}


	public function renderText(\Nette\Database\Table\ActiveRow $row): string
	{
		$value = $this->getValue($row);

		return $value === null ? '' : $this->grid->getText($value ? 'yes' : 'no');
	}
}
