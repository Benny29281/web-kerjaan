document.querySelectorAll('form[data-confirm]').forEach(form => {
  form.addEventListener('submit', event => {
    if (!window.confirm(form.dataset.confirm)) event.preventDefault();
  });
});
const statusInput = document.getElementById('status');
if (statusInput) {
  const progress = document.getElementById('progress');
  const blocker = document.getElementById('blocker-note');
  const sync = () => {
    blocker.required = statusInput.value === 'blocked';
    if (statusInput.value === 'done') progress.value = 100;
    progress.readOnly = statusInput.value === 'done';
  };
  statusInput.addEventListener('change', sync);
  sync();
}
