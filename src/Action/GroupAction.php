<?php

declare(strict_types = 1);

namespace ModulIS\Datagrid\Action;

use Nette\Utils\Html;

/**
 * Action with the checked rows - a button, or a menu of options when the action has them
 *
 *     $grid->addGroupAction('Smazat')->setColor('danger')->setConfirm('Smazat vybrané?')
 *         ->onSelect[] = fn(array $ids) => ...;
 *     $grid->addGroupAction('Přesunout do kategorie', $categoryPairs)
 *         ->onSelect[] = fn(array $ids, int|string $categoryId) => ...;
 */
class GroupAction
{
	use Button;

	/** @var array<\Closure(list<string> $ids, int|string|null $value): void> */
	public array $onSelect = [];


	/**
	 * @param array<int|string, string>|null $options
	 */
	public function __construct
	(
		private readonly string $key,
		protected ?string $caption,
		private readonly ?array $options
	)
	{
	}


	public function getKey(): string
	{
		return $this->key;
	}


	/**
	 * @return array<int|string, string>|null
	 */
	public function getOptions(): ?array
	{
		return $this->options;
	}


	/**
	 * Option chosen by the submitted value, null for an action without options - false when the option is unknown
	 */
	public function findOption(?string $value): int|string|false|null
	{
		if($this->options === null)
		{
			return null;
		}

		foreach(array_keys($this->options) as $option)
		{
			if((string) $option === $value)
			{
				return $option;
			}
		}

		return false;
	}


	/**
	 * @param list<string> $ids
	 */
	public function select(array $ids, int|string|null $option): void
	{
		foreach($this->onSelect as $callback)
		{
			$callback($ids, $option);
		}
	}


	/**
	 * Submit button(s) of the group form - value "key" or "key|option"
	 */
	public function render(string $formId): Html
	{
		if($this->options === null)
		{
			return $this->createButton(null, null)
				->type('submit')
				->name('groupAction')
				->value($this->key)
				->setAttribute('form', $formId)
				->setAttribute('data-datagrid-group-button', true)
				->disabled(true);
		}

		$toggle = $this->createButton(null, null)
			->setAttribute('data-bs-toggle', 'menu')
			->setAttribute('aria-expanded', 'false')
			->setAttribute('data-datagrid-group-button', true)
			->disabled(true);

		$menu = Html::el('div')->class('menu');

		foreach($this->options as $option => $text)
		{
			$item = Html::el('button')
				->type('submit')
				->class('menu-item')
				->name('groupAction')
				->value($this->key . '|' . $option)
				->setAttribute('form', $formId)
				->setText($text);

			$confirm = $this->resolve($this->confirm, null);

			if($confirm !== null)
			{
				$item->setAttribute('data-datagrid-confirm', $confirm);
			}

			$menu->addHtml($item);
		}

		return Html::el('div')->class('d-inline-block')
			->addHtml($toggle->removeAttribute('data-datagrid-confirm'))
			->addHtml($menu);
	}
}
