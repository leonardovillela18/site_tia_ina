'use strict';

(() => {
  const KEY = 'tia_ina_cart_v1';
  const body = document.body;
  if (body.dataset.page !== 'checkout') return;

  const form = document.getElementById('checkout-form');
  const layout = document.getElementById('checkout-layout');
  const success = document.getElementById('checkout-success');
  const itemsBox = document.getElementById('checkout-items');
  const totalBox = document.getElementById('checkout-total');
  const errorBox = document.getElementById('checkout-error');
  const submit = document.getElementById('checkout-submit');
  const csrf = body.dataset.csrf || '';

  const read = () => {
    try {
      const value = JSON.parse(localStorage.getItem(KEY) || '[]');
      return Array.isArray(value) ? value : [];
    } catch (error) {
      return [];
    }
  };

  const money = (cents) => 'R$ ' + (cents / 100).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');

  const showError = (message) => {
    if (!errorBox) return;
    errorBox.textContent = message;
    errorBox.hidden = message === '';
  };

  const render = () => {
    const items = read();
    if (!itemsBox || !totalBox) return;
    itemsBox.textContent = '';
    if (!items.length) {
      const empty = document.createElement('p');
      empty.className = 'cart-empty';
      empty.textContent = 'Seu carrinho está vazio.';
      const link = document.createElement('a');
      link.className = 'back-to-products';
      link.href = 'index.php#produtos';
      link.textContent = '← Escolher produtos';
      itemsBox.append(empty, link);
      totalBox.textContent = money(0);
      if (submit) submit.disabled = true;
      return;
    }

    items.forEach((item) => {
      const row = document.createElement('div');
      row.className = 'checkout-item';
      const name = document.createElement('span');
      name.className = 'checkout-item-name';
      name.textContent = item.qty + 'x ' + item.name;
      const price = document.createElement('span');
      price.className = 'checkout-item-price';
      price.textContent = money(item.price * item.qty);
      row.append(name, price);
      itemsBox.appendChild(row);
    });
    totalBox.textContent = money(items.reduce((sum, item) => sum + item.price * item.qty, 0));
    if (submit) submit.disabled = false;
  };

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    showError('');
    const items = read();
    if (!items.length) {
      showError('Seu carrinho está vazio. Escolha um produto antes de finalizar.');
      return;
    }

    const name = document.getElementById('co-name')?.value.trim() || '';
    const phone = document.getElementById('co-phone')?.value.trim() || '';
    const delivery = document.getElementById('co-delivery')?.value || 'entrega';
    const address = document.getElementById('co-address')?.value.trim() || '';
    const note = document.getElementById('co-note')?.value.trim() || '';

    if (name.length < 2) {
      showError('Informe seu nome completo.');
      return;
    }
    if (phone.replace(/\D+/g, '').length < 10) {
      showError('Informe um WhatsApp/telefone válido com DDD.');
      return;
    }
    if (delivery === 'entrega' && address.length < 5) {
      showError('Informe o endereço completo para entrega.');
      return;
    }

    submit.disabled = true;
    submit.textContent = 'Enviando...';

    try {
      const response = await fetch('checkout.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrf,
        },
        body: JSON.stringify({
          name,
          phone,
          delivery,
          address,
          note,
          items: items.map((item) => ({ id: item.id, qty: item.qty })),
        }),
      });
      const data = await response.json();
      if (!response.ok || !data.ok) {
        throw new Error(data.error || 'Não foi possível registrar o pedido.');
      }

      localStorage.removeItem(KEY);
      if (layout) layout.hidden = true;
      if (success) success.hidden = false;
      document.getElementById('success-name').textContent = name.split(' ')[0];
      document.getElementById('success-order').textContent = '#' + data.order_id;
      document.getElementById('success-total').textContent = data.total;
      const whatsapp = document.getElementById('success-whatsapp');
      if (whatsapp) whatsapp.href = data.whatsapp;
      success?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (error) {
      showError(error.message || 'Erro ao enviar o pedido. Tente novamente.');
      submit.disabled = false;
      submit.textContent = 'Enviar pedido pelo WhatsApp';
    }
  });

  render();
})();
