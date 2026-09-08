# Produtos Artesanais Tia Iná

Front-end em PHP, HTML, CSS e JavaScript, usando as imagens de `img/`.

## Executar

Com PHP instalado, abra um terminal nesta pasta e execute:

```sh
php -S localhost:8888
```

Acesse `http://localhost:8888`. Se o PHP não estiver no PATH, use `C:\xampp\php\php.exe -S localhost:8888`. Mantenha o terminal aberto enquanto utilizar o site. Não é necessário ativar o Apache para usar esse servidor PHP.

- `index.php`: página principal contínua, com início, história, produtos, fazenda e contatos.
- `login.php`: tela de login separada, acessível pelo ícone no cabeçalho.
- `css/style.css`: estilos e adaptações para celular.
- `js/main.js`: menu, indicação da seção atual, consulta visual de produtos e mensagens da tela de login.

Não há autenticação, banco de dados, envio de formulários ou armazenamento de credenciais. Os botões dos produtos abrem uma janela de consulta com acesso aos contatos; não há carrinho ou checkout.

Os preços e contatos seguem os prints. O mapa apresenta a região de Santa Rita do Passa Quatro, pois não foi fornecido endereço exato da fazenda. O mapa e as fontes Google (Fraunces e Young Serif) precisam de internet; fontes serifadas locais são usadas como alternativa. As imagens permanecem locais. O banner usa a imagem fornecida, que difere da referência.
