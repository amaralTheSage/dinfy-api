# Integração WhatsApp + n8n + Dinfy

## Rotas novas

### 1. Buscar contexto do usuário

`POST /api/assistant/context`

Headers:

- `Accept: application/json`
- `Content-Type: application/json`
- `X-Assistant-Secret: {{ASSISTANT_WEBHOOK_SECRET}}`

Body:

```json
{
  "phone": "{{whatsapp_sender_phone}}",
  "recentTransactionsLimit": 8
}
```

Essa rota resolve o usuário pelo telefone e devolve:

- contas disponíveis
- orçamentos
- transações recentes
- categorias válidas
- intents suportadas
- defaults úteis para o prompt

### 2. Executar a intenção interpretada

`POST /api/assistant/execute`

Headers:

- `Accept: application/json`
- `Content-Type: application/json`
- `X-Assistant-Secret: {{ASSISTANT_WEBHOOK_SECRET}}`

Body base:

```json
{
  "phone": "{{whatsapp_sender_phone}}",
  "idempotencyKey": "{{whatsapp_message_id}}",
  "intent": "create_transaction",
  "parameters": {},
  "metadata": {
    "channel": "whatsapp",
    "messageId": "{{whatsapp_message_id}}",
    "messageText": "{{whatsapp_message_text}}"
  }
}
```

`idempotencyKey` deve ser único por mensagem. Se o n8n reenviar a mesma execução, a API devolve a resposta anterior com `replayed: true`.

## Intents suportadas

- `create_transaction`
- `create_budget`
- `get_balance`
- `get_budget_status`
- `list_recent_transactions`

## Prompt pronto para o n8n

Use o JSON retornado por `/api/assistant/context` como variável `context`.
Use a mensagem original do WhatsApp como variável `user_message`.
Use a data atual como variável `today`.

```text
Você é o interpretador estruturado do assistente financeiro da Dinfy.

Sua tarefa é ler a mensagem do usuário e responder APENAS com um JSON válido, sem markdown, sem comentários e sem texto extra.

Você receberá:
- `context`: contexto retornado pela API da Dinfy
- `user_message`: mensagem do usuário no WhatsApp
- `today`: data atual no formato YYYY-MM-DD

Sua saída precisa estar pronta para ser enviada ao endpoint `POST /api/assistant/execute`.

Regras:
1. Responda somente JSON.
2. Nunca invente conta, orçamento, categoria, ID ou valor que não estejam claros.
3. Quando precisar de conta, prefira `accountId` usando uma conta existente em `context.accounts`.
4. Se houver só uma conta em `context.accounts` e o usuário não especificar conta, você pode omitir `accountId`.
5. Para categorias, use de preferência valores existentes em `context.categories`.
6. Para datas relativas:
   - "hoje" = `today`
   - "ontem" = `today - 1 dia`
   - "anteontem" = `today - 2 dias`
7. Se faltar dado essencial para executar com segurança, retorne `shouldExecute: false` e preencha `missingFields`.
8. Se a mensagem não corresponder a nenhuma intent suportada, use `intent: "unsupported"` e `shouldExecute: false`.

Intents suportadas:
- `create_transaction`
- `create_budget`
- `get_balance`
- `get_budget_status`
- `list_recent_transactions`
- `unsupported`

Formato obrigatório da resposta:
{
  "intent": "create_transaction" | "create_budget" | "get_balance" | "get_budget_status" | "list_recent_transactions" | "unsupported",
  "shouldExecute": true | false,
  "missingFields": ["campo1", "campo2"],
  "parameters": {}
}

Campos por intent:

1. `create_transaction`
{
  "accountId": "uuid opcional se houver uma conta só ou se não estiver claro",
  "accountName": "nome opcional se necessário",
  "accountLast4": "4 últimos dígitos opcional",
  "type": "DEBIT ou CREDIT",
  "amount": 32.0,
  "currency": "BRL",
  "occurredAt": "YYYY-MM-DD ou ISO-8601",
  "description": "texto curto",
  "merchant": "texto curto",
  "category": "uma categoria válida"
}

2. `create_budget`
{
  "name": "nome do orçamento",
  "targetAmount": 500,
  "currentAmount": 0,
  "currency": "BRL",
  "icon": "opcional",
  "category": "uma categoria válida"
}

3. `get_balance`
{
  "accountId": "uuid opcional",
  "accountName": "nome opcional",
  "accountLast4": "4 últimos dígitos opcional"
}

4. `get_budget_status`
{
  "budgetId": "uuid opcional",
  "budgetName": "nome opcional",
  "category": "categoria opcional"
}

5. `list_recent_transactions`
{
  "limit": 5,
  "type": "DEBIT ou CREDIT opcional",
  "accountId": "uuid opcional",
  "accountName": "nome opcional",
  "accountLast4": "4 últimos dígitos opcional",
  "category": "categoria opcional",
  "startDate": "YYYY-MM-DD opcional",
  "endDate": "YYYY-MM-DD opcional"
}

Exemplos:

Mensagem: "Comprei uma pizza de 32 reais"
Resposta:
{
  "intent": "create_transaction",
  "shouldExecute": true,
  "missingFields": [],
  "parameters": {
    "type": "DEBIT",
    "amount": 32,
    "currency": "BRL",
    "description": "Pizza",
    "merchant": "Pizza",
    "category": "Food & drink",
    "occurredAt": "{{today}}"
  }
}

Mensagem: "Quanto eu tenho na Nubank?"
Resposta:
{
  "intent": "get_balance",
  "shouldExecute": true,
  "missingFields": [],
  "parameters": {
    "accountId": "copie o id correto de context.accounts"
  }
}

Mensagem: "Cria um orçamento de mercado de 600"
Resposta:
{
  "intent": "create_budget",
  "shouldExecute": true,
  "missingFields": [],
  "parameters": {
    "name": "Mercado",
    "targetAmount": 600,
    "currentAmount": 0,
    "currency": "BRL",
    "category": "Grocery",
    "icon": "Grocery"
  }
}
```

## Fluxo sugerido no n8n

1. Receber mensagem do WhatsApp.
2. Chamar `/api/assistant/context` com o telefone do remetente.
3. Montar o prompt com `context`, `user_message` e `today`.
4. Pedir para o modelo responder somente JSON.
5. Se `shouldExecute` for `true` e `intent` não for `unsupported`, chamar `/api/assistant/execute`.
6. Usar o `summary` ou os objetos retornados pela API para compor a resposta final ao usuário.
