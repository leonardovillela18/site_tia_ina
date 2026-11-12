<?php
declare(strict_types=1);

/**
 * Cria um pedido validando produtos e precos no servidor.
 * Nunca confia no total enviado pelo navegador.
 */
function create_order(array $data, array $items): array
{
    $name = trim((string) ($data['name'] ?? ''));
    $phone = trim((string) ($data['phone'] ?? ''));
    $address = trim((string) ($data['address'] ?? ''));
    $note = trim((string) ($data['note'] ?? ''));
    $delivery = (string) ($data['delivery'] ?? 'entrega');
    $delivery = in_array($delivery, ['entrega', 'retirada'], true) ? $delivery : 'entrega';

    if (mb_strlen($name) < 2) {
        throw new RuntimeException('Informe seu nome completo.');
    }
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (strlen($digits) < 10 || strlen($digits) > 13) {
        throw new RuntimeException('Informe um telefone/WhatsApp válido com DDD.');
    }
    if ($delivery === 'entrega' && mb_strlen($address) < 5) {
        throw new RuntimeException('Informe o endereço para entrega (rua, número, bairro e cidade).');
    }
    if (!is_array($items) || count($items) === 0) {
        throw new RuntimeException('Seu carrinho está vazio.');
    }
    if (count($items) > 50) {
        throw new RuntimeException('Muitos itens no pedido.');
    }

    $wanted = [];
    foreach ($items as $item) {
        $id = (int) ($item['id'] ?? 0);
        $qty = (int) ($item['qty'] ?? 0);
        if ($id <= 0 || $qty <= 0) {
            continue;
        }
        $wanted[$id] = min(99, ($wanted[$id] ?? 0) + $qty);
    }
    if (!$wanted) {
        throw new RuntimeException('Nenhum item válido no carrinho.');
    }

    $placeholders = implode(',', array_fill(0, count($wanted), '?'));
    $stmt = db()->prepare("SELECT * FROM products WHERE active = 1 AND id IN ($placeholders)");
    $stmt->execute(array_keys($wanted));
    $products = [];
    foreach ($stmt->fetchAll() as $product) {
        $products[(int) $product['id']] = $product;
    }
    if (!$products) {
        throw new RuntimeException('Os produtos do carrinho não estão mais disponíveis.');
    }

    $lines = [];
    $total = 0;
    foreach ($wanted as $id => $qty) {
        if (!isset($products[$id])) {
            continue;
        }
        $product = $products[$id];
        $unit = (int) $product['price_cents'];
        $lineTotal = $unit * $qty;
        $total += $lineTotal;
        $lines[] = [
            'product_id'       => $id,
            'product_name'     => (string) $product['name'],
            'unit_price_cents' => $unit,
            'quantity'         => $qty,
            'line_total_cents' => $lineTotal,
        ];
    }
    if (!$lines) {
        throw new RuntimeException('Os produtos do carrinho não estão mais disponíveis.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare('INSERT INTO orders (customer_name, customer_phone, customer_address, note, delivery_method, total_cents, status) VALUES (?, ?, ?, ?, ?, ?, "novo")');
        $insert->execute([$name, $phone, $address, $note, $delivery, $total]);
        $orderId = (int) $pdo->lastInsertId();

        $insertItem = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, unit_price_cents, quantity, line_total_cents) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($lines as $line) {
            $insertItem->execute([
                $orderId,
                $line['product_id'],
                $line['product_name'],
                $line['unit_price_cents'],
                $line['quantity'],
                $line['line_total_cents'],
            ]);
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }

    $order = [
        'id'               => $orderId,
        'customer_name'    => $name,
        'customer_phone'   => $phone,
        'customer_address' => $address,
        'note'             => $note,
        'delivery_method'  => $delivery,
        'total_cents'      => $total,
        'status'           => 'novo',
    ];
    return ['order' => $order, 'items' => $lines];
}

function load_orders(?string $status = null, int $limit = 200): array
{
    $sql = 'SELECT * FROM orders';
    $params = [];
    if ($status !== null && $status !== '' && $status !== 'todos') {
        $sql .= ' WHERE status = ?';
        $params[] = $status;
    }
    $sql .= ' ORDER BY id DESC LIMIT ' . max(1, min(1000, $limit));
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    return attach_order_items($orders);
}

function load_order(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    if (!$order) {
        return null;
    }
    $orders = attach_order_items([$order]);
    return $orders[0];
}

function attach_order_items(array $orders): array
{
    if (!$orders) {
        return [];
    }
    $ids = array_map(static fn (array $order): int => (int) $order['id'], $orders);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT * FROM order_items WHERE order_id IN ($placeholders) ORDER BY id");
    $stmt->execute($ids);
    $items = [];
    foreach ($stmt->fetchAll() as $item) {
        $items[(int) $item['order_id']][] = $item;
    }
    foreach ($orders as &$order) {
        $order['items'] = $items[(int) $order['id']] ?? [];
    }
    unset($order);
    return $orders;
}

function update_order_status(int $id, string $status): bool
{
    if (!isset(order_statuses()[$status])) {
        return false;
    }
    $stmt = db()->prepare('UPDATE orders SET status = ?, updated_at = datetime("now") WHERE id = ?');
    $stmt->execute([$status, $id]);
    return $stmt->rowCount() > 0;
}

function order_summary_totals(): array
{
    $today = new DateTime('today', new DateTimeZone(date_default_timezone_get()));
    $tomorrow = (clone $today)->modify('+1 day');
    $utc = new DateTimeZone('UTC');
    $start = $today->setTimezone($utc)->format('Y-m-d H:i:s');
    $end = $tomorrow->setTimezone($utc)->format('Y-m-d H:i:s');

    $row = db()->query('SELECT COUNT(*) AS total, SUM(CASE WHEN status = "novo" THEN 1 ELSE 0 END) AS novos FROM orders')->fetch();
    $stmt = db()->prepare('SELECT COALESCE(SUM(total_cents), 0) FROM orders WHERE created_at >= ? AND created_at < ?');
    $stmt->execute([$start, $end]);

    return [
        'total'      => (int) ($row['total'] ?? 0),
        'novos'      => (int) ($row['novos'] ?? 0),
        'hoje_cents' => (int) $stmt->fetchColumn(),
    ];
}

function order_whatsapp_message(array $order, array $items): string
{
    $lines = [];
    $lines[] = '*Pedido #' . (int) $order['id'] . ' — ' . APP_NAME . '*';
    $lines[] = 'Cliente: ' . $order['customer_name'];
    $lines[] = 'Telefone: ' . $order['customer_phone'];
    $lines[] = 'Entrega: ' . ($order['delivery_method'] === 'retirada' ? 'Retirada na fazenda' : 'Entrega');
    if (trim((string) $order['customer_address']) !== '') {
        $lines[] = 'Endereço: ' . $order['customer_address'];
    }
    $lines[] = '';
    $lines[] = '*Itens:*';
    foreach ($items as $item) {
        $lines[] = '• ' . (int) $item['quantity'] . 'x ' . $item['product_name'] . ' — ' . format_price_cents((int) $item['line_total_cents']);
    }
    $lines[] = '';
    $lines[] = '*Total: ' . format_price_cents((int) $order['total_cents']) . '*';
    if (trim((string) $order['note']) !== '') {
        $lines[] = 'Observações: ' . $order['note'];
    }
    $lines[] = '';
    $lines[] = 'Pedido registrado pelo site. Aguardo a confirmação.';
    return implode("\n", $lines);
}

function order_whatsapp_url(array $order, array $items): string
{
    return 'https://wa.me/' . WHATSAPP_NUMBER . '?text=' . rawurlencode(order_whatsapp_message($order, $items));
}
