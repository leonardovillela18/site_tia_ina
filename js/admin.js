'use strict';

/* Confirmação de ações destrutivas */
document.querySelectorAll('.inline-confirm').forEach((form) => form.addEventListener('submit', (event) => {
  const button = form.querySelector('button[data-confirm]');
  if (button && !window.confirm(button.dataset.confirm)) event.preventDefault();
}));

/* Envio automático de filtros/status */
document.querySelectorAll('[data-auto-submit]').forEach((field) => {
  field.addEventListener('change', () => field.form?.submit());
});

/* Fecha mensagens de feedback depois de alguns segundos */
const flash = document.querySelector('.flash');
if (flash) {
  setTimeout(() => { flash.hidden = true; }, 6000);
}
