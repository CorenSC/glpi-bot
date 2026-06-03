# Como habilitar a API do GLPI para o robô

Use este fluxo para GLPI 10 usando a API REST v1 (`apirest.php`). Em GLPI 11 pode existir API v2; este MVP está preparado para v1.

## 1. Criar usuário técnico do robô

1. Entre no GLPI como administrador.
2. Vá em **Administração > Usuários**.
3. Crie um usuário dedicado, por exemplo `robo.triagem.ia`.
4. Dê a ele um perfil com permissão para:
   - ler chamados;
   - ler usuários, grupos, categorias, soluções e acompanhamentos;
   - futuramente, se autoatribuição for ativada, atribuir técnicos/grupos e criar acompanhamento interno.
5. Não use seu usuário pessoal para integração.

## 2. Habilitar API REST e gerar App-Token

1. Vá em **Configurar > Geral > API**.
2. Habilite a API REST.
3. Crie/adiciona um cliente de API.
4. Informe um nome, por exemplo `GLPI AI Triage Bot`.
5. Ative o cliente.
6. Se possível, restrinja o cliente ao IP do servidor onde o Laravel vai rodar.
7. Copie o **App-Token** gerado.
8. Coloque no `.env`:

```env
GLPI_API_BASE_URL=https://seu-glpi.exemplo.com/apirest.php
GLPI_APP_TOKEN=cole_o_app_token_aqui
```

## 3. Gerar User-Token

1. Entre no GLPI com o usuário técnico do robô.
2. Abra as preferências do usuário.
3. Procure por **Chave de acesso remoto**, **Remote access key** ou **Token da API**, conforme tradução/versão.
4. Gere uma nova chave.
5. Copie o token.
6. Coloque no `.env`:

```env
GLPI_USER_TOKEN=cole_o_user_token_aqui
```

## 4. Testar autenticação

PowerShell:

```powershell
curl.exe -H "App-Token: SEU_APP_TOKEN" -H "Authorization: user_token SEU_USER_TOKEN" "https://seu-glpi.exemplo.com/apirest.php/initSession"
```

Resposta esperada:

```json
{"session_token":"..."}
```

Depois teste um ticket:

```powershell
curl.exe -H "App-Token: SEU_APP_TOKEN" -H "Session-Token: SESSION_TOKEN_RETORNADO" "https://seu-glpi.exemplo.com/apirest.php/Ticket/1?expand_dropdowns=true&get_hateoas=false"
```

Finalize a sessão:

```powershell
curl.exe -H "App-Token: SEU_APP_TOKEN" -H "Session-Token: SESSION_TOKEN_RETORNADO" "https://seu-glpi.exemplo.com/apirest.php/killSession"
```

## 5. Segurança operacional

- Mantenha `GLPI_AI_DRY_RUN=true` no início.
- Mantenha `GLPI_AI_AUTO_ASSIGN=false` até validar auditoria e ranking.
- Não coloque tokens em logs, prints ou commits.
- Use um usuário técnico dedicado e revogue o token se houver vazamento.
- Restrinja o cliente de API por IP quando possível.
