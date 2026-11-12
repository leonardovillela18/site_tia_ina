'use strict';

/* ---------- Menu mobile ---------- */
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
navigation?.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeMenu));
document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape') closeMenu();
});

/* ---------- Destaque do item atual no menu (somente na home) ---------- */
const navLinks = [...document.querySelectorAll('#navigation a')].filter((link) => link.hash && document.querySelector(link.hash));
if (navLinks.length && document.body.dataset.page === 'home') {
  let scheduled = false;
  const updateNavigation = () => {
    const header = document.querySelector('.site-header');
    const marker = window.scrollY + (header?.offsetHeight || 0) + 100;
    let current = navLinks[0];
    navLinks.forEach((link) => {
      const section = document.querySelector(link.hash);
      if (section && section.offsetTop <= marker) current = link;
    });
    if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4) current = navLinks[navLinks.length - 1];
    navLinks.forEach((link) => {
      link.classList.toggle('active', link === current);
      if (link === current) link.setAttribute('aria-current', 'location');
      else link.removeAttribute('aria-current');
    });
    scheduled = false;
  };
  window.addEventListener('scroll', () => {
    if (!scheduled) {
      scheduled = true;
      requestAnimationFrame(updateNavigation);
    }
  }, { passive: true });
  window.addEventListener('resize', updateNavigation);
  updateNavigation();
}

/* ---------- Filtro por categoria e busca ---------- */
const filterChips = [...document.querySelectorAll('.category-filter .chip')];
const searchField = document.getElementById('product-search');
const productCards = [...document.querySelectorAll('#product-grid .product-card')];
const emptyFilter = document.getElementById('empty-filter');

if (filterChips.length && productCards.length) {
  let activeCategory = 'todos';

  const applyFilters = () => {
    const query = (searchField?.value || '').toLowerCase().trim();
    let visible = 0;
    productCards.forEach((card) => {
      const category = card.dataset.category || '';
      const haystack = card.dataset.search || '';
      const matchesCategory = activeCategory === 'todos' || category === activeCategory;
      const matchesQuery = query === '' || haystack.includes(query);
      const show = matchesCategory && matchesQuery;
      card.hidden = !show;
      if (show) visible += 1;
    });
    if (emptyFilter) emptyFilter.hidden = visible > 0;
  };

  filterChips.forEach((chip) => {
    chip.addEventListener('click', () => {
      activeCategory = chip.dataset.category || 'todos';
      filterChips.forEach((other) => {
        other.classList.toggle('is-active', other === chip);
        other.setAttribute('aria-pressed', other === chip ? 'true' : 'false');
      });
      applyFilters();
    });
  });

  searchField?.addEventListener('input', applyFilters);
  applyFilters();
}

/* ---------- Editor de produto (admin) ---------- */
const editor = document.querySelector('#product-editor');
const addButton = document.querySelector('#btn-add-product');
const pedAction = document.querySelector('#ped-action');
const pedId = document.querySelector('#ped-id');
const pedName = document.querySelector('#ped-name');
const pedPrice = document.querySelector('#ped-price');
const pedDescription = document.querySelector('#ped-description');
const pedImage = document.querySelector('#ped-image');
const pedCategory = document.querySelector('#ped-category');
const pedActive = document.querySelector('#ped-active');
const pedTitle = document.querySelector('#product-editor-title');
const pedDelete = document.querySelector('#ped-delete');
const pedCancel = document.querySelector('#ped-cancel');

const openEditor = ({ id = 0, name = '', price = '', description = '', image = '', category = '', active = 1 } = {}) => {
  if (!editor) return;
  pedAction.value = 'save_product';
  pedId.value = id;
  pedName.value = name;
  pedPrice.value = price;
  pedDescription.value = description;
  pedImage.value = image;
  if (pedCategory) pedCategory.value = category || 'Queijos';
  if (pedActive) pedActive.checked = String(active) === '1';
  pedDelete.hidden = id === 0;
  pedTitle.textContent = id ? 'Editar produto' : 'Adicionar produto';
  editor.hidden = false;
  editor.scrollIntoView({ behavior: 'smooth', block: 'start' });
  pedName.focus();
};

addButton?.addEventListener('click', () => openEditor());
document.querySelectorAll('.card-edit').forEach((button) => button.addEventListener('click', () => {
  const card = button.closest('.product-card');
  if (!card) return;
  openEditor({
    id: Number(card.dataset.id),
    name: card.dataset.name,
    price: card.dataset.price,
    description: card.dataset.description,
    image: card.dataset.image,
    category: card.dataset.categoryEdit,
    active: Number(card.dataset.active || 0),
  });
}));
pedCancel?.addEventListener('click', () => { if (editor) editor.hidden = true; });
pedDelete?.addEventListener('click', () => {
  if (!pedId.value || !window.confirm('Excluir este produto?')) return;
  pedAction.value = 'delete_product';
  editor.querySelector('form').submit();
});

/* ---------- Editores de conteudo (admin) ---------- */
document.querySelectorAll('.section-edit').forEach((button) => button.addEventListener('click', () => {
  const target = document.getElementById(button.dataset.edit);
  if (!target) return;
  if (target.hidden) {
    document.querySelectorAll('.content-editor').forEach((other) => {
      if (other.id !== button.dataset.edit) other.hidden = true;
    });
    target.hidden = false;
    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    target.querySelector('input, textarea, select')?.focus();
  } else {
    target.hidden = true;
  }
}));
document.querySelectorAll('.content-editor .editor-cancel').forEach((button) => button.addEventListener('click', () => {
  const container = button.closest('.content-editor');
  if (container) container.hidden = true;
}));
