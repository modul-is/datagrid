import 'bootstrap/dist/css/bootstrap.css';
import 'bootstrap-icons/font/bootstrap-icons.css';
import '../../vendor/modul-is/form/assets/css/form.css';
import '../../assets/css/datagrid.css';

// all Bootstrap plugins register their data-API on import
import 'bootstrap';
import naja from 'naja';
import netteForms from 'nette-forms';
import { initForms } from '../../vendor/modul-is/form/assets/js/index.js';
import { initDatagrid } from '../../assets/js/index.js';

window.naja = naja;

netteForms.initOnLoad();
naja.formsHandler.netteForms = netteForms;

initForms(naja);
initDatagrid(naja);

document.addEventListener('DOMContentLoaded', () => naja.initialize({ history: false }));
