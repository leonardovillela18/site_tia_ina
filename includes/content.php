<?php
declare(strict_types=1);
require_once __DIR__ . '/images.php';

function content_defaults(): array
{
    return [
        'brand_name'       => 'Produtos Artesanais Tia Iná',
        'brand_tagline'    => 'Santa Rita do Passa Quatro / SP',
        'hero_title'       => 'Conheça o verdadeiro<br>sabor <em>da fazenda</em>',
        'hero_description' => 'Produção familiar, produtos de qualidade selecionados à mão, e produção à mão, para preservar o sabor autêntico de produtos artesanais e caseiros da Tia Iná.',
        'highlight_1_title' => 'Desde 2024',
        'highlight_1_text' => 'Tradição familiar',
        'highlight_2_title' => 'Entrega',
        'highlight_2_text' => 'Por toda a região',
        'highlight_3_title' => 'Artesanais',
        'highlight_3_text' => 'E selecionados',
        'about_title'      => 'Um pouco sobre nós...',
        'about_heading'    => 'Uma história feita com carinho',
        'about_text'       => "Tudo começou na fazenda, entre receitas de família, leite fresquinho e o cuidado de fazer cada coisa do jeito que sempre foi feito em casa. Foi assim que nasceu a Tia Iná, transformando tradição e carinho em produtos artesanais cheios de sabor.\n\nDa nossa fazenda para a sua mesa, preparamos queijos, requeijão, mussarela e doce de leite valorizando o sabor caseiro e a simplicidade da vida no campo.\n\nMais do que produzir alimentos, queremos manter viva uma tradição: reunir a família em volta da mesa e compartilhar sabores que trazem boas lembranças.\n\nTia Iná — feito com amor, direto do campo.",
        'about_image'      => 'familia.png',
        'farm_title'       => 'A fazenda',
        'farm_image'       => 'fazenda.png',
        'map_query'        => 'Santa Rita do Passa Quatro SP',
        'delivery_heading' => 'Fazemos entrega para Santa Rita do<br class="desktop-break"> Passa Quatro e região',
        'delivery_cities'  => "Santa Rita do Passa Quatro\nSerrana\nSertãozinho\nFranca\nRibeirão Preto\nBomfim",
        'contact_title'    => 'Contatos',
        'instagram_user'   => '@artesanais.tia.ina',
        'instagram_url'    => 'https://www.instagram.com/artesanais.tia.ina/',
        'phone_display'    => '(19) 98987-3151',
        'phone_url'        => 'tel:+5519989873151',
    ];
}

function content_get(string $key): string
{
    static $stored = null;
    if ($stored === null) {
        $stored = [];
        foreach (db()->query('SELECT key, value FROM site_settings') as $row) {
            $stored[$row['key']] = $row['value'];
        }
    }
    if (isset($stored[$key])) {
        return (string) $stored[$key];
    }
    $defaults = content_defaults();
    return (string) ($defaults[$key] ?? '');
}

function content_all(): array
{
    $values = content_defaults();
    foreach (db()->query('SELECT key, value FROM site_settings') as $row) {
        $values[$row['key']] = $row['value'];
    }
    return $values;
}

function content_paragraphs(string $text): string
{
    $html = '';
    foreach (preg_split('/\n\s*\n/', trim($text)) ?: [] as $paragraph) {
        $paragraph = trim($paragraph);
        if ($paragraph !== '') {
            $html .= '<p>' . e($paragraph) . '</p>';
        }
    }
    return $html;
}

function content_map_embed(string $query): string
{
    return 'https://maps.google.com/maps?q=' . rawurlencode($query) . '&z=11&output=embed';
}

function content_map_link(string $query): string
{
    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($query);
}

function handle_content_save(): void
{
    $admin = require_admin();
    $allowed = array_keys(content_defaults());
    $files = $_FILES['content_upl'] ?? [];
    $saved = 0;

    foreach ($_POST['content'] ?? [] as $key => $value) {
        if (!is_string($key) || !in_array($key, $allowed, true)) {
            continue;
        }
        $value = trim((string) $value);
        if (isset($files['name'][$key]) && ($files['error'][$key] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = [
                'name'     => (string) $files['name'][$key],
                'type'     => (string) ($files['type'][$key] ?? ''),
                'tmp_name' => (string) $files['tmp_name'][$key],
                'error'    => (int) $files['error'][$key],
                'size'     => (int) $files['size'][$key],
            ];
            try {
                $uploaded = handle_image_upload($upload, 'content_');
            } catch (RuntimeException $exception) {
                flash_set('error', $exception->getMessage());
                redirect('index.php');
            }
            if ($uploaded !== null) {
                $value = $uploaded;
            }
        }
        if ($value === '') {
            continue;
        }
        $stmt = db()->prepare('INSERT INTO site_settings (key, value, updated_at) VALUES (?, ?, datetime("now")) ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at');
        $stmt->execute([$key, $value]);
        $saved++;
    }

    flash_set($saved > 0 ? 'success' : 'error', $saved > 0 ? 'Conteúdo salvo.' : 'Nenhum campo foi alterado.');
    redirect('index.php');
}

function content_edit_button(string $target): string
{
    return '<button class="section-edit" type="button" data-edit="' . e($target) . '">Editar conteúdo</button>';
}

function content_editor(string $target, string $title, array $fields, array $values): string
{
    $html = "\n" . '<div class="card-editor content-editor" id="' . e($target) . '" hidden>';
    $html .= '<h3>Editar — ' . e($title) . '</h3>';
    $html .= '<form method="post" action="index.php" enctype="multipart/form-data">';
    $html .= csrf_field();
    $html .= '<input type="hidden" name="action" value="save_content">';
    $html .= '<div class="content-fields">';
    foreach ($fields as $key => $field) {
        $label = (string) ($field['label'] ?? $key);
        $hint = (string) ($field['hint'] ?? '');
        $value = (string) ($values[$key] ?? '');
        $html .= '<label>' . e($label);
        if ($hint !== '') {
            $html .= '<span class="field-hint">' . e($hint) . '</span>';
        }
        if (($field['type'] ?? 'text') === 'textarea') {
            $html .= '<textarea name="content[' . e($key) . ']" rows="' . (int) ($field['rows'] ?? 6) . '">' . e($value) . '</textarea>';
        } elseif (($field['type'] ?? 'text') === 'image') {
            $html .= '<input type="file" name="content_upl[' . e($key) . ']" accept="image/png,image/jpeg,image/webp">';
            $html .= '<select name="content[' . e($key) . ']">' . render_image_options($value) . '</select>';
        } else {
            $html .= '<input type="text" name="content[' . e($key) . ']" value="' . e($value) . '">';
        }
        $html .= '</label>';
    }
    $html .= '</div>';
    $html .= '<div class="card-editor-actions"><button class="login-submit" type="submit">Salvar</button><button class="admin-cancel editor-cancel" type="button">Cancelar</button></div>';
    $html .= '</form></div>';
    return $html;
}