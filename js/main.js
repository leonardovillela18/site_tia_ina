'use strict';
const menuButton = document.querySelector('.menu-toggle');
const navigation = document.querySelector('#navigation');
function closeMenu() {
  navigation?.classList.remove('is-open');
  menuButton?.setAttribute('aria-expanded', 'false');
  menuButton?.setAttribute('aria-label', 'Abrir menu');
}
menuButton?.addEventListener('click', () => {
  const open = navigation.classList.toggle('is-open');
  menuButton.setAttribute('aria-expanded', String(open));
  menuButton.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
});
navigation?.querySelectorAll('a').forEach(link => link.addEventListener('click', closeMenu));
document.addEventListener('keydown', event => { if (event.key === 'Escape') closeMenu(); });
const navLinks = [...document.querySelectorAll('#navigation a')];
if (navLinks.length) {
  let scheduled = false;
  const updateNavigation = () => {
    const marker = window.scrollY + document.querySelector('.site-header').offsetHeight + 100;
    let current = navLinks[0];
    navLinks.forEach(link => { if (document.querySelector(link.hash).offsetTop <= marker) current = link; });
    if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4) current = navLinks.at(-1);
    navLinks.forEach(link => {
      link.classList.toggle('active', link === current);
      if (link === current) link.setAttribute('aria-current', 'location');
      else link.removeAttribute('aria-current');
    });
    scheduled = false;
  };
  window.addEventListener('scroll', () => { if (!scheduled) { scheduled = true; requestAnimationFrame(updateNavigation); } }, { passive: true });
  window.addEventListener('resize', updateNavigation);
  updateNavigation();
}
const productDialog = document.querySelector('#product-dialog');
document.querySelectorAll('[data-product]').forEach(button => button.addEventListener('click', () => {
  document.querySelector('#selected-product').textContent = button.dataset.product;
  productDialog.showModal();
}));
document.querySelector('.dialog-close')?.addEventListener('click', () => productDialog.close());
document.querySelector('#product-contact')?.addEventListener('click', () => productDialog.close());
productDialog?.addEventListener('click', event => {
  const bounds = productDialog.getBoundingClientRect();
  if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) productDialog.close();
});
const editor = document.querySelector('#product-editor');
const addButton = document.querySelector('#btn-add-product');
const pedAction = document.querySelector('#ped-action');
const pedId = document.querySelector('#ped-id');
const pedName = document.querySelector('#ped-name');
const pedPrice = document.querySelector('#ped-price');
const pedDescription = document.querySelector('#ped-description');
const pedImage = document.querySelector('#ped-image');
const pedTitle = document.querySelector('#product-editor-title');
const pedDelete = document.querySelector('#ped-delete');
const pedCancel = document.querySelector('#ped-cancel');
const openEditor = ({ id = 0, name = '', price = '', description = '', image = '' } = {}) => {
  pedAction.value = 'save_product';
  pedId.value = id;
  pedName.value = name;
  pedPrice.value = price;
  pedDescription.value = description;
  pedImage.value = image;
  pedDelete.hidden = id === 0;
  pedTitle.textContent = id ? 'Editar produto' : 'Adicionar produto';
  editor.hidden = false;
  editor.scrollIntoView({ behavior: 'smooth', block: 'start' });
  pedName.focus();
};
addButton?.addEventListener('click', () => openEditor());
document.querySelectorAll('.card-edit').forEach(button => button.addEventListener('click', () => {
  const card = button.closest('.product-card');
  openEditor({
    id: Number(card.dataset.id),
    name: card.dataset.name,
    price: card.dataset.price,
    description: card.dataset.description,
    image: card.dataset.image,
  });
}));
pedCancel?.addEventListener('click', () => { editor.hidden = true; });
pedDelete?.addEventListener('click', () => {
  if (!pedId.value || !window.confirm('Excluir este produto?')) return;
  pedAction.value = 'delete_product';
  editor.querySelector('form').submit();
});
document.querySelectorAll('.section-edit').forEach(button => button.addEventListener('click', () => {
  const target = document.getElementById(button.dataset.edit);
  if (!target) return;
  if (target.hidden) {
    document.querySelectorAll('.content-editor').forEach(other => { if (other.id !== button.dataset.edit) other.hidden = true; });
    target.hidden = false;
    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    target.querySelector('input, textarea, select')?.focus();
  } else {
    target.hidden = true;
  }
}));
document.querySelectorAll('.content-editor .editor-cancel').forEach(button => button.addEventListener('click', () => {
  button.closest('.content-editor').hidden = true;
}));
