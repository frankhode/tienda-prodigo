/* Prueba de interacción DOM sobre formularios reales exportados por integration.py.
   Requiere jsdom. Uso: node tests/abm.cjs /ruta/dom-fixtures
   No reemplaza la comprobación visual del modal en un navegador. */
const {JSDOM, VirtualConsole} = require('jsdom');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const fixtures = process.argv[2];
if (!fixtures) throw new Error('Indicá la carpeta de fixtures generada por integration.py.');
function load(name) {
  const dom = new JSDOM(fs.readFileSync(path.join(fixtures, name), 'utf8'), {
    url: 'http://localhost/index.php?page=edit', runScripts: 'outside-only', pretendToBeVisual: true,
    virtualConsole: new VirtualConsole()
  });
  const w = dom.window;
  w.HTMLDialogElement.prototype.showModal = function () { this.open = true; };
  w.HTMLDialogElement.prototype.close = function () { this.open = false; };
  w.confirm = () => true;
  const submissions = [];
  w.HTMLFormElement.prototype.requestSubmit = function (button) {
    const event = new w.SubmitEvent('submit', {bubbles:true, cancelable:true, submitter:button});
    this.dispatchEvent(event);
    if (!event.defaultPrevented) submissions.push({button, data:new w.FormData(this)});
  };
  w.eval(fs.readFileSync(path.join(root, 'assets/admin.js'), 'utf8'));
  w.eval(fs.readFileSync(path.join(root, 'assets/abm.js'), 'utf8'));
  return {dom, w, d:w.document, submissions};
}
function key(w, target, name, options={}) {
  const event = new w.KeyboardEvent('keydown', {key:name, bubbles:true, cancelable:true, ...options});
  target.dispatchEvent(event); return event;
}
function change(w, field) { field.dispatchEvent(new w.Event('change', {bubbles:true})); }
function current(d) { return [...d.querySelectorAll('[data-step]')].find(el => !el.hidden)?.dataset.step; }
{
  const {w,d,dom,submissions} = load('new.html');
  const form=d.querySelector('#piece-form'); const dialog=d.querySelector('.abm-dialog');
  assert.ok(dialog.open); assert.equal(current(d),'identity'); assert.equal(d.activeElement.name,'title');
  form.elements.title.value='Radio para prueba'; form.elements.title.dispatchEvent(new w.Event('input',{bubbles:true}));
  key(w,form.elements.title,'Enter');
  assert.equal(current(d),'identity'); assert.equal(submissions.length,0);
  assert.match(d.querySelector('.abm-validation').textContent,/categoría/);
  const radio=d.querySelector('[data-category][value="3"]'); radio.checked=true; change(w,radio);
  key(w,form.elements.title,'Enter'); assert.equal(current(d),'material-3');
  const brand=d.querySelector('input[name="fields[3][marca]"]'); brand.value='Mi marca';
  assert.equal(key(w,brand,'Tab').defaultPrevented,false);
  key(w,brand,'Escape'); assert.equal(current(d),'identity'); assert.equal(brand.value,'Mi marca');
  const machine=d.querySelector('[data-category][value="2"]'); machine.checked=true; change(w,machine);
  key(w,form.elements.title,'Enter'); assert.equal(current(d),'material-2');
  key(w,d.querySelector('[data-step="material-2"] input'),'Enter'); assert.equal(current(d),'material-3');
  key(w,brand,'Escape'); key(w,d.activeElement,'Escape'); assert.equal(current(d),'identity');
  machine.checked=false; change(w,machine);
  assert.ok(d.querySelector('[data-step="material-2"]').hidden);
  assert.ok(d.querySelector('[data-step="material-2"] input').disabled);
  key(w,form.elements.title,'Enter'); key(w,brand,'Enter'); assert.equal(current(d),'description');
  form.elements.description.value='Primera línea\nSegunda línea';
  assert.equal(key(w,form.elements.description,'Enter',{shiftKey:true}).defaultPrevented,false);
  assert.equal(current(d),'description');
  key(w,form.elements.description,'Enter'); assert.equal(current(d),'internal');
  key(w,d.activeElement,'Escape'); assert.equal(current(d),'description');
  assert.equal(form.elements.description.value,'Primera línea\nSegunda línea');
  let guard=0;
  while(current(d)!=='photos' && guard++<12) d.querySelector('[data-abm-next]').click();
  assert.equal(current(d),'photos');
  assert.equal(key(w,form.elements.photos||d.querySelector('#photos'),'Enter').defaultPrevented,false);
  form.requestSubmit(d.querySelector('#abm-save'));
  assert.equal(submissions.length,1);
  assert.equal(submissions[0].data.get('title'),'Radio para prueba');
  assert.equal(submissions[0].data.get('fields[3][marca]'),'Mi marca');
  assert.equal(submissions[0].data.get('_wizard'),'1');
  assert.equal(submissions[0].data.get('fields[2][marca]'),null);
  key(w,d.activeElement,'Escape'); assert.equal(current(d),'publication');
  while(current(d)!=='identity') key(w,d.activeElement,'Escape');
  w.confirm=()=>false; key(w,d.activeElement,'Escape'); assert.ok(dialog.open);
  w.confirm=()=>true; key(w,d.activeElement,'Escape'); assert.equal(dialog.open,false);
  dom.window.close();
}
{
  const {w,d,dom,submissions}=load('edit.html'); const form=d.querySelector('#piece-form');
  const remove=d.querySelector('[data-remove-image]'); assert.ok(remove);
  remove.click();
  assert.ok(remove.closest('.image-edit').classList.contains('pending-removal'));
  assert.equal(form.querySelector('input[name="remove_images[]"]').value,remove.dataset.removeImage);
  remove.click(); assert.equal(form.querySelector('input[name="remove_images[]"]'),null);
  key(w,form.elements.title,'Enter',{ctrlKey:true});
  assert.equal(submissions.length,1); assert.equal(current(d),'photos');
  dom.window.close();
}
console.log('OK: modal, Enter/Escape, Tab sin bloqueo, multilinea, pasos por material, conservación de campos, guardado rápido y eliminación reversible.');
