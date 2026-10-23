<?php

declare(strict_types = 1);

namespace ModulIS\Datagrid\Action;

use ModulIS\Datagrid\Datagrid;
use Nette\Utils\Html;

/**
 * Button above the grid - destination like Action, parameters are fixed values
 */
class ToolbarButton
{
	use Button;

	/**
	 * @param array<string, mixed> $parameters
	 */
	public function __construct
	(
		private readonly Datagrid $grid,
		private readonly string $destination,
		protected ?string $caption,
		private readonly array $parameters
	)
	{
	}


	public function render(): Html
	{
		return $this->createButton($this->grid->createLink($this->destination, $this->parameters), null);
	}
}
