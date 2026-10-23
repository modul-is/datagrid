<?php

declare(strict_types = 1);

namespace ModulIS\Datagrid\Export;

use ModulIS\Datagrid\Column\Column;
use ModulIS\Datagrid\Datagrid;
use Nette\Application\Responses\CallbackResponse;
use Nette\Database\Table\Selection;
use Nette\Http\IRequest;
use Nette\Http\IResponse;

/**
 * CSV of the columns of the grid - texts of the cells as they are rendered (without HTML)
 */
class CsvExport extends Export
{
	/** @var list<string>|null */
	private ?array $columns = null;


	public function __construct
	(
		Datagrid $grid,
		string $key,
		?string $caption,
		private readonly string $fileName,
		bool $filtered,
		private readonly string $encoding = 'UTF-8',
		private readonly string $delimiter = ';',
		private readonly bool $bom = true
	)
	{
		parent::__construct($grid, $key, $caption, $this->send(...), $filtered);
	}


	/**
	 * Keys of the exported columns in the order of the file - default all columns of the grid
	 *
	 * @param list<string> $columns
	 */
	public function setColumns(array $columns): static
	{
		$this->columns = $columns;

		return $this;
	}


	private function send(Selection $selection, Datagrid $grid): void
	{
		$columns = $grid->getColumns();

		if($this->columns !== null)
		{
			$columns = array_map(fn(string $key): Column => $columns[$key] ?? throw new \Nette\InvalidArgumentException("Column '$key' of the export does not exist."), $this->columns);
		}

		$grid->getPresenter()->sendResponse(new CallbackResponse(function(IRequest $request, IResponse $response) use ($selection, $columns): void
		{
			$response->setContentType('text/csv', $this->encoding);
			$response->setHeader('Content-Disposition', 'attachment; filename="' . $this->fileName . '"; filename*=utf-8\'\'' . rawurlencode($this->fileName));

			$output = fopen('php://output', 'w') ?: throw new \RuntimeException('Output of the CSV export cannot be opened.');

			if($this->bom && strtoupper($this->encoding) === 'UTF-8')
			{
				fwrite($output, "\u{feff}");
			}

			$this->writeLine($output, array_values(array_map(fn(Column $column): string => $column->getLabel(), $columns)));

			foreach($selection as $row)
			{
				$this->writeLine($output, array_values(array_map(fn(Column $column): string => $column->renderText($row), $columns)));
			}

			fclose($output);
		}));
	}


	/**
	 * @param resource $output
	 * @param list<string> $values
	 */
	private function writeLine($output, array $values): void
	{
		if(strtoupper($this->encoding) !== 'UTF-8')
		{
			$values = array_map(fn(string $value): string => (string) iconv('UTF-8', $this->encoding . '//TRANSLIT', $value), $values);
		}

		fputcsv($output, $values, $this->delimiter, '"', '');
	}
}
