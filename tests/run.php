<?php
declare(strict_types=1);

/**
 * Testes automatizados (sem framework).
 * Uso: php tests/run.php
 *
 * Usa um banco SQLite temporario e nunca toca no banco real.
 */

$dbPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tia_ina_test_' . getmypid() . '.sqlite';
foreach ([$dbPath, $dbPath . '-wal', $dbPath . '-shm'] as $file) {
    if (is_file($file)) {
        @unlink($file);
    }
}
putenv('DB_PATH=' . $dbPath);
putenv('BASE_URL=http://test.local');

require_once dirname(__DIR__) . '/bootstrap.php';

$tests = 0;
$failures = 0;

function check(string $label, bool $condition, string $detail = ''): void
{
    global $tests, $failures;
    $tests++;
    if ($condition) {
        echo "  ok   {$label}\n";
        return;
    }
    $failures++;
    echo "  FAIL {$label}" . ($detail !== '' ? " ({$detail})" : '') . "\n";
}

function check_equals(string $label, mixed $expected, mixed $actual): void
{
    check($label, $expected === $actual, 'esperado ' . var_export($expected, true) . ', obtido ' . var_export($actual, true));
}

echo "== Schema e seed ==\n";
check_equals('schema version', SCHEMA_VERSION, (int) db()->query('PRAGMA user_version')->fetchColumn());
check_equals('8 produtos de exemplo', 8, (int) db()->query('SELECT COUNT(*) FROM products')->fetchColumn());
check_equals('1 admin de exemplo', 1, admin_count());
check('tabela orders existe', (bool) db()->query("SELECT name FROM sqlite_master WHERE type='table' AND name='orders'")->fetchColumn());

$products = load_products(false);
$slugs = array_column($products, 'slug');
check('todos os produtos tem slug', count(array_filter($slugs, static fn ($slug) => trim((string) $slug) !== '')) === 8);
check_equals('slugs unicos', count($slugs), count(array_unique($slugs)));
check('categorias preenchidas', count(array_filter(array_column($products, 'category'), static fn ($c) => trim((string) $c) !== '')) === 8);

echo "\n== Helpers ==\n";
check_equals('slugify acentos', 'requeijao-de-corte-250g', slugify('Requeijão de corte - 250g'));
check_equals('preco com virgula', 123456, parse_price_to_cents('R$ 1.234,56'));
check_equals('preco simples', 2000, parse_price_to_cents('20,00'));
check_equals('preco com ponto decimal', 3550, parse_price_to_cents('35.50'));
check_equals('formata preco', 'R$ 20,00', format_price_cents(2000));
check_equals('formata data', '01/01/2026 12:00', format_datetime_local('2026-01-01 15:00:00'));
check_equals('sanitiza script', 'Ola mundo', trim(preg_replace('/\s+/', ' ', sanitize_html('Ola <script>alert(1)</script> mundo'))));
check('sanitiza mantendo br', str_contains(sanitize_html('a<br>b'), '<br>'));
check('sanitiza removendo atributos', !str_contains(sanitize_html('<span onclick="x()">a</span>'), 'onclick'));
check('email valido', require_email('teste@exemplo.com'));
check('email invalido', !require_email('teste@'));
check_equals('telefone vira whatsapp', 'https://wa.me/5519989873151', whatsapp_url('tel:+5519989873151'));
check_equals('telefone local vira whatsapp', 'https://wa.me/5519989873151', whatsapp_url('(19) 98987-3151'));
check_equals('link whatsapp e mantido', 'https://wa.me/5511988887777?text=Oi', whatsapp_url('https://wa.me/5511988887777?text=Oi'));
check('icone do whatsapp e svg', str_contains(whatsapp_icon(), '<svg') && str_contains(whatsapp_icon(), 'currentColor'));

echo "\n== Catalogo ==\n";
$first = $products[0];
$bySlug = load_product_by_slug((string) $first['slug']);
check('busca por slug', $bySlug !== null && (int) $bySlug['id'] === (int) $first['id']);
check('slug inexistente retorna null', load_product_by_slug('nao-existe-' . bin2hex(random_bytes(3))) === null);
check('categorias do catalogo', count(product_categories()) >= 3);
$freeSlug = make_product_slug('Queijo fresco - 500g');
check('slug de produto existente recebe sufixo', $freeSlug !== (string) $first['slug']);

echo "\n== Pedidos ==\n";
$beforeStock = (int) $first['stock'];
$result = create_order([
    'name'     => 'Cliente de Teste',
    'phone'    => '(19) 99999-0000',
    'address'  => 'Rua Teste, 100, Centro',
    'note'     => 'Sem cebola',
    'delivery' => 'entrega',
], [
    ['id' => (int) $first['id'], 'qty' => 2],
]);
$order = $result['order'];
check_equals('total calculado no servidor', (int) $first['price_cents'] * 2, (int) $order['total_cents']);
check_equals('um item no pedido', 1, count($result['items']));
check('pedido persistido', load_order((int) $order['id']) !== null);
$wa = order_whatsapp_url($order, $result['items']);
check('url do whatsapp', str_starts_with($wa, 'https://wa.me/' . WHATSAPP_NUMBER . '?text='));
check('mensagem tem o total', str_contains(rawurldecode($wa), format_price_cents((int) $order['total_cents'])));

$rejected = false;
try {
    create_order(['name' => 'X', 'phone' => '123', 'address' => ''], []);
} catch (RuntimeException $exception) {
    $rejected = true;
}
check('pedido invalido e recusado', $rejected);

check('status valido atualiza', update_order_status((int) $order['id'], 'preparo'));
check('status invalido e recusado', !update_order_status((int) $order['id'], 'status-que-nao-existe'));
check_equals('status gravado', 'preparo', (string) load_order((int) $order['id'])['status']);

$totals = order_summary_totals();
check_equals('resumo total de pedidos', 1, $totals['total']);
check_equals('resumo sem pedidos novos', 0, $totals['novos']);
check_equals('resumo total do dia', (int) $order['total_cents'], $totals['hoje_cents']);
check_equals('filtro por status', 1, count(load_orders('preparo')));
check_equals('filtro por outro status', 0, count(load_orders('cancelado')));

echo "\n== Conteudo ==\n";
$content = content_all();
check('conteudo padrao presente', $content['brand_name'] !== '' && $content['hero_title'] !== '');
$faq = content_faq_items("Pergunta?|Resposta.\nInvalida sem separador\nOutra?|Outra resposta.");
check_equals('faq parseado', 2, count($faq));
check_equals('faq pergunta', 'Pergunta?', $faq[0]['question']);
$testimonials = content_testimonials("Maria|Muito bom|Franca\nJoao|Otimo");
check_equals('depoimentos parseados', 2, count($testimonials));
check_equals('depoimento sem cidade', '', $testimonials[1]['city']);
check('mapa usa google', str_contains(content_map_embed('Franca SP'), 'maps.google.com'));
check('paragrafos separados', substr_count(content_paragraphs("Um\n\nDois"), '<p>') === 2);

echo "\n== Rate limit ==\n";
rate_limit_clear('test', '10.0.0.1');
check('sem tentativas', !rate_limit_exceeded('test', '10.0.0.1', 2, 60));
rate_limit_hit('test', '10.0.0.1');
rate_limit_hit('test', '10.0.0.1');
check('limite atingido', rate_limit_exceeded('test', '10.0.0.1', 2, 60));
check('outro ip nao afetado', !rate_limit_exceeded('test', '10.0.0.2', 2, 60));
rate_limit_clear('test', '10.0.0.1');
check('limite limpo', !rate_limit_exceeded('test', '10.0.0.1', 2, 60));

echo "\n== Imagens ==\n";
check_equals('nome da variante', 'queijo_01-480.webp', image_variant_name('queijo_01.png', 480));
check_equals('dimensoes lidas', [1254, 1254], image_dimensions('queijo_01.png'));
$html = render_picture('logo.png', 'Logo <teste>', ['widths' => [160], 'sizes' => '160px', 'width' => 160, 'height' => 160]);
check('render_picture gera img', str_contains($html, '<img '));
check('render_picture escapa alt', str_contains($html, 'alt="Logo &lt;teste&gt;"'));
check('imagem inexistente retorna vazio', render_picture('nao-existe.png', 'x') === '');

foreach ([$dbPath, $dbPath . '-wal', $dbPath . '-shm'] as $file) {
    if (is_file($file)) {
        @unlink($file);
    }
}

echo "\n----------------------------------------\n";
echo "{$tests} testes, {$failures} falha(s).\n";
exit($failures === 0 ? 0 : 1);
