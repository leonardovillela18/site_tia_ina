# Produtos Artesanais Tia Iná

Site mobile-first com catálogo de produtos, carrinho, checkout via WhatsApp e painel administrativo em PHP + SQLite.

## Funcionalidades

- **Catálogo responsivo** com busca e filtro por categoria.
- **Página de cada produto** (`produto.php?slug=...`) com preço, estoque, produtos relacionados, Open Graph e dados estruturados (JSON-LD).
- **Carrinho lateral** salvo no navegador e **checkout** que valida produtos, preços e estoque no servidor antes de gravar o pedido.
- **Pedido enviado pelo WhatsApp** com resumo pronto para a cliente confirmar.
- **Painel administrativo** com gestão de produtos (categoria e visibilidade) e de usuários, além do editor de conteúdo do site.
- **Editor de conteúdo** no próprio site (herói, história, como comprar, depoimentos, FAQ, fazenda, contatos).
- **Imagens otimizadas**: variantes WebP + `srcset` geradas automaticamente (GD).
- **Segurança**: CSRF em formulários, rate limit em login/recuperação/pedidos, escape de HTML, CSP com nonce e headers de segurança.
- **SEO**: títulos/descrições por página, canonical, `sitemap.php`, `robots.txt`, página 404 personalizada.

## Executar localmente

Com PHP 8.1+ (extensões `pdo_sqlite`, `mbstring`, `gd` com WebP):

```sh
php -S localhost:8888
```

Acesse `http://localhost:8888`. O banco `data/site.sqlite` é criado/migrado automaticamente na primeira execução.

Para usar outro banco (testes/staging):

```sh
DB_PATH=/caminho/site.sqlite BASE_URL=http://localhost:8899 php -S localhost:8899
```

No Windows (PowerShell):

```powershell
$env:DB_PATH="C:\temp\site.sqlite"; $env:BASE_URL="http://127.0.0.1:8899"
php -S 127.0.0.1:8899
```

## Painel administrativo

Acesse `http://localhost:8888/login.php` (ícone de usuário no cabeçalho).

- O administrador inicial é criado apenas quando o banco está vazio (`admin` / `admin123`).
- Troque a senha e o e-mail em **Painel → Usuários administrativos**.
- **Produtos**: categoria e visibilidade podem ser ajustados no painel. Foto, descrição, preço e criação de novos produtos ficam em **Ver site → Produtos** (botões de edição aparecem logado).

### Recuperação de senha

Em `login.php`, clique em **Esqueci minha senha**. Um link válido por 30 minutos é enviado por e-mail.
Configure `SMTP_*` no `.env` (ou em `config.php`). Sem SMTP válido o envio falha silenciosamente e o link não chega.

## Testes

```sh
php tests/run.php
```

Os testes usam um banco SQLite temporário e cobrem schema/seed, helpers, catálogo, criação e status de pedidos, conteúdo, rate limit e imagens.

Para validar a sintaxe de tudo:

```sh
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

## Imagens

As imagens originais ficam em `img/`. As variantes WebP são geradas por:

```sh
php tools/generate-images.php          # gera o que faltar
php tools/generate-images.php --force  # regera tudo
```

O upload de novas fotos no painel já gera as variantes automaticamente (requer GD com WebP).
As variantes não são versionadas (ver `.gitignore`).

## Estrutura

- `bootstrap.php`: entrada comum (sessão, headers, migrações).
- `index.php`: home com catálogo, conteúdo editável e carrinho.
- `produto.php`: página do produto; `checkout.php`: criação do pedido; `404.php`: página não encontrada.
- `sitemap.php`, `robots.txt`: SEO.
- `admin.php`, `login.php`, `logout.php`, `forgot.php`, `reset.php`: área administrativa.
- `config.php`: constantes e variáveis de ambiente (`DB_PATH`, `BASE_URL`, SMTP, `WHATSAPP_NUMBER`).
- `includes/`: banco/migrações (`db.php`), helpers (`functions.php`), imagens (`images.php`), conteúdo (`content.php`), pedidos (`orders.php`), SEO (`seo.php`), e-mail (`mailer.php`).
- `css/`, `js/`: estilos e scripts (mobile-first).
- `tests/`, `tools/`: testes e geração de imagens.

## Deploy

O site roda em container Docker no VPS da Mabelle (`/opt/tia-ina`), atrás do nginx do host.
Passo a passo em [`deploy/README.md`](deploy/README.md).

```sh
cd /opt/tia-ina
docker compose up -d --build
docker compose exec -T web php tools/generate-images.php
```

- Produção: `docker-compose.yml` (porta `127.0.0.1:8090`).
- Staging: `docker-compose.staging.yml` (porta `127.0.0.1:8091`, banco em `data-staging/`).
- CI: `.github/workflows/ci.yml` roda lint, testes e build da imagem.
- Deploy automático: `.github/workflows/deploy.yml` (configure os secrets `VPS_HOST`, `VPS_USER`, `VPS_SSH_KEY`, `VPS_PATH`).

## Notas

- Senhas usam `password_hash`/`password_verify`; tokens de recuperação são guardados como hash.
- Sessões são regeneradas no login; cookies são `HttpOnly` e `SameSite=Lax` (e `Secure` em HTTPS).
- O banco usa WAL e `busy_timeout`; as migrações são versionadas por `PRAGMA user_version`.
- As fontes Google (Fraunces e Young Serif) e o mapa precisam de internet; as imagens ficam locais.
