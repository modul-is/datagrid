<?php

declare(strict_types = 1);

namespace ModulIS\Datagrid\Demo\Presenter;

use ModulIS\Datagrid\Datagrid;
use ModulIS\Datagrid\Demo\Model\DemoDatabase;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Utils\Html;

/**
 * Grid showing all features of the library over the demo database
 */
class DemoPresenter extends \Nette\Application\UI\Presenter
{
	public function __construct
	(
		private readonly Explorer $Explorer,
		private readonly DemoDatabase $DemoDatabase
	)
	{
		parent::__construct();
	}


	public function startup(): void
	{
		parent::startup();

		$this->DemoDatabase->create();
	}


	public function handleDelete(int $id): void
	{
		$this->flashMessage("Osoba $id by byla smazána (demo nic nemaže).", 'warning');
		$this->redrawControl('flashMessages');

		if(!$this->isAjax())
		{
			$this->redirect('this');
		}
	}


	protected function createComponentGrid(): Datagrid
	{
		$insurances = $this->Explorer->table('insurance')->fetchPairs('id', 'name');

		$grid = new Datagrid($this->Explorer->table('person'));
		$grid->setDefaultSort(['lastname' => 'ASC']);
		$grid->setDefaultFilter(['active' => 1]);

		$grid->addNumber('id', 'ID')
			->setWidth(5)
			->setSortable()
			->setFilterRange();

		$grid->addText('name', 'Jméno', 'lastname')
			->setAlign('start')
			->setRenderer(fn(ActiveRow $row): Html => Html::el('strong')->setText($row->lastname . ' ' . $row->firstname))
			->setSortable()
			->setFilterText(['lastname', 'firstname', 'email']);

		$grid->addText('email', 'E-mail')
			->setAlign('start')
			->setSortable();

		$grid->addDial('role', 'Role', DemoDatabase::Roles)
			->setSortable()
			->setFilterMultiSelect(DemoDatabase::Roles);

		$grid->addText('insurance.name', 'Pojišťovna')
			->setSortable()
			->setFilterSelect($insurances, 'insurance_id')
				->setSearch();

		/**
		 * Column over three tables (person -> insurance -> company) - the key is the path, sorting and filtering use it too
		 */
		$grid->addText('insurance.company.name', 'Skupina')
			->setSortable()
			->setFilterText();

		$status = $grid->addStatus('status', 'Stav', DemoDatabase::Statuses)
			->setColors(['A' => 'success', 'B' => 'danger', 'N' => 'info'])
			->setConfirm('Změnit stav na „%s“?')
			->setSortable();

		$status->setFilterSelect(DemoDatabase::Statuses);

		$status->onChange[] = function(int|string $id, string $value): void
		{
			$this->Explorer->table('person')->where('id', $id)->update(['status' => $value]);
		};

		$grid->addBool('active', 'Aktivní')
			->setSortable()
			->setFilterSelect([1 => 'Ano', 0 => 'Ne']);

		$grid->addNumber('salary', 'Plat')
			->setFormat(0)
			->setAlign('end')
			->setSortable()
			->setFilterRange();

		$grid->addDateTime('created_at', 'Vytvořeno')
			->setFormat('j. n. Y H:i')
			->setSortable()
			->setFilterDateRange();

		$grid->addText('note', 'Poznámka')
			->setWidth(15)
			->setEditable(function(int|string $id, string $value): void
			{
				$this->Explorer->table('person')->where('id', $id)->update(['note' => $value === '' ? null : $value]);
			}, 'textarea');

		$grid->addAction('delete!')
			->setIcon('trash')
			->setTitle('Smazat')
			->setColor('danger')
			->setAjax()
			->setConfirm('Opravdu smazat osobu %s?', 'lastname')
			->setVisible(fn(ActiveRow $row): bool => $row->status !== 'A');

		$grid->addCallbackAction('activate')
			->setIcon(fn(ActiveRow $row): string => $row->active ? 'toggle-on' : 'toggle-off')
			->setTitle('Přepnout aktivitu')
			->setColor(fn(ActiveRow $row): string => $row->active ? 'success' : 'secondary')
			->setAjax()
			->onClick[] = function(int|string $id): void
			{
				$row = $this->Explorer->table('person')->get($id);
				$row?->update(['active' => $row->active ? 0 : 1]);
			};

		$grid->setDetail(fn(ActiveRow $row): Html => Html::el('div')
			->addHtml(Html::el('strong')->setText('Datum narození: '))
			->addText($row->birth_date ?? '–')
			->addHtml(Html::el('br'))
			->addHtml(Html::el('strong')->setText('Pojišťovna: '))
			->addText($row->insurance->name ?? '–'));

		$grid->addGroupAction('Aktivovat')
			->setColor('success')
			->onSelect[] = function(array $ids): void
			{
				$this->Explorer->table('person')->where('id', $ids)->update(['active' => 1]);
				$this->flashMessage('Aktivováno: ' . count($ids));
				$this->redrawControl('flashMessages');
			};

		$grid->addGroupAction('Nastavit roli', DemoDatabase::Roles)
			->setConfirm('Opravdu změnit roli vybraným osobám?')
			->onSelect[] = function(array $ids, int|string|null $role): void
			{
				$this->Explorer->table('person')->where('id', $ids)->update(['role' => $role]);
			};

		$grid->addToolbarButton('this', 'Obnovit')
			->setIcon('arrow-clockwise')
			->setVariant('outline');

		$grid->addExportCsv('CSV', 'osoby.csv', filtered: true);

		$grid->addExport('Počet (callback)', function(Selection $selection): void
		{
			$this->sendJson(['count' => $selection->count('*')]);
		}, filtered: true);

		$grid->setRowCallback(function(ActiveRow $row, Html $tr): void
		{
			if($row->status === 'B')
			{
				$tr->class('opacity-75');
			}
		});

		return $grid;
	}


	protected function createComponentOuterFilterGrid(): Datagrid
	{
		$grid = new Datagrid($this->Explorer->table('person'));
		$grid->setDefaultSort(['lastname' => 'ASC']);
		$grid->setPerPageList([5, 10, 25]);
		$grid->setOuterFilters();
		$grid->setOuterFilterColumns(3);
		$grid->setCollapsibleOuterFilters();

		$grid->addText('lastname', 'Příjmení')
			->setSortable()
			->setFilterText(['lastname', 'firstname', 'email']);

		$grid->addDial('role', 'Role', DemoDatabase::Roles)
			->setFilterMultiSelect(DemoDatabase::Roles);

		$grid->addText('insurance', 'Pojišťovna', 'insurance.name')
			->setFilterSelect($this->Explorer->table('insurance')->fetchPairs('id', 'name'), 'insurance_id');

		$grid->addNumber('salary', 'Plat')
			->setFormat(0)
			->setAlign('end')
			->setFilterRange();

		$grid->addDateTime('created_at', 'Vytvořeno')
			->setFormat('j. n. Y')
			->setFilterDateRange();

		return $grid;
	}
}
