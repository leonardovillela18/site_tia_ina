# Deploy no VPS (Docker)

Alvo: `ubuntu@147.15.51.155` (`/opt/tia-ina`), atrás do nginx do host.

## Subir / atualizar

```sh
cd /opt/tia-ina
git pull --ff-only
docker compose up -d --build
docker compose exec -T web php tools/generate-images.php
docker compose ps
curl -I http://127.0.0.1:8090/
```

O site fica em `127.0.0.1:8090`. O nginx do host faz o proxy.

## Banco e imagens (persistentes)

- `./data` → banco SQLite (`site.sqlite`, `-wal`, `-shm`)
- `./img` → imagens dos produtos (uploads do painel) e variantes WebP

As migrações rodam sozinhas na primeira requisição (`PRAGMA user_version`), preservando os dados existentes.
As variantes WebP são geradas dentro do container (GD com suporte a WebP):

```sh
docker compose exec -T web php tools/generate-images.php
```

## Staging

```sh
docker compose -f docker-compose.staging.yml up -d --build
curl -I http://127.0.0.1:8091/
```

Staging usa `data-staging/site.sqlite` e a porta `8091`, sem tocar no banco de produção.
Para recriar o banco de staging do zero, pare o serviço e apague `data-staging/`.

## nginx

- `deploy/nginx-tia-ina.conf`: publica o site (hoje no IP `147.15.51.155`) com gzip, cache de estáticos e bloqueio de `/data/` e `/tools/`.
- `deploy/nginx-mabelle-maintenance.conf`: mantém `mabellebeauty.com.br` em manutenção (503), preservando `/sistema/` (StockFlow).

Instalação:

```sh
sudo cp deploy/nginx-tia-ina.conf /etc/nginx/sites-available/tia-ina
sudo ln -sfn /etc/nginx/sites-available/tia-ina /etc/nginx/sites-enabled/tia-ina
sudo nginx -t && sudo systemctl reload nginx
```

## Domínio + HTTPS

Depois de apontar o DNS do domínio para `147.15.51.155`:

```sh
sudo certbot --nginx -d seudominio.com.br -d www.seudominio.com.br
```

E ajuste `BASE_URL` no `.env` para `https://seudominio.com.br`, seguido de
`docker compose up -d`.

## Verificações após o deploy

```sh
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8090/
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8090/sitemap.php
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8090/produto.php?slug=queijo-fresco-500g
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8090/data/site.sqlite   # deve ser 403/404
```

## Reverter a manutenção da Mabelle

```sh
sudo ln -sfn /etc/nginx/sites-available/stockflow /etc/nginx/sites-enabled/stockflow
sudo rm -f /etc/nginx/sites-enabled/mabelle-maintenance
sudo nginx -t && sudo systemctl reload nginx
```
