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
// Protótipo: não envia, valida ou armazena credenciais.
document.querySelector('#login-form')?.addEventListener('submit', event => {
  event.preventDefault();
  document.querySelector('#login-feedback').textContent = 'O acesso ainda não está disponível. A autenticação será habilitada em breve.';
});
document.querySelector('#forgot-password')?.addEventListener('click', () => {
  document.querySelector('#login-feedback').textContent = 'A recuperação de senha estará disponível quando o sistema de login for habilitado.';
});
