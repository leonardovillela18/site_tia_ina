'use strict';

/**
 * Carrinho simples baseado em localStorage.
 * O checkout valida produtos, precos e estoque novamente no servidor.
 */
(() => {
  const KEY = 'tia_ina_cart_v1';
  const drawer = document.getElementById('cart-drawer');
  const overlay = document.getElementById('cart-overlay');
  const itemsBox = document.getElementById('cart-items');
  const totalBox = document.getElementById('cart-total');
  const openButtons = [document.getElementById('cart-open'), document.getElementById('cart-fab')].filter(Boolean);
  const closeButton = document.getElementById('cart-close');
  const counters = [document.getElementById('cart-count'), document.getElementById('cart-count-fab')].filter(Boolean);
  const toastBox = document.getElementById('toast');
  let toastTimer = null;

  const read = () => {
    try {
      const value = JSON.parse(localStorage.getItem(KEY) || '[]');
      return Array.isArray(value) ? value : [];
    } catch (error) {
      return [];
    }
  };

  const save = (items) => {
    localStorage.setItem(KEY, JSON.stringify(items));
    document.dispatchEvent(new CustomEvent('cart:updated', { detail: items }));
    render();
  };

  const money = (cents) => 'R$ ' + (cents / 100).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');

  const total = (items) => items.reduce((sum, item) => sum + item.price * item.qty, 0);

  const showToast = (message) => {
    if (!toastBox) return;
    toastBox.textContent = message;
    toastBox.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toastBox.hidden = true; }, 2600);
  };

  const setDrawer = (open) => {
    if (!drawer || !overlay) return;
    drawer.classList.toggle('is-open', open);
    drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
    overlay.hidden = !open;
    document.body.classList.toggle('cart-open', open);
    openButtons.forEach((button) => button.setAttribute('aria-expanded', open ? 'true' : 'false'));
    if (open) {
      drawer.querySelector('button, a')?.focus();
    }
  };

  const makeRow = (item) => {
    const row = document.createElement('div');
    row.className = 'cart-row';
    row.dataset.id = String(item.id);

    const link = document.createElement('a');
    link.className = 'cart-thumb';
    link.href = item.url || 'index.php#produtos';
    const image = document.createElement('img');
    image.src = item.image || 'img/logo.png';
    image.alt = '';
    image.width = 72;
    image.height = 72;
    image.loading = 'lazy';
    link.appendChild(image);

    const info = document.createElement('div');
    info.className = 'cart-row-info';
    const name = document.createElement('a');
    name.className = 'cart-row-name';
    name.href = item.url || 'index.php#produtos';
    name.textContent = item.name;
    const price = document.createElement('span');
    price.className = 'cart-row-price';
    price.textContent = money(item.price * item.qty);
    info.append(name, price);

    const controls = document.createElement('div');
    controls.className = 'cart-qty';
    const minus = document.createElement('button');
    minus.type = 'button';
    minus.dataset.action = 'dec';
    minus.setAttribute('aria-label', 'Diminuir quantidade de ' + item.name);
    minus.textContent = '−';
    const amount = document.createElement('span');
    amount.textContent = String(item.qty);
    const plus = document.createElement('button');
    plus.type = 'button';
    plus.dataset.action = 'inc';
    plus.setAttribute('aria-label', 'Aumentar quantidade de ' + item.name);
    plus.textContent = '+';
    const remove = document.createElement('button');
    remove.type = 'button';
    remove.dataset.action = 'remove';
    remove.className = 'cart-remove';
    remove.setAttribute('aria-label', 'Remover ' + item.name + ' do carrinho');
    remove.textContent = 'Remover';
    controls.append(minus, amount, plus, remove);

    row.append(link, info, controls);
    return row;
  };

  const render = () => {
    const items = read();
    counters.forEach((counter) => {
      const count = items.reduce((sum, item) => sum + item.qty, 0);
      counter.textContent = String(count);
      counter.hidden = count === 0;
    });
    if (totalBox) totalBox.textContent = money(total(items));
    if (!itemsBox) return;
    itemsBox.textContent = '';
    if (!items.length) {
      const empty = document.createElement('p');
      empty.className = 'cart-empty';
      empty.textContent = 'Seu carrinho está vazio. Escolha seus produtos favoritos.';
      itemsBox.appendChild(empty);
      return;
    }
    items.forEach((item) => itemsBox.appendChild(makeRow(item)));
  };

  const addItem = (data, qty) => {
    const items = read();
    const id = Number(data.id);
    const existing = items.find((item) => Number(item.id) === id);
    if (existing) {
      existing.qty += qty;
    } else {
      items.push({
        id,
        name: data.name,
        price: Number(data.price),
        image: data.image,
        url: data.url,
        qty,
      });
    }
    save(items);
    showToast(data.name + ' adicionado ao carrinho.');
  };

  document.addEventListener('click', (event) => {
    const addButton = event.target.closest('.add-to-cart');
    if (addButton) {
      const qtyInput = addButton.dataset.qtySource ? document.querySelector(addButton.dataset.qtySource) : null;
      const qty = Math.max(1, Number(qtyInput?.value || 1));
      addItem(addButton.dataset, qty);
      return;
    }

    if (event.target.closest('#cart-open') || event.target.closest('#cart-fab')) {
      setDrawer(true);
      return;
    }
    if (event.target.closest('#cart-close') || event.target.closest('#cart-overlay')) {
      setDrawer(false);
      return;
    }

    const actionButton = event.target.closest('[data-action]');
    if (actionButton && actionButton.closest('#cart-items')) {
      const row = actionButton.closest('.cart-row');
      const id = Number(row?.dataset.id);
      const items = read();
      const item = items.find((entry) => Number(entry.id) === id);
      if (!item) return;
      if (actionButton.dataset.action === 'inc') {
        item.qty += 1;
      } else if (actionButton.dataset.action === 'dec') {
        item.qty -= 1;
      } else if (actionButton.dataset.action === 'remove') {
        item.qty = 0;
      }
      save(items.filter((entry) => entry.qty > 0));
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && drawer?.classList.contains('is-open')) {
      setDrawer(false);
    }
  });

  window.addEventListener('storage', (event) => {
    if (event.key === KEY) render();
  });

  render();
})();
