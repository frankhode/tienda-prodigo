'use strict';
const form = document.getElementById('piece-form');
let dirty = false;
let submitting = false;
if (form) {
  const updateCategories = () => {
    const ids = [...form.querySelectorAll('[data-category]:checked')].map(el => el.value);
    form.querySelectorAll('[data-fields-category]').forEach(group => {
      group.hidden = !ids.includes(group.dataset.fieldsCategory);
      group.querySelectorAll('input').forEach(el => { el.disabled = group.hidden; });
    });
    const count = form.elements.component_count;
    if (count) count.disabled = form.elements.unit_type.value !== 'set';
  };
  updateCategories();
  form.addEventListener('input', () => {
    dirty = true;
    document.getElementById('save-state').textContent = 'Tenés cambios sin guardar.';
  });
  form.addEventListener('change', updateCategories);
  form.addEventListener('submit', event => {
    if (event.defaultPrevented) return;
    submitting = true;
    document.getElementById('save-state').textContent = 'Guardando…';
  });
  const imageList = form.querySelector('.image-list');
  const renumber = () => {
    [...imageList.children].forEach((item, index) => {
      item.querySelector('input[name$="[position]"]').value = index + 1;
    });
    dirty = true;
    document.getElementById('save-state').textContent = 'Tenés cambios sin guardar.';
  };
  imageList.addEventListener('click', event => {
    const button = event.target.closest('[data-move]');
    if (!button) return;
    const item = button.closest('.image-edit');
    if (button.dataset.move === 'up' && item.previousElementSibling) imageList.insertBefore(item, item.previousElementSibling);
    if (button.dataset.move === 'down' && item.nextElementSibling) imageList.insertBefore(item.nextElementSibling, item);
    renumber();
  });
  let dragged = null;
  imageList.addEventListener('dragstart', event => {
    if (!event.target.matches('[data-drag-handle]')) return;
    dragged = event.target.closest('.image-edit');
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', 'photo');
  });
  imageList.addEventListener('dragover', event => { if (dragged) event.preventDefault(); });
  imageList.addEventListener('drop', event => {
    if (!dragged) return;
    event.preventDefault();
    const target = event.target.closest('.image-edit');
    if (target && target !== dragged) {
      const rect = target.getBoundingClientRect();
      imageList.insertBefore(dragged, event.clientY < rect.top + rect.height / 2 ? target : target.nextSibling);
      renumber();
    }
    dragged = null;
  });
  imageList.addEventListener('dragend', () => { dragged = null; });
  let objectUrls = [];
  document.getElementById('photos').addEventListener('change', event => {
    const preview = document.getElementById('upload-preview');
    objectUrls.forEach(URL.revokeObjectURL); objectUrls = [];
    preview.replaceChildren();
    for (const file of event.target.files) {
      if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) continue;
      const img = document.createElement('img');
      const localUrl = URL.createObjectURL(file); objectUrls.push(localUrl);
      img.src = localUrl; img.alt = file.name; preview.appendChild(img);
    }
    if (event.target.files.length > 12) {
      const note = document.createElement('p'); note.textContent = 'Elegí hasta 12 fotos por carga.'; preview.appendChild(note);
    }
  });
}
document.querySelectorAll('[data-confirm]').forEach(button => {
  button.addEventListener('click', event => {
    if (!window.confirm(button.dataset.confirm)) { event.preventDefault(); event.stopPropagation(); }
    else submitting = true;
  });
});
window.addEventListener('beforeunload', event => {
  if (dirty && !submitting) { event.preventDefault(); event.returnValue = ''; }
});
