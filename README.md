# Produtos Artesanais Tia Iná

Site com catálogo de produtos e um painel administrativo em PHP (SQLite) para gerenciar produtos e administradores.

## Executar

Com PHP instalado, abra um terminal nesta pasta e execute:

```sh
php -S localhost:8888
```

Se o PHP não estiver no PATH, use `C:\xampp\php\php.exe -S localhost:8888`. Mantenha o terminal aberto. Acesse `http://localhost:8888`.

## Painel administrativo

Acesse `http://localhost:8888/login.php` (link "entrar" no cabeçalho do site).

- **Login padrão:** usuário `admin` / senha `admin123`
- Ao entrar, **troque o e-mail e a senha** em **Admin → Minha conta**.

### O que o painel permite

- **Cadastrar, editar e excluir produtos** (nome, preço, descrição e imagem).
  - Os preços são informados em reais (ex.: `20,50`) e convertidos para centavos no banco.
  - As imagens disponíveis são as `.png`/`.jpg` que ficam em `img/`.
- **Cadastrar e remover outros administradores** (o primeiro admin só pode ser criado por outro admin).
- **Alterar o e-mail/senha da própria conta.**

> Nenhuma pessoa pode criar conta no site — a criação é exclusiva do painel (um administrador cria os demais).

### Esqueci minha senha

Em `login.php`, clique em **Esqueci minha senha**, informe o e-mail cadastrado e um link de recuperação
(válido por 30 minutos) será enviado por e-mail. Ele permite definir uma nova senha.

**Configuração do e-mail (SMTP):** edite as constantes `SMTP_*` em `config.php` com as credenciais de um remetente
(ex.: um app password do Gmail). Sem credenciais válidas, o envio falha e nada é enviado.

## Estrutura

- `index.php`: catálogo público (produtos vêm do banco).
- `login.php`, `logout.php`: autenticação do administrador.
- `forgot.php`, `reset.php`: recuperação de senha por e-mail.
- `admin.php`: painel para gerenciar produtos e administradores.
- `config.php`: configurações (banco, SMTP, contatos).
- `includes/`: funções (`functions.php`), acesso ao banco (`db.php`) e envio de e-mail (`mailer.php`).
- `css/admin.css`: estilos do painel e das telas de login/recuperação.
- `data/site.sqlite`: banco SQLite (criado automaticamente). Não versionar (ver `.gitignore`).

## Notas

- Senhas são processadas com `password_hash`/`password_verify`; tokens de recuperação são guardados como hash.
- Proteção CSRF em todos os formulários; sessões regeneradas ao logar.
- As fontes Google (Fraunces e Young Serif) e o mapa precisam de internet; as imagens permanecem locais.