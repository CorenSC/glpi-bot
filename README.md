# GLPI BOT

GLPI BOT é um sistema Laravel 12 com Inertia, React e TypeScript para triagem assistida de chamados do GLPI. Ele lê chamados pela API REST oficial do GLPI, mantém uma base interna em MariaDB/MySQL, gera embeddings, compara chamados novos com o histórico, calcula ranking de técnicos, valida a recomendação com IA via OpenRouter e registra auditoria completa.

O sistema não responde automaticamente ao usuário final e não escreve diretamente no banco do GLPI.

## O Que o Sistema Faz

- Importa chamados históricos solucionados/fechados pela API do GLPI.
- Normaliza título, categoria, descrição, solução e histórico do chamado.
- Detecta categoria no título, por exemplo `[SICSP 2.0]`.
- Gera embeddings dos chamados históricos.
- Analisa chamados novos.
- Busca chamados semanticamente parecidos.
- Calcula ranking de técnicos com base em similaridade, categoria, histórico, recência, carga, contexto e feedback humano.
- Usa OpenRouter para validar e explicar a recomendação.
- Exibe sugestões em painel administrativo.
- Permite aprovar, rejeitar, escolher outro técnico, enviar para triagem manual, recalcular ranking e revalidar a IA pelo painel.
- Registra auditoria de análises, ações humanas, execuções do scheduler, payloads e respostas.
- Aprende operacionalmente com aprovações/rejeições e escolha manual de técnico.
- Pode atribuir chamados via API REST do GLPI, somente quando habilitado e aprovado.

## O Que o Sistema Não Faz

- Não treina modelo do zero.
- Não responde o usuário final.
- Não escreve diretamente no banco do GLPI.
- Não usa microserviço Python.
- Não usa pgvector/PostgreSQL neste projeto.
- Não atribui automaticamente quando `GLPI_AI_REQUIRE_HUMAN_APPROVAL=true`.

## Requisitos

- PHP 8.3 ou superior.
- Composer.
- Node.js 20 ou superior.
- MariaDB/MySQL.
- Extensões PHP comuns do Laravel: `pdo_mysql`, `curl`, `openssl`, `mbstring`, `fileinfo`, `tokenizer`, `xml`.
- Extensão PHP `ldap`, se o login LDAP estiver ativo.
- Redis, se for usar filas assíncronas em produção.
- Acesso à API REST do GLPI.
- Conta no OpenRouter com chave de API.

No Windows, confira qual `php.ini` está sendo usado:

```bash
php --ini
```

## Instalação Local

```bash
git clone https://github.com/CorenSC/glpi-bot.git glpi-bot
cd glpi-bot
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan migrate
npm run build
```

Para desenvolvimento, rode em dois terminais:

```bash
php artisan serve
```

```bash
npm run dev
```

Acesse:

```text
http://127.0.0.1:8000/glpi-ai
```

## Banco Interno

O banco interno é MariaDB/MySQL:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=glpi_bot
DB_USERNAME=glpi_bot
DB_PASSWORD=senha_forte
```

Para zerar o banco interno e recriar as tabelas:

```bash
php artisan migrate:fresh
```

Atenção: esse comando apaga apenas os dados internos do GLPI BOT. Ele não apaga nada no GLPI.

## Configuração Principal do Robô

Configuração segura para início:

```env
GLPI_AI_DRY_RUN=true
GLPI_AI_AUTO_ASSIGN=false
GLPI_AI_REQUIRE_HUMAN_APPROVAL=true
```

Significado:

- `GLPI_AI_DRY_RUN=true`: simulação. Nunca escreve no GLPI.
- `GLPI_AI_AUTO_ASSIGN=false`: desliga atribuição via API.
- `GLPI_AI_REQUIRE_HUMAN_APPROVAL=true`: mesmo com autoatribuição habilitada, a análise só cria sugestão; você decide no painel.

Configuração para teste real controlado:

```env
GLPI_AI_DRY_RUN=false
GLPI_AI_AUTO_ASSIGN=true
GLPI_AI_REQUIRE_HUMAN_APPROVAL=true
```

Nesse modo, `glpi-ai:analyze-new-tickets` apenas cria sugestões. A escrita real no GLPI só acontece quando você abre a sugestão no painel e confirma a ação.

Depois de mudar `.env`, rode:

```bash
php artisan optimize:clear
```

## Configuração Editável Pelo Painel

Por padrão, a tela `/glpi-ai/settings` é somente leitura. Para permitir edição segura pelo painel:

```env
GLPI_AI_ENABLE_DASHBOARD_SETTINGS_EDIT=true
```

O painel permite alterar parâmetros operacionais sem expor tokens:

- dry-run;
- autoatribuição;
- exigência de aprovação humana;
- thresholds de confiança;
- quantidade de similares;
- diferença mínima entre candidatos;
- intervalo de análise de chamados novos;
- dias para arquivamento de sugestões finalizadas;
- status de chamados novos e históricos.

As alterações são gravadas em `glpi_ai_settings` e auditadas em execuções operacionais. Tokens do GLPI, LDAP e OpenRouter não são exibidos nem editados pelo painel.

Variáveis novas do bloco operacional:

```env
GLPI_AI_ANALYZE_NEW_TICKETS_INTERVAL_MINUTES=2
GLPI_AI_ARCHIVE_AFTER_DAYS=30
```

## Configuração da API do GLPI

```env
GLPI_API_BASE_URL=https://chamados.exemplo.gov.br/apirest.php
GLPI_WEB_BASE_URL=https://chamados.exemplo.gov.br
GLPI_APP_TOKEN=
GLPI_USER_TOKEN=
GLPI_API_VERIFY_SSL=true
GLPI_API_ENTITY_ID=all
GLPI_API_ENTITY_RECURSIVE=true
```

`GLPI_WEB_BASE_URL` é usado para gerar links diretos:

```text
https://chamados.exemplo.gov.br/front/ticket.form.php?id=1924
```

## Como Pegar as Chaves no GLPI

1. Entre no GLPI como administrador.
2. Vá em `Configurar > Geral > API`.
3. Ative a API REST.
4. Crie ou habilite um cliente de API.
5. Gere o `App Token`.
6. Crie um usuário dedicado para o robô, por exemplo `Bot Sistema`.
7. Dê permissões para esse usuário enxergar chamados, usuários, grupos, categorias, soluções e acompanhamentos.
8. No cadastro do usuário, habilite token de API ou token pessoal.
9. Copie o token do usuário para `GLPI_USER_TOKEN`.

O usuário do robô precisa conseguir ler chamados, categorias, usuários/técnicos, grupos, soluções, acompanhamentos e vínculos de atribuição.

Se você for testar atribuição real, ele também precisa permissão para atribuir técnico, atribuir grupo e, se estiver habilitado, adicionar nota interna.

## OpenRouter

```env
OPENROUTER_API_KEY=
OPENROUTER_MODEL=nvidia/nemotron-3-super-120b-a12b:free
OPENROUTER_BASE_URL=https://openrouter.ai/api/v1
OPENROUTER_SITE_URL=
OPENROUTER_APP_NAME="GLPI BOT"
OPENROUTER_VERIFY_SSL=true
```

Modelo de embedding:

```env
GLPI_AI_EMBEDDING_PROVIDER=openrouter
GLPI_AI_EMBEDDING_MODEL="nvidia/llama-nemotron-embed-vl-1b-v2:free"
GLPI_AI_EMBEDDING_DIMENSION=1536
```

Verifique no OpenRouter se o modelo escolhido suporta embeddings. Modelo de chat não é automaticamente modelo de embedding.

## Login e LDAP

```env
LDAP_ENABLED=true
LDAP_HOST=192.168.1.17
LDAP_PORT=389
LDAP_ENCRYPTION=none
LDAP_BASE_DN="DC=coren,DC=local"
LDAP_SERVICE_DN=
LDAP_SERVICE_PASSWORD=
LDAP_USER_FILTER="(&(objectCategory=person)(objectClass=user)(sAMAccountName=%s))"
LDAP_REQUIRED_DESCRIPTION_CONTAINS=DTI
```

Se `LDAP_REQUIRED_DESCRIPTION_CONTAINS=DTI`, somente usuários cujo atributo `description` contenha `DTI` conseguem acessar.

No Windows, habilite a extensão LDAP no `php.ini`:

```ini
extension=ldap
```

## Fluxo de Uso

Primeira carga:

```bash
php artisan glpi-ai:sync-history
php artisan glpi-ai:generate-embeddings --all
```

Analisar chamados novos:

```bash
php artisan glpi-ai:analyze-new-tickets
```

Analisar um chamado específico:

```bash
php artisan glpi-ai:dry-run-ticket 1924
```

Recalcular ranking de uma sugestão:

```bash
php artisan glpi-ai:recalculate-suggestion 35
```

Arquivar sugestões finalizadas antigas:

```bash
php artisan glpi-ai:archive-suggestions --days=30
```

## O Que Cada Comando Faz

### `glpi-ai:sync-history`

Busca no GLPI chamados históricos com status configurados em:

```env
GLPI_AI_HISTORICAL_TICKET_STATUSES=5,6
```

Normalmente:

- `5`: solucionado;
- `6`: fechado.

Ele salva a base interna que será usada como histórico. Chamados já importados não são reprocessados sem necessidade.

### `glpi-ai:generate-embeddings`

Gera embeddings apenas para registros que ainda não têm embedding ou tiveram conteúdo alterado.

### `glpi-ai:analyze-new-tickets`

Busca chamados novos conforme:

```env
GLPI_AI_NEW_TICKET_STATUSES=1
```

No GLPI, normalmente `1` significa `Novo`.

O comando lê chamados novos, monta texto canônico, gera embedding temporário, busca similares, calcula ranking, chama a IA para validar/explicar, salva sugestão e não atribui sozinho se `GLPI_AI_REQUIRE_HUMAN_APPROVAL=true`.

### `glpi-ai:sync-suggestion-statuses`

Verifica no GLPI se chamados com sugestão local foram solucionados ou fechados. Quando isso acontece, o GLPI BOT atualiza o status local e mantém histórico/auditoria.

### Revalidação de IA pelo painel

Na tela de detalhe da sugestão, use o botão de revalidação de IA quando quiser tentar novamente apenas a explicação/validação do modelo, sem refazer todo o ranking.

## Fila

Para rodar simples no seu PC:

```env
QUEUE_CONNECTION=sync
```

Para produção com fila no banco:

```env
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=360
```

Worker:

```bash
php artisan queue:work database --queue=glpi-ai --sleep=3 --tries=3 --timeout=300
```

Se usar Redis:

```env
QUEUE_CONNECTION=redis
REDIS_QUEUE_RETRY_AFTER=360
```

```bash
php artisan queue:work redis --queue=glpi-ai --sleep=3 --tries=3 --timeout=300
```

## Scheduler

O Scheduler permite rodar o robô continuamente.

Rotina configurada:

- sincronizar histórico: a cada hora;
- gerar embeddings pendentes: a cada minuto;
- analisar chamados novos: intervalo configurável, padrão de 2 minutos;
- sincronizar status de sugestões: a cada 5 minutos;
- arquivar sugestões finalizadas antigas: diariamente.

No Linux, configure o cron:

```bash
* * * * * cd /var/www/glpi-bot && php artisan schedule:run >> /dev/null 2>&1
```

## Supervisor em Produção

Exemplo:

```ini
[program:glpi-bot-worker]
command=php /var/www/glpi-bot/artisan queue:work database --queue=glpi-ai --sleep=3 --tries=3 --timeout=300
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/glpi-bot-worker.log
```

## Painel Administrativo

Rotas principais:

- `/glpi-ai`: dashboard.
- `/glpi-ai/suggestions`: fila de sugestões.
- `/glpi-ai/suggestions/{id}`: detalhe da sugestão.
- `/glpi-ai/manual-analysis`: análise manual.
- `/glpi-ai/audit`: auditoria.
- `/glpi-ai/settings`: configurações.

O painel mostra estado de dry-run ou execução real, sugestões pendentes, técnico recomendado, grupo do chamado, confiança, risco, chamados similares, ranking de técnicos, histórico de ações humanas e link direto para o chamado no GLPI.

## Observabilidade

O dashboard operacional mostra:

- jobs pendentes;
- jobs falhados;
- último erro de IA;
- último chamado analisado;
- últimas execuções do scheduler;
- duração e status de sync, embeddings, análise e sincronização de status.

## Aprendizado por Validação Humana

O sistema não treina um modelo do zero. O aprendizado é operacional:

- aprovação aumenta a relevância do técnico escolhido;
- rejeição reduz a confiança em padrões parecidos;
- escolher outro técnico registra sinal positivo para esse técnico;
- a observação humana entra na auditoria;
- o ranking das próximas análises considera esse feedback.

## Auditoria

A auditoria permite responder:

- qual chamado foi analisado;
- quando foi analisado;
- qual modelo de IA foi usado;
- qual embedding foi usado;
- qual foi a confiança;
- qual técnico foi recomendado;
- quais chamados similares influenciaram;
- quem aprovou ou rejeitou;
- se houve escrita real no GLPI;
- qual erro ocorreu, se houver;
- qual execução do scheduler gerou o evento.

## Teste Real Seguro

Para testar atribuição real sem deixar o robô atribuir sozinho:

```env
GLPI_AI_DRY_RUN=false
GLPI_AI_AUTO_ASSIGN=true
GLPI_AI_REQUIRE_HUMAN_APPROVAL=true
```

Depois:

```bash
php artisan optimize:clear
php artisan glpi-ai:analyze-new-tickets
```

Abra a sugestão no painel e clique na ação desejada.

Para voltar para simulação:

```env
GLPI_AI_DRY_RUN=true
GLPI_AI_AUTO_ASSIGN=false
GLPI_AI_REQUIRE_HUMAN_APPROVAL=true
```

```bash
php artisan optimize:clear
```

## Configurações Importantes

```env
GLPI_AI_CONFIDENCE_TECHNICIAN=60
GLPI_AI_CONFIDENCE_GROUP=45
GLPI_AI_MAX_SIMILAR_TICKETS=10
GLPI_AI_MINIMUM_SIMILAR_TICKETS=3
GLPI_AI_MINIMUM_GAP_BETWEEN_CANDIDATES=3
GLPI_AI_MINIMUM_CONTEXT_SCORE_FOR_TECHNICIAN=0.35
GLPI_AI_ALLOW_GROUP_RECOMMENDATION=false
GLPI_AI_NEW_TICKET_STATUSES=1
GLPI_AI_HISTORICAL_TICKET_STATUSES=5,6
GLPI_AI_ONLY_UNASSIGNED_NEW_TICKETS=false
GLPI_AI_IGNORE_GROUP_ASSIGNMENT_FOR_NEW_TICKETS=true
GLPI_AI_HYDRATE_TICKET_SOLUTIONS=true
GLPI_AI_HYDRATE_TICKET_FOLLOWUPS=false
GLPI_AI_ANALYZE_NEW_TICKETS_INTERVAL_MINUTES=2
GLPI_AI_ARCHIVE_AFTER_DAYS=30
```

Observação: no seu fluxo, chamados novos já vêm com o grupo da TI. Por isso `GLPI_AI_IGNORE_GROUP_ASSIGNMENT_FOR_NEW_TICKETS=true` permite analisar chamados mesmo que já tenham grupo atribuído.

## Deploy no Servidor

Depois de baixar ou atualizar o projeto no servidor:

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan migrate
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Garanta que o cron do scheduler e o worker da fila estejam ativos.

## Problemas Comuns

### `Class "Redis" not found`

Use fila síncrona no PC:

```env
QUEUE_CONNECTION=sync
```

### `The environment file is invalid`

Valores com `:` precisam estar entre aspas:

```env
GLPI_AI_EMBEDDING_MODEL="nvidia/llama-nemotron-embed-vl-1b-v2:free"
```

### OpenRouter HTTP 402

O modelo pode não estar disponível gratuitamente para embeddings, ou a conta pode exigir crédito. Teste outro modelo de embedding no OpenRouter.

### GLPI API HTTP 403

Normalmente é permissão do usuário/token. Verifique entidade, perfil, permissões de chamado, API habilitada, App Token e User Token.

### LDAP não autentica

Verifique extensão `ldap`, `LDAP_ENCRYPTION`, base DN, filtro de usuário e atributo `description` contendo `DTI`.

## Regras de Segurança

Checklist mínimo para produção:

```env
APP_ENV=production
APP_DEBUG=false
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=360
GLPI_API_VERIFY_SSL=true
OPENROUTER_VERIFY_SSL=true
```

- Dry-run nunca escreve no GLPI.
- Escrita real exige `GLPI_AI_DRY_RUN=false` e `GLPI_AI_AUTO_ASSIGN=true`.
- Com `GLPI_AI_REQUIRE_HUMAN_APPROVAL=true`, análise automática só cria sugestão.
- Escrita no GLPI ocorre somente pela API REST oficial.
- Tokens não são exibidos no frontend.
- Erro de IA ou API força falha segura.
- Rejeitado, risco alto e falhas aparecem em vermelho.
- Aprovado e ações de aprovação aparecem em verde.

## Comandos Úteis

```bash
php artisan optimize:clear
php artisan migrate
php artisan migrate:fresh
php artisan glpi-ai:sync-history
php artisan glpi-ai:generate-embeddings --all
php artisan glpi-ai:analyze-new-tickets
php artisan glpi-ai:dry-run-ticket 1924
php artisan glpi-ai:recalculate-suggestion 35
php artisan glpi-ai:archive-suggestions --days=30
npm run dev
npm run build
```
