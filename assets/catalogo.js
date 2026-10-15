'use strict';
const thumbs = [...document.querySelectorAll('.photo-thumb')];
const dialog = document.getElementById('zoom-dialog');
if (thumbs.length && dialog) {
  let index = 0;
  const mainPhoto = document.getElementById('main-photo');
  const zoomPhoto = document.getElementById('zoom-photo');
  const stage = dialog.querySelector('.zoom-stage');
  const scaleButton = document.getElementById('zoom-scale');
  const select = next => {
    index = (next + thumbs.length) % thumbs.length;
    const current = thumbs[index];
    mainPhoto.src = current.dataset.photo;
    mainPhoto.alt = current.dataset.alt;
    zoomPhoto.src = current.dataset.zoom;
    zoomPhoto.alt = current.dataset.alt;
    document.getElementById('photo-caption').textContent = current.dataset.caption;
    document.getElementById('zoom-caption').textContent = current.dataset.caption;
    document.getElementById('zoom-count').textContent = `${index + 1} / ${thumbs.length}`;
    stage.classList.remove('zoomed'); scaleButton.textContent = 'Acercar +';
    stage.scrollTop = 0; stage.scrollLeft = 0;
    thumbs.forEach((thumb, i) => {
      thumb.classList.toggle('active', i === index);
      thumb.setAttribute('aria-pressed', String(i === index));
    });
  };
  thumbs.forEach((thumb, i) => thumb.addEventListener('click', () => select(i)));
  document.querySelectorAll('[data-open-gallery]').forEach(button => button.addEventListener('click', () => { select(index); dialog.showModal(); }));
  document.getElementById('open-zoom').addEventListener('click', () => { select(index); dialog.showModal(); });
  document.getElementById('close-zoom').addEventListener('click', () => dialog.close());
  document.getElementById('prev-photo').addEventListener('click', () => select(index - 1));
  document.getElementById('next-photo').addEventListener('click', () => select(index + 1));
  scaleButton.addEventListener('click', () => {
    const zoomed = stage.classList.toggle('zoomed');
    scaleButton.textContent = zoomed ? 'Alejar −' : 'Acercar +';
  });
  dialog.addEventListener('keydown', event => {
    if (event.key === 'ArrowLeft') { event.preventDefault(); select(index - 1); }
    if (event.key === 'ArrowRight') { event.preventDefault(); select(index + 1); }
  });
  if (thumbs.length === 1) {
    document.getElementById('prev-photo').disabled = true;
    document.getElementById('next-photo').disabled = true;
  }
}

const shopMenu = document.querySelector('.shop-menu');
if (shopMenu) {
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && shopMenu.open) {
      shopMenu.open = false;
      shopMenu.querySelector('summary').focus();
    }
  });
  document.addEventListener('click', event => {
    if (!shopMenu.contains(event.target)) shopMenu.open = false;
  });
}
