/**
 * Client side of modul-is/datagrid - filters submitted while typing, confirmation of actions, checkboxes
 * of group actions, detail of a row, editable cells, collapsible outer filters and settings of the columns.
 *
 *     import { initDatagrid } from '@modul-is/datagrid';
 *     import '@modul-is/datagrid/css';
 *
 *     initDatagrid(naja);            // before naja.initialize(), after initForms(naja) of modul-is/form
 *
 * All listeners are on the document - grids redrawn by snippets need no initialization.
 */

const InputDelay = 500;

let naja = null;
let confirmDialog = null;

/**
 * Filter being typed into - its focus and value survive the redraw of the grid
 */
let typing = null;

/**
 * Collapsible outer filters shown or hidden by the user - id => shown, survives the redraw of the grid
 */
const outerFilters = new Map();


export function initDatagrid(najaInstance)
{
	naja = najaInstance;

	document.addEventListener('click', onConfirmClick, true);
	document.addEventListener('click', onDetailClick, true);
	document.addEventListener('click', onOuterFiltersClick);
	document.addEventListener('click', onEditClick);
	document.addEventListener('input', onFilterInput);
	document.addEventListener('change', onFilterChange);
	document.addEventListener('keydown', onFilterKeydown);
	document.addEventListener('change', onCheckChange);
	document.addEventListener('click', onColumnsClick);
	document.addEventListener('submit', onColumnsSubmit, true);
	document.addEventListener('dragstart', onColumnDragStart);
	document.addEventListener('dragover', onColumnDragOver);
	document.addEventListener('dragend', onColumnDragEnd);

	naja.snippetHandler.addEventListener('afterUpdate', onAfterUpdate);
}


/************************************************************************************************************
 * Filters
 ************************************************************************************************************/


function onFilterInput(event)
{
	const input = event.target.closest?.('[data-datagrid-autosubmit="input"]');

	if (!input)
	{
		return;
	}

	clearTimeout(typing?.timer);

	typing = {
		id: input.id,
		value: input.value,
		submitted: typing?.id === input.id ? typing.submitted : null,
		timer: setTimeout(() => submitFilter(input), InputDelay),
	};
}


function onFilterChange(event)
{
	const control = event.target.closest?.('[data-datagrid-autosubmit="change"]');

	if (control)
	{
		submitFilter(control);
	}
}


function onFilterKeydown(event)
{
	const input = event.target.closest?.('[data-datagrid-autosubmit="input"]');

	if (input && event.key === 'Enter')
	{
		event.preventDefault();
		clearTimeout(typing?.timer);
		submitFilter(input);
	}
}


function submitFilter(control)
{
	const form = control.form;

	if (!form)
	{
		return;
	}

	if (typing?.id === control.id)
	{
		typing.submitted = control.value;
		typing.selection = [control.selectionStart, control.selectionEnd];
	}

	form.requestSubmit();
}


/**
 * Focus back to the filter being typed into - text typed during the request is kept and submitted again
 */
function restoreTyping()
{
	if (!typing)
	{
		return;
	}

	const input = document.getElementById(typing.id);

	if (!input || document.activeElement === input)
	{
		return;
	}

	const typed = typing.value;

	input.focus();

	if (typed !== input.value)
	{
		input.value = typed;
		typing.timer = setTimeout(() => submitFilter(input), InputDelay);
	}
	else
	{
		typing = null;
	}

	try
	{
		input.setSelectionRange(input.value.length, input.value.length);
	}
	catch
	{
		// inputs without selection (number, date)
	}
}


/************************************************************************************************************
 * Collapsible outer filters - [data-datagrid-outer-toggle] shows / hides the filters above the table
 ************************************************************************************************************/


function onOuterFiltersClick(event)
{
	const button = event.target.closest?.('[data-datagrid-outer-toggle]');
	const filters = button && document.getElementById(button.getAttribute('aria-controls'));

	if (!filters)
	{
		return;
	}

	outerFilters.set(filters.id, filters.hidden);
	showOuterFilters(button, filters, filters.hidden);
}


function showOuterFilters(button, filters, shown)
{
	filters.hidden = !shown;
	button.classList.toggle('active', shown);
	button.setAttribute('aria-expanded', String(shown));
}


/**
 * Filters shown by the user stay shown after the redraw, hidden ones are shown again only by an active filter
 */
function restoreOuterFilters(grid)
{
	for (const button of grid.querySelectorAll('[data-datagrid-outer-toggle]'))
	{
		const filters = document.getElementById(button.getAttribute('aria-controls'));

		if (filters && outerFilters.get(filters.id) === true)
		{
			showOuterFilters(button, filters, true);
		}
	}
}


/************************************************************************************************************
 * Confirmation - [data-datagrid-confirm] on links and buttons
 ************************************************************************************************************/


function onConfirmClick(event)
{
	const element = event.target.closest?.('[data-datagrid-confirm]');

	if (!element || !element.dataset.datagridConfirm || element.dataset.datagridConfirmed)
	{
		return;
	}

	event.preventDefault();
	event.stopImmediatePropagation();

	confirmAction(element.dataset.datagridConfirm, element.closest('[data-datagrid]')).then((confirmed) =>
	{
		if (!confirmed)
		{
			return;
		}

		element.dataset.datagridConfirmed = '1';
		element.click();
		delete element.dataset.datagridConfirmed;
	});
}


/**
 * Bootstrap 6 dialog with the question - resolves true when confirmed
 */
function confirmAction(question, grid)
{
	const texts = JSON.parse(grid?.dataset.datagridTexts || '{}');

	confirmDialog ??= createConfirmDialog();
	confirmDialog.querySelector('.dialog-title').textContent = texts.confirm || 'Potvrzení';
	confirmDialog.querySelector('.dialog-body').textContent = question;
	confirmDialog.querySelector('[value="ok"]').textContent = texts.ok || 'Ano';
	confirmDialog.querySelector('[value="cancel"]').textContent = texts.cancel || 'Zrušit';
	confirmDialog.returnValue = '';
	confirmDialog.showModal();
	confirmDialog.querySelector('[value="ok"]').focus();

	return new Promise((resolve) =>
	{
		confirmDialog.addEventListener('close', () => resolve(confirmDialog.returnValue === 'ok'), { once: true });
	});
}


function createConfirmDialog()
{
	const dialog = document.createElement('dialog');

	dialog.className = 'dialog datagrid-confirm';
	dialog.setAttribute('aria-labelledby', 'datagrid-confirm-title');
	dialog.innerHTML = `
		<form method="dialog">
			<div class="dialog-header">
				<h2 class="dialog-title fs-lg" id="datagrid-confirm-title"></h2>
			</div>
			<div class="dialog-body"></div>
			<div class="dialog-footer d-flex justify-content-end gap-2">
				<button type="submit" value="cancel" class="btn-outline theme-secondary"></button>
				<button type="submit" value="ok" class="btn-solid theme-primary"></button>
			</div>
		</form>`;

	// click on the backdrop closes the dialog
	dialog.addEventListener('click', (event) =>
	{
		if (event.target === dialog)
		{
			dialog.close('cancel');
		}
	});

	document.body.append(dialog);

	return dialog;
}


/************************************************************************************************************
 * Group actions - checkboxes of the rows, buttons enabled with checked rows
 ************************************************************************************************************/


function onCheckChange(event)
{
	const grid = event.target.closest?.('[data-datagrid]');

	if (!grid)
	{
		return;
	}

	if (event.target.matches('[data-datagrid-check-all]'))
	{
		for (const check of grid.querySelectorAll('[data-datagrid-check]'))
		{
			check.checked = event.target.checked;
		}
	}

	if (event.target.matches('[data-datagrid-check], [data-datagrid-check-all]'))
	{
		refreshChecks(grid);
	}
}


function refreshChecks(grid)
{
	const checks = [...grid.querySelectorAll('[data-datagrid-check]')];
	const checked = checks.filter((check) => check.checked).length;
	const all = grid.querySelector('[data-datagrid-check-all]');

	if (all)
	{
		all.checked = checks.length > 0 && checked === checks.length;
		all.indeterminate = checked > 0 && checked < checks.length;
	}

	for (const button of grid.querySelectorAll('[data-datagrid-group-button]'))
	{
		button.disabled = checked === 0;
	}

	const counter = grid.querySelector('[data-datagrid-selected]');

	if (counter)
	{
		counter.textContent = String(checked);
	}
}


/************************************************************************************************************
 * Detail of a row - loaded once by AJAX, then only shown / hidden
 ************************************************************************************************************/


function onDetailClick(event)
{
	const button = event.target.closest?.('[data-datagrid-detail]');
	const grid = button?.closest('[data-datagrid]');
	const row = grid?.querySelector(`[data-datagrid-detail-row="${ CSS.escape(button.dataset.datagridDetail) }"]`);

	if (!row)
	{
		return;
	}

	event.preventDefault();
	event.stopImmediatePropagation();

	row.hidden = !row.hidden;
	button.classList.toggle('active', !row.hidden);
}


/************************************************************************************************************
 * Editable cells - td[data-datagrid-edit] with the URL of the signal, the value is sent by POST
 ************************************************************************************************************/


function onEditClick(event)
{
	const cell = event.target.closest?.('td[data-datagrid-edit]');

	if (!cell || cell.querySelector('[data-datagrid-editor]') || event.target.closest('a, button, input, select, textarea'))
	{
		return;
	}

	const type = cell.dataset.datagridEditType || 'text';
	const value = cell.dataset.datagridEditValue ?? '';
	const editor = createEditor(type, value, JSON.parse(cell.dataset.datagridEditItems || 'null'));
	const content = [...cell.childNodes];

	editor.dataset.datagridEditor = '';
	cell.replaceChildren(editor);
	editor.focus();

	let done = false;

	const finish = (save) =>
	{
		if (done)
		{
			return;
		}

		done = true;

		if (!save || editor.value === value)
		{
			cell.replaceChildren(...content);

			return;
		}

		editor.disabled = true;
		cell.classList.add('datagrid-saving');

		naja.makeRequest('POST', cell.dataset.datagridEdit, { value: editor.value }, { history: false })
			.catch(() =>
			{
				cell.classList.remove('datagrid-saving');
				cell.replaceChildren(...content);
			});
	};

	editor.addEventListener('keydown', (keyEvent) =>
	{
		if (keyEvent.key === 'Escape')
		{
			finish(false);
		}
		else if (keyEvent.key === 'Enter' && type !== 'textarea')
		{
			keyEvent.preventDefault();
			finish(true);
		}
	});

	editor.addEventListener('blur', () => finish(true));

	if (type === 'select')
	{
		editor.addEventListener('change', () => finish(true));
	}
}


function createEditor(type, value, items)
{
	let editor;

	if (type === 'select')
	{
		editor = document.createElement('select');
		editor.className = 'form-control form-control-sm';

		for (const [key, text] of Object.entries(items || {}))
		{
			editor.add(new Option(text, key, false, key === value));
		}
	}
	else if (type === 'textarea')
	{
		editor = document.createElement('textarea');
		editor.className = 'form-control form-control-sm';
		editor.rows = 3;
	}
	else
	{
		editor = document.createElement('input');
		editor.type = type;
		editor.className = 'form-control form-control-sm';
	}

	editor.value = value;

	return editor;
}


/************************************************************************************************************
 * Settings of the columns - dialog [data-datagrid-columns] opened natively (no state of the Bootstrap dialog
 * is left behind when the grid is redrawn), order changed by arrows or drag and drop
 ************************************************************************************************************/


let draggedColumn = null;


function onColumnsClick(event)
{
	const open = event.target.closest?.('[data-datagrid-columns-open]');

	if (open)
	{
		const dialog = open.closest('[data-datagrid]')?.querySelector('[data-datagrid-columns]');

		if (dialog && !dialog.open)
		{
			openColumnsDialog(dialog);
		}

		return;
	}

	const dialog = event.target.closest?.('[data-datagrid-columns]');

	if (!dialog)
	{
		return;
	}

	// click on the backdrop or on a closing button
	if (event.target === dialog || event.target.closest('[data-datagrid-columns-close]'))
	{
		dialog.close();

		return;
	}

	const move = event.target.closest('[data-datagrid-column-move]');
	const item = move?.closest('[data-datagrid-column]');

	if (!item)
	{
		return;
	}

	if (move.dataset.datagridColumnMove === 'up' && item.previousElementSibling)
	{
		item.previousElementSibling.before(item);
	}
	else if (move.dataset.datagridColumnMove === 'down' && item.nextElementSibling)
	{
		item.nextElementSibling.after(item);
	}

	move.focus();
}


/**
 * Closed without saving - the list returns to the rendered settings (checkboxes by their checked attribute)
 */
function openColumnsDialog(dialog)
{
	const list = dialog.querySelector('ul');
	const original = list.innerHTML;

	dialog.dataset.datagridColumnsSaved = '';
	dialog.addEventListener('close', () =>
	{
		if (!dialog.dataset.datagridColumnsSaved)
		{
			list.innerHTML = original;
		}
	}, { once: true });

	dialog.showModal();
}


/**
 * At least one column stays visible - the dialog is closed before the grid is redrawn by the response
 */
function onColumnsSubmit(event)
{
	const form = event.target.closest?.('[data-datagrid-columns-form]');

	if (!form)
	{
		return;
	}

	if (!form.querySelector('input[name="visible[]"]:checked'))
	{
		event.preventDefault();
		event.stopImmediatePropagation();

		return;
	}

	const dialog = form.closest('dialog');

	if (dialog)
	{
		dialog.dataset.datagridColumnsSaved = '1';
		dialog.close();
	}
}


function onColumnDragStart(event)
{
	draggedColumn = event.target.closest?.('[data-datagrid-column]') ?? null;

	if (draggedColumn)
	{
		event.dataTransfer.effectAllowed = 'move';
		draggedColumn.classList.add('is-dragging');
	}
}


function onColumnDragOver(event)
{
	const target = event.target.closest?.('[data-datagrid-column]');

	if (!draggedColumn || !target || target === draggedColumn || target.parentElement !== draggedColumn.parentElement)
	{
		return;
	}

	event.preventDefault();

	const box = target.getBoundingClientRect();

	if (event.clientY < box.top + box.height / 2)
	{
		target.before(draggedColumn);
	}
	else
	{
		target.after(draggedColumn);
	}
}


function onColumnDragEnd()
{
	draggedColumn?.classList.remove('is-dragging');
	draggedColumn = null;
}


/************************************************************************************************************
 * After a redraw
 ************************************************************************************************************/


function onAfterUpdate(event)
{
	const grid = event.detail.snippet.closest('[data-datagrid]') ?? event.detail.snippet.querySelector('[data-datagrid]');

	if (grid)
	{
		refreshChecks(grid);
		restoreOuterFilters(grid);
	}

	restoreTyping();
}
