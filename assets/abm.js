'use strict';
(() => {
  const shell = document.querySelector('.editor-shell');
  if (!shell || !window.HTMLDialogElement) return;
  const form = shell.querySelector('#piece-form');
  const dialog = document.createElement('dialog');
  dialog.className = 'abm-dialog'; dialog.setAttribute('aria-labelledby', 'editor-title');
  shell.parentNode.insertBefore(dialog, shell); dialog.appendChild(shell);
  const background = document.querySelector('.editor-background');
  if (background) background.removeAttribute('aria-hidden');
  const notice = document.querySelector('main > .notice.success');
  if (notice) shell.insertBefore(notice, shell.firstChild);
  const returnUrl = shell.dataset.returnUrl;
  let current = 0;
  let steps = [];
  let navigation;
  let selectStep = () => {};
  const cancel = () => {
    if (window.ProdigoForm?.isDirty() && !window.confirm('¿Cancelar la carga y descartar los cambios sin guardar?')) return;
    window.ProdigoForm?.submitting();
    dialog.close();
    window.location.assign(returnUrl);
  };
  shell.querySelector('[data-editor-cancel]').addEventListener('click', cancel);
  if (!form) {
    dialog.addEventListener('cancel', event => { event.preventDefault(); cancel(); });
    dialog.showModal(); return;
  }
  form.noValidate = true;
  const marker = document.createElement('input'); marker.type = 'hidden'; marker.name = '_wizard'; marker.value = '1'; form.appendChild(marker);
  const identity = form.querySelector('[data-step="identity"]');
  const categoryContainer = form.querySelector('[data-category-container]');
  const materialSteps = [...categoryContainer.querySelectorAll('[data-fields-category]')];
  const removalConfirmation = categoryContainer.querySelector('label.check');
  if (removalConfirmation) identity.appendChild(removalConfirmation);
  const identityHint = document.createElement('p'); identityHint.className = 'muted';
  identityHint.textContent = 'Elegí el material. Podés marcar más de uno con Espacio; cada uno tendrá su pantalla de datos.';
  identity.querySelector('fieldset').appendChild(identityHint);
  materialSteps.forEach(group => {
    group.dataset.step = `material-${group.dataset.fieldsCategory}`;
    group.dataset.stepTitle = group.querySelector('legend').textContent;
    group.classList.add('card', 'material-step');
    form.appendChild(group);
  });
  categoryContainer.hidden = true;
  const order = [identity, ...materialSteps, ...['description', 'internal', 'publication', 'photos'].map(key => form.querySelector(`[data-step="${key}"]`)).filter(Boolean)];
  order.forEach(step => form.appendChild(step));
  const oldSavebar = form.querySelector('.savebar');
  oldSavebar.classList.add('abm-savebar');
  form.appendChild(oldSavebar);
  const saveButton = oldSavebar.querySelector('button[type="submit"]');
  saveButton.id = 'abm-save'; saveButton.name = 'after_save'; saveButton.value = 'list';
  saveButton.textContent = 'Guardar y cerrar';
  const saveNew = document.createElement('button'); saveNew.type = 'submit'; saveNew.name = 'after_save'; saveNew.value = 'new';
  saveNew.textContent = 'Guardar y nueva'; saveNew.id = 'abm-save-new'; oldSavebar.appendChild(saveNew);
  const footer = document.createElement('div'); footer.className = 'abm-controls';
  footer.innerHTML = '<button type="button" data-abm-back>← Anterior</button><span data-abm-counter aria-live="polite"></span><button type="button" class="primary" data-abm-next>Siguiente →</button>';
  form.appendChild(footer);
  const help = document.createElement('p'); help.className = 'abm-keyboard';
  help.innerHTML = '<kbd>Enter</kbd> avanzar · <kbd>Esc</kbd> volver · <kbd>Tab</kbd> campos · <kbd>Shift+Enter</kbd> nueva línea · <kbd>Ctrl+Enter</kbd> guardar';
  shell.insertBefore(help, form);
  navigation = document.createElement('nav'); navigation.className = 'abm-steps'; navigation.setAttribute('aria-label', 'Pantallas de carga');
  shell.insertBefore(navigation, form);
  const validation = document.createElement('p'); validation.className = 'abm-validation'; validation.setAttribute('role', 'alert'); validation.hidden = true;
  form.insertBefore(validation, form.firstChild);
  const error = message => { validation.textContent = message; validation.hidden = false; };
  const clearError = () => { validation.hidden = true; validation.textContent = ''; };
  const focusStep = () => {
    const step = steps[current];
    const first = [...step.querySelectorAll('input:not([type="hidden"]),select,textarea,button,a[href]')].find(el => !el.disabled && !el.closest('[hidden]'));
    if (first) first.focus({preventScroll: true});
    dialog.scrollTop = 0;
  };
  const validate = step => {
    if (step === identity && !form.querySelector('[data-category]:checked')) {
      error('Elegí al menos una categoría para continuar.');
      identity.querySelector('[data-category]').focus(); return false;
    }
    for (const field of step.querySelectorAll('input,select,textarea')) {
      if (!field.disabled && !field.checkValidity()) { field.focus(); field.reportValidity(); return false; }
    }
    if (step.dataset.step === 'description') {
      const from = form.elements.year_from.value; const to = form.elements.year_to.value;
      if (from !== '' && to !== '' && Number(from) > Number(to)) {
        error('El año inicial no puede ser posterior al final.'); form.elements.year_from.focus(); return false;
      }
    }
    return true;
  };
  const rebuild = () => {
    const key = steps[current]?.dataset.step;
    const selected = [...form.querySelectorAll('[data-category]:checked')].map(el => el.value);
    steps = order.filter(step => !step.dataset.fieldsCategory || selected.includes(step.dataset.fieldsCategory));
    current = Math.max(0, steps.findIndex(step => step.dataset.step === key));
    navigation.replaceChildren();
    steps.forEach((step, i) => {
      const button = document.createElement('button'); button.type = 'button'; button.textContent = `${String(i + 1).padStart(2, '0')} ${step.dataset.stepTitle}`;
      button.addEventListener('click', () => { if (i <= current || validate(steps[current])) selectStep(i); }); navigation.appendChild(button);
    });
  };
  selectStep = (index, focus = true) => {
    current = Math.max(0, Math.min(index, steps.length - 1));
    order.forEach(step => { step.hidden = step !== steps[current]; });
    const isLast = current === steps.length - 1;
    saveButton.hidden = !isLast; saveNew.hidden = !isLast;
    footer.querySelector('[data-abm-next]').hidden = isLast;
    footer.querySelector('[data-abm-back]').textContent = current === 0 ? 'Cancelar' : '← Anterior';
    footer.querySelector('[data-abm-counter]').textContent = `${current + 1} / ${steps.length} · ${steps[current].dataset.stepTitle}`;
    [...navigation.children].forEach((button, i) => {
      button.classList.toggle('active', i === current);
      if (i === current) button.setAttribute('aria-current', 'step'); else button.removeAttribute('aria-current');
    });
    form.elements._step.value = steps[current].dataset.step;
    clearError(); if (focus) focusStep();
  };
  const back = () => { if (current === 0) cancel(); else selectStep(current - 1); };
  const forward = () => {
    clearError(); if (!validate(steps[current])) return;
    if (current < steps.length - 1) selectStep(current + 1); else form.requestSubmit(saveButton);
  };
  footer.querySelector('[data-abm-back]').addEventListener('click', back);
  footer.querySelector('[data-abm-next]').addEventListener('click', forward);
  form.addEventListener('change', () => { rebuild(); selectStep(current, false); });
  form.addEventListener('submit', event => {
    // Instalación de estilos: operación separada, conserva su aviso de guardar antes.
    for (let i = 0; i < steps.length; i++) {
      const previous = current;
      selectStep(i, false);
      if (!validate(steps[i])) { event.preventDefault(); return; }
      current = previous;
    }
    selectStep(steps.length - 1, false);
  });
  dialog.addEventListener('cancel', event => { event.preventDefault(); back(); });
  dialog.addEventListener('keydown', event => {
    if (event.isComposing || event.altKey || event.metaKey) return;
    if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); back(); return; }
    if (event.key !== 'Enter') return;
    if (event.ctrlKey) { event.preventDefault(); form.requestSubmit(saveButton); return; }
    if (event.shiftKey && event.target.matches('textarea')) return;
    if (event.target.closest('button,a,summary') || event.target.matches('input[type="file"]')) return;
    event.preventDefault(); forward();
  });
  rebuild();
  const requested = form.elements._step.value;
  const initial = steps.findIndex(step => step.dataset.step === requested);
  selectStep(initial < 0 ? 0 : initial, false);
  dialog.showModal(); focusStep();
})();
