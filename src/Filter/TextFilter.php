<?php

declare(strict_types = 1);

namespace ModulIS\Datagrid\Filter;

use ModulIS\Datagrid\Datagrid;
use Nette\Database\Table\Selection;
use Nette\Forms\Container;

/**
 * Text search - every word has to be found in one of the columns (LIKE %word%)
 */
class TextFilter extends Filter
{
	private bool $exact = false;

	private bool $splitWords = true;


	/**
	 * Whole value has to equal one of the columns
	 */
	public function setExact(bool $exact = true): static
	{
		$this->exact = $exact;

		return $this;
	}


	/**
	 * false - the value is searched as one phrase, not word by word
	 */
	public function setSplitWords(bool $splitWords = true): static
	{
		$this->splitWords = $splitWords;

		return $this;
	}


	public function addControl(Container $container, Datagrid $grid): void
	{
		$container->addText($this->key, $this->label)
			->setHtmlAttribute('placeholder', $this->placeholder ?? $grid->getText('search'))
			->setHtmlAttribute('data-datagrid-autosubmit', 'input');
	}


	public function normalize(mixed $value): ?string
	{
		if(!is_scalar($value))
		{
			return null;
		}

		$value = trim((string) $value);

		return $value === '' ? null : $value;
	}


	protected function applyCondition(Selection $selection, mixed $value): void
	{
		if($this->exact)
		{
			$this->whereColumns($selection, '%s = ?', [$value]);

			return;
		}

		$words = $this->splitWords ? (preg_split('/\s+/u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [$value]) : [$value];

		/**
		 * Wildcards of the value are searched literally - "!" as the escape character works in MySQL and SQLite the same
		 */
		foreach($words as $word)
		{
			$this->whereColumns($selection, "%s LIKE ? ESCAPE '!'", ['%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $word) . '%']);
		}
	}
}
