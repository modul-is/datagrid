<?php

declare(strict_types = 1);

namespace ModulIS\Datagrid\Action;

use Nette\Database\Table\ActiveRow;
use Nette\Utils\Html;

/**
 * Look of a button - icon, caption, title, color (Bootstrap 6 theme) and confirmation
 *
 * Settings marked "per row" accept a Closure(ActiveRow) as well - buttons outside of rows (toolbar, group actions)
 * do not call it and use the default (secondary color, nothing else).
 */
trait Button
{
	/** @var string|(\Closure(ActiveRow): ?string)|null */
	protected string|\Closure|null $icon = null;

	/** @var string|(\Closure(ActiveRow): ?string)|null */
	protected string|\Closure|null $title = null;

	/** @var string|(\Closure(ActiveRow): string) */
	protected string|\Closure $color = 'secondary';

	protected string $variant = 'solid';

	/** @var string|(\Closure(ActiveRow): ?string)|null */
	protected string|\Closure|null $class = null;

	/** @var array<string, mixed> */
	protected array $attributes = [];

	/** @var string|(\Closure(ActiveRow): ?string)|null */
	protected string|\Closure|null $confirm = null;

	protected ?string $confirmColumn = null;

	protected bool $ajax = false;

	protected bool $newTab = false;


	public function setCaption(?string $caption): static
	{
		$this->caption = $caption;

		return $this;
	}


	/**
	 * Icon name of the icon set of modul-is/form (per row)
	 *
	 * @param string|(\Closure(ActiveRow): ?string)|null $icon
	 */
	public function setIcon(string|\Closure|null $icon): static
	{
		$this->icon = $icon;

		return $this;
	}


	/**
	 * Tooltip (per row)
	 *
	 * @param string|(\Closure(ActiveRow): ?string)|null $title
	 */
	public function setTitle(string|\Closure|null $title): static
	{
		$this->title = $title;

		return $this;
	}


	/**
	 * Theme color - primary, secondary, success, danger, warning, info, accent... (per row)
	 *
	 * @param string|(\Closure(ActiveRow): string) $color
	 */
	public function setColor(string|\Closure $color): static
	{
		$this->color = $color;

		return $this;
	}


	/**
	 * solid (default), outline, subtle, text or link
	 */
	public function setVariant(string $variant): static
	{
		$this->variant = $variant;

		return $this;
	}


	/**
	 * Additional classes of the button (per row)
	 *
	 * @param string|(\Closure(ActiveRow): ?string)|null $class
	 */
	public function setClass(string|\Closure|null $class): static
	{
		$this->class = $class;

		return $this;
	}


	/**
	 * @param array<string, mixed> $attributes
	 */
	public function setAttributes(array $attributes): static
	{
		$this->attributes = $attributes;

		return $this;
	}


	/**
	 * Question confirmed before the action - %s is replaced by the value of $column of the row
	 *
	 *     ->setConfirm('Opravdu smazat uživatele %s?', 'name')
	 *     ->setConfirm(fn(ActiveRow $row) => 'Smazat ' . $row->name . '?')
	 *
	 * @param string|(\Closure(ActiveRow): ?string)|null $question
	 */
	public function setConfirm(string|\Closure|null $question, ?string $column = null): static
	{
		$this->confirm = $question;
		$this->confirmColumn = $column;

		return $this;
	}


	/**
	 * The link is requested by AJAX (naja)
	 */
	public function setAjax(bool $ajax = true): static
	{
		$this->ajax = $ajax;

		return $this;
	}


	public function setNewTab(bool $newTab = true): static
	{
		$this->newTab = $newTab;

		return $this;
	}


	/**
	 * Html of the button - <a> with $href, or <button> when $href is null
	 */
	protected function createButton(?string $href, ?ActiveRow $row, string $size = 'sm'): Html
	{
		$button = $href === null ? Html::el('button')->type('button') : Html::el('a')->href($href);

		$button->class(implode(' ', array_filter([
			'btn-' . $this->variant,
			'theme-' . $this->resolve($this->color, $row, 'secondary'),
			$size !== '' ? 'btn-' . $size : null,
			$this->resolve($this->class, $row),
			$this->ajax ? 'ajax' : null
		])));

		foreach($this->attributes as $name => $value)
		{
			$button->setAttribute($name, $value);
		}

		$title = $this->resolve($this->title, $row);

		if($title !== null)
		{
			$button->setAttribute('title', $title)
				->setAttribute('aria-label', $title);
		}

		if($this->newTab)
		{
			$button->setAttribute('target', '_blank');
		}

		$confirm = $this->resolve($this->confirm, $row);

		if($confirm !== null)
		{
			if($this->confirmColumn !== null && $row !== null)
			{
				$confirm = sprintf($confirm, (string) \ModulIS\Datagrid\RowValue::get($row, $this->confirmColumn));
			}

			$button->setAttribute('data-datagrid-confirm', $confirm);
		}

		$icon = $this->resolve($this->icon, $row);

		if($icon !== null && $icon !== '')
		{
			$button->addHtml(\ModulIS\Form\Renderer\Bootstrap6Renderer::getDefaultIconRenderer()->render($icon));
		}

		if($this->caption !== null && $this->caption !== '')
		{
			$button->addHtml(Html::el('span')->setText($this->caption));
		}

		return $button;
	}


	/**
	 * Setting for the row - a Closure is called only with a row, otherwise $default is used
	 *
	 * @template T
	 * @param T|(\Closure(ActiveRow): T) $value
	 * @param T $default
	 * @return T
	 */
	protected function resolve(mixed $value, ?ActiveRow $row, mixed $default = null): mixed
	{
		if($value instanceof \Closure)
		{
			return $row === null ? $default : $value($row);
		}

		return $value;
	}
}
