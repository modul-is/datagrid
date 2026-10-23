<?php

declare(strict_types = 1);

namespace ModulIS\Datagrid;

use ModulIS\Datagrid\Action\Button;
use Nette\Database\Table\ActiveRow;
use Nette\HtmlStringable;
use Nette\Utils\Html;

/**
 * Detail of a row - a button in the actions opens a row with the content under the row (loaded by AJAX)
 *
 *     $grid->setDetail(__DIR__ . '/detail.latte', ['types' => $types]);   // template gets $row and the parameters
 *     $grid->setDetail(fn(ActiveRow $row) => Html::el('p')->setText($row->note));
 */
class Detail
{
	use Button;

	protected ?string $caption = null;

	/** @var (\Closure(ActiveRow): bool)|null */
	private ?\Closure $visible = null;


	/**
	 * @param string|\Closure(ActiveRow): (string|HtmlStringable) $content template file or callback
	 * @param array<string, mixed> $parameters parameters of the template
	 */
	public function __construct
	(
		private readonly Datagrid $grid,
		private readonly string|\Closure $content,
		private array $parameters = []
	)
	{
		$this->icon = 'eye';
		$this->color = 'info';
		$this->ajax = true;
	}


	/**
	 * Detail is available only in rows the callback returns true for
	 *
	 * @param \Closure(ActiveRow): bool $visible
	 */
	public function setVisible(\Closure $visible): static
	{
		$this->visible = $visible;

		return $this;
	}


	public function isVisible(ActiveRow $row): bool
	{
		return $this->visible === null || ($this->visible)($row);
	}


	/**
	 * @param array<string, mixed> $parameters
	 */
	public function setTemplateParameters(array $parameters): static
	{
		$this->parameters = $parameters;

		return $this;
	}


	public function getTemplate(): ?string
	{
		return is_string($this->content) ? $this->content : null;
	}


	/**
	 * @return array<string, mixed>
	 */
	public function getTemplateParameters(): array
	{
		return $this->parameters;
	}


	public function render(ActiveRow $row): string|HtmlStringable
	{
		return $this->content instanceof \Closure ? ($this->content)($row) : '';
	}


	public function renderButton(ActiveRow $row, int|string $id): Html
	{
		return $this->createButton($this->grid->link('detail!', ['id' => $id]), $row)
			->setAttribute('data-datagrid-detail', (string) $id);
	}
}
