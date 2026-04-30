# API de Pagamento - TecnoSpeed (1.0.0)

Esta documentação irá te explicar como iniciar a integração entre seu sistema e nossa API.

## Introdução

Antes de começarmos, vamos para uma breve explicação de como nossa API funciona:

Somos uma API REST, portanto, toda a comunicação entre seu sistema e o nosso será feita via
requisições HTTPs. **Ou seja, se a linguagem de programação que você desenvolve possibilita a troca
de informações via requisições HTTPs, seu sistema é compatível com o nosso!**

Sistemas escritos em JavaScript, Delphi, C#, Java, VB6, PHP, Python e qualquer outra linguagem que
dispare requisições HTTPs podem utilizar nossos serviços.


## Explicando cada passo

#### 1 - Cadastrar o pagador

Esta é a primeira rotina que devemos implementar, e deve ser chamada sempre que formos precisar
gerar o pagamento com uma conta que não tenha sido utilizada ainda em nossa API.

A implementação contempla 2 rotas: A primeira para cadastrar o CNPJ do pagador, e a segunda para
consultar o pagador cadastrado.

Após cadastrar a conta pela primeira vez, este cadastro não precisará ser refeito para os próximos
pagamentos.

Esta requisição irá te retornar um campo chamado "hashConta", que é o identificador da conta que foi
cadastrada, e será utilizado nas requisições seguintes, onde iremos gerar o pagamento.

#### 2 - Solicitar um pagamento

Nesta rotina iremos informar para a API as informações do pagamento que iremos efetuar. Aqui
passaremos o valor deste pagamento, dados do favorecido, e demais informações que o banco
utilizará para efetuar o pagamento em si.

Esta rota devolverá um campo chamado "uniqueId", que é o identificador único de cada pagamento
gerado e será utilizado na sequência do fluxo, como por exemplo, no momento de solicitar a remessa.

#### 3 - Gerando a remessa

No passo "2" nós fizemos a solicitação dos pagamentos, e nossa API te retornou o "uniqueId" de cada
um deles.

Agora, nesta requisição, é hora de solicitar para a nossa API a remessa contendo os seus pagamentos.


Esta rota recebe a lista dos uniqueId que você deseja que estejam presentes na remessa, e devolve um
número de protocolo referente a este pedido de remessa, que deverá ser consultado na rota a seguir.

#### 4 - Upload do retorno (apenas para a Transmissão Manual)

Esta rotina define as 2 rotas que fazem a recepção dos arquivos de retorno pela API de Pagamentos
da Tecnospeed. Caso você não utilize a Transmissão Automática, será esta rotina que você irá utilizar
para nos encaminhar os arquivos de retorno gerados pelo banco, contendo o resultado do
processamento das remessas de pagamento.

#### 5 - Consultar os pagamentos

A rota de consulta é utilizada para que você saiba o resultado da conciliação bancária, tanto para
boletos trafegados de forma manual quanto para os trafegados de forma automática. O retorno desta
rotina será atualizado conforme as instruções que vierem do banco, e estarão disponíveis para que
você faça as consultas.

## Como conseguir o token da Software House

Para que seja possível dar inicio a integração com a API de Pagamentos, seria necessário
primeiramente localizar o token da **Sofware House**.

O token da Software House é disponibilizado no **portal de contas da TecnoSpeed** , que pode ser
acessado através da **URL** : https://conta.tecnospeed.com.br/.

A tela inicial de login que será apresentada é esta:


Assim que for realizado o acesso à página, será solicitado o e-mail e senha cadastrado, e se no caso
você ainda não possuir este cadastro, é preciso somente clicar em Criar uma conta e informar e-mail,
nome completo, CPF ou CNPJ e a senha para acesso.

Ao realizar o login será apresentada esta tela:

Para que você consiga adquirir o token da Software House basta clicar em **Ver meu token** , botão que
fica localizado na parte central da página.

Lembramos que é de suma importância guardar este token, pois ele será utilizado para autenticar as
requisições feitas pela API de Pagamentos.


## Fluxo ideal para emissão de Pagamentos

A fim de facilitar a integração de nossos clientes e parceiros, elaboramos este breve descritivo para
mostrar de uma forma intuitiva e modelada o fluxo ideal de emissão de pagamentos, utilizando a
nossa solução para integração de Pagamentos.

O objetivo principal é que aqui você consiga ter uma visão geral de nosso sistema, das principais
funcionalidades do componente/API, métodos e rotas, e entender o funcionamento, para que o início
de suas implementações ocorram da forma mais produtiva possível!

**Tudo começa com a geração do JSON em seu sistema**

Em nossa integração, as informações dos Pagamentos podem ser passadas via JSON, com campos
padronizados. Ou seja, independente do banco ou tipo de pagamento**, os campos dos pagamentos
terão o mesmo nome

###### **vale lembrar temos alguns tipos de pagamentos e bancos com campos específicos, porém descritos

na documentação.

É claro, existem particularidades entre bancos, como o Banco X exigir determinado campo, enquanto
que o mesmo campo não é aceito pelo Banco Y. Mas estes são detalhes que vamos acertando no
momento de homologar o pagamentos junto ao banco. O que importa é que com esta padronização, o
esforço no desenvolvimento para homologar diferentes bancos fica muito reduzido!

**E a partir daqui, esta é a sequencia do fluxo:**

**1º - Inclusão do JSON de Pagamento:** Ou seja, é o momento que você pega o JSON que seu sistema
montou e envia para nossa API (via https). Nós recebemos este arquivo e te devolvemos um uniqueID,
que é um ID único que vai identificar seu pagamento em nosso sistema. Esse ID é importante porque
para algumas partes do fluxo nós iremos utilizá-lo.

**2º - Gerar a remessa desse pagamento:** É o momento que realizamos a geração desse remessa de
pagamento para o envio ao banco. Vale lembrar que atualmente contamos com a forma de envio
automática, que pode ser vista com melhores detalhes nessa mesma documentação, buscando por:
“Transmissão automática”

**3º - Aguardar a conciliação do retorno bancário:** Para esse passo, basta aguardar o prazo estipulado
pelo banco para a conciliação bancária, para a grande maioria dos bancos o envio de pagamentos tem
um tempo médio de processamento e envio de retorno entre 2 à 4h a partir do momento que o arquivo
for enviado ao banco.

**4º - Consultar o uniqueID:** É o momento que consultamos o pagamento em nosso banco de dados,
para sabermos se se ele está com uma das seguintes **situações** :

```
CREATED - Pagamento criado na API da Tecnospeed;
PAID (*) - Pagamento efetivado e confirmado pelo banco;
SCHEDULED (*) - Pagamento agendado junto ao banco;
CANCELLED (*) - Solicitação de pagamento cancelada junto ao banco;
```

```
REJECTED (*) - Pedido de pagamento rejeitado pelo banco;
REFUNDED (*) - Pagamento devolvido pelo banco.
```
*Status que serão alterados apenas após existir a conciliação bancária.

**Para facilitar a compreensão, segue também o modelo com a sequência de fluxos:**

Vale lembrar que além destes métodos modelados acima, temos também diversos outros métodos,
incluindo:

```
Notificações via Webhook;
Emissão de um comprovante de pagamento;
Consulta de bilhetagem;
Conciliação de Extrato bancário via VAN(OFX);
Pagamentos em Lote
```
## Pagador

Nessa seção, você encontrará informações sobre como criar, consultar e atualizar um pagador.

## Cadastrar pagador

Antes de fazer qualquer ação no produto, é necessário fazer o **cadastro do pagador** , ou seja, cadastrar
o CNPJ do pagador e as contas que irão gerar os pagamentos.

Este processo é feito através da rota que explicaremos abaixo.

**Obs.** : É importante que os campos que identificam a conta sejam confirmados com o banco antes de
efetuar o cadastro das informações em nossa API.

HEADER PARAMETERS


```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
REQUEST BODY SCHEMA: application/json

```
string <= 250 characters
Nome ou Razão Social do pagador.
```
```
string <= 250 characters
Email do pagador
```
```
string <= 18 characters
CPF ou CNPJ do pagador
```
```
Array of objects
```
```
boolean
Campo que define se o pagador cadastrado possuirá o serviço de DDA ativo
ou não (a ativação necessita e contrato prévio com a Tecnospeed)
```
```
string <= 250 characters
Logradouro do pagador. Nome da Rua, Av, Pça, Etc.
```
```
string <= 250 characters
Informar o Bairro do pagador.
```
```
string <= 10 characters
Número do Local do pagador.
```
```
string <= 250 characters
Complemento de endereço do pagador. Casa, Apto, Sala, Etc.
```
```
string <= 250 characters
Nome da Cidade do pagador.
```
```
string <= 2 characters
Sigla do Estado (UF) do pagador. Utilizar 2 caracteres. Ex: SP, RJ, PR, MG...
```
```
string <= 10 characters
```
```
cnpjsh
required
```
```
tokensh
required
```
```
Content-Type
required
```
```
name
required
```
```
email
```
```
cpfCnpj
required
```
```
accounts
```
```
ddaActived
```
```
street
```
```
neighborhood
required
```
```
addressNumber
```
```
addressComplement
```
```
city
required
```
```
state
required
```
```
zipcode
required
```

```
CEP do pagador
```
### Responses

```
201
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Request samples

```
Payload
```
##### Response samples

```
POST /api/v1/payer
```
```
application/json
```
```
Copy Expand all Collapse all
{
"name": "CNPJ PARA TESTES",
"cpfCnpj": "01001001000113",
"neighborhood": "DUQUE DE CAXIAS",
"addressNumber": "882",
"zipcode": "87020025",
"state": "PR",
"city": "MARINGA",
```
- "accounts": [
    + { ... },
    + { ... }
]
}

```
Content type
```

```
201 401 422
```
```
application/json
```
```
Copy Expand all Collapse all
{
"name": "string",
"email": "string",
"cpfCnpj": "string",
```
- "accounts": [
    + { ... }
],
"street": "string",
"neighborhood": "string",
"addressNumber": "string",
"addressComplement": "string",
"city": "string",
"state": "string",
"zipcode": "string",
"token": "string"
}

## Consultar pagador

Rota responsável pela **consulta dos dados do pagador**.

Em caso de sucesso, o retorno da API trará na resposta todas as informações que foram cadastradas
para o CNPJ consultado, incluindo o accountHash, token e demais campos que visem a
identificação do pagador.

HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
cnpjsh
required
```
```
tokensh
required
```
```
Content type
```

```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
### Responses

```
200
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Response samples

```
200 401 422
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
GET /api/v1/payer
```
```
application/json
```
```
Copy Expand all Collapse all
{
"name": "TESTES",
"status": 1 ,
"active": true,
"cpfCnpj": "01001001000113",
```
- "accounts": [
    + { ... }
],
"neighborhood": "DUQUE DE CAXIAS",
"addressNumber": "882",

```
Content type
```

```
"city": "MARINGA",
"state": "PR",
"zipcode": "87020025",
"token": "Bzg5eMxc-xsRsSMZtD5ZM",
"createdAt": "2025-03-20 13:21:57",
"updatedAt": "2025-03-21 20:30:57"
}
```
## Atualizar dados do pagador

A documentação abaixo mostra como alterar dados de um pagador. Este recurso pode ser utilizado
caso precise atualizar as informações como nome, e-mail, endereço e telefone dos pagadores
cadastrados em nossa API.

HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
REQUEST BODY SCHEMA: application/json

```
string <= 250 characters
Nome ou Razão Social do pagador.
```
```
string <= 250 characters
Email do pagador
```
```
string <= 250 characters
Logradouro do pagador. Nome da Rua, Av, Pça, Etc.
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
name
```
```
email
```
```
street
```

```
string <= 250 characters
Informar o Bairro do pagador.
```
```
string <= 10 characters
Número do Local do pagador.
```
```
string <= 250 characters
Complemento de endereço do pagador. Casa, Apto, Sala, Etc.
```
```
string <= 250 characters
Nome da Cidade do pagador.
```
```
string <= 2 characters
Sigla do Estado (UF) do pagador. Utilizar 2 caracteres. Ex: SP, RJ, PR, MG...
```
```
string <= 10 characters
CEP do pagador
```
### Responses

```
200
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Request samples

```
Payload
```
```
neighborhood
```
```
addressNumber
```
```
addressComplement
```
```
city
```
```
state
```
```
zipcode
```
```
PUT /api/v1/payer
```
```
application/json
```
```
Copy
```
```
Content type
```

##### Response samples

```
200 401 422
```
```
{
"name": "Tecnospeed",
"email": "contato@tecnospeed.com.br",
"street": "Av. Duque de Caxias",
"neighborhood": "Zona 01",
"addressNumber": "882",
"addressComplement": "",
"city": "MARINGA",
"state": "PR",
"zipcode": "87020025"
}
```
```
application/json
```
```
Copy
{
"name": "Tecnospeed",
"email": "contato@tecnospeed.com.br",
"cpfCnpj": "86492313000120",
"street": "Av. Duque de Caxias",
"neighborhood": "Zona 01",
"addressNumber": "882",
"addressComplement": "complement",
"city": "MARINGA",
"state": "PR",
"zipcode": "87020025"
}
```
## Listar pagadores

Rota responsável pela listagem dos dados dos pagadores.

HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
cnpjsh
required
```
```
Content type
```

```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
### Responses

```
200
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Response samples

```
200 401 422
```
```
tokensh
required
```
```
Content-Type
required
```
```
GET /api/v1/payer/list
```
```
application/json
```
```
Copy Expand all Collapse all
{
```
- "payers": {
    "type": "array",
+ "items": { ... }
}
}

```
Content type
```

## Desativar um pagador

Rota responsável por **desativar de um pagador**.

PATH PARAMETERS

```
string
```
HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
### Responses

```
200
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Response samples

```
tokenPayer
required
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
DELETE /api/v1/payer/{tokenPayer}
```

## Conta

Nessa seção, você encontrará informações sobre como criar, consultar, atualizar e deletar contas.

```
200 401 422
```
```
application/json
```
```
Copy Expand all Collapse all
{
"active": false,
"message": "Pagador desativado com sucesso",
```
- "payer": {
    "name": "Nome do pagador"
}
}

## Criar conta

A documentação a seguir mostrará como efetuar o **cadastro** de contas.

HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
Content type
```

REQUEST BODY SCHEMA: application/json

Array (non-empty) [

```
string <= 3 characters
Enum: "001" "004" "033" "041" "104" "136" "237"
"318" "341" "422" "745" "748" "756" "077" "208"
"755"
Código do banco
```
```
001 : Banco do Brasil
004 : Banco do Nordeste do Brasil
033 : Banco Santander
041 : Banco Banrisul
104 : Caixa Econônima Federal
136 : Banco Unicred
237 : Banco Bradesco
318 : Banco BMG
341 : Banco Itaú
422 : Banco Safra
745 : Banco Citibank
748 : Banco Sicredi
756 : Banco Sicoob
077 : Banco Inter (apenas webservice):
https://forum.casadodesenvolvedor.com.br/topic/48500-como-
solicitar-as-credenciais-para-emitir-pagamentos-pela-api-do-
inter/#comment-
208 : BTG Pactual
755 : Bank Of America
070 : Banco BRB
```
```
string <= 10 characters
Agência Mantenedora da Conta. Código adotado pelo Banco
responsável pela conta, para identificar a qual unidade está vinculada
a conta corrente.
```
```
string <= 2 characters
Dígito Verificador da Agência. Código adotado pelo Banco
responsável pela conta corrente, para verificação da autenticidade do
Código da Agência.
```
```
string <= 12 characters
Número da Conta Corrente. Número adotado pelo Banco, para
identificar univocamente a conta corrente utilizada pelo Cliente.
```
```
string <= 2 characters
Dígito Verificador da Conta. Código adotado pelo responsável pela
conta corrente, para verificação da autenticidade do Número da
Conta Corrente.
```
```
bankCode
required
```
```
agency
required
```
```
agencyDigit
```
```
accountNumber
required
```
```
accountNumberDigit
```

```
string <= 2 characters
Dígito de Autoconferência. O valor a ser informado deve ser o mesmo
que corresponde ao dígito da conta. Este campo tem como objetivo
validar e garantir a consistência dos dados da conta bancária.
```
```
number
Este campo é utilizado apenas para o banco 104-Caixa, e pode ser
chamado de operação de conta no banco em questão.
```
```
boolean
Este campo é utilizado apenas para o banco 033-Santander - campo
para informar se a conta é uma conta de pagamento ou não
```
```
number or null [ 0 .. 9999999999 ]
```
```
string <= 20 characters
Código do Convênio no Banco. Código adotado pelo Banco para
identificar o Contrato entre este e a Empresa Cliente.
```
```
number [ 0 .. 9999999999 ]
Número Sequencial do Arquivo. Informar o último número sequencial
utilizado pela geração do arquivo de remessa.
```
```
boolean
Disponível para:
```
```
077 : Banco inter
```
```
string <= 100 characters
Código do Contrato com o Banco do Brasil. Código adotado pelo
Banco para identificar o Contrato entre este e a Empresa Cliente.
```
```
boolean
Campo que define se a conta cadastrada possuirá o serviço de DDA
ativo ou não (a ativação necessita e contrato prévio com a
Tecnospeed)
```
```
string <= 100 characters
Campo clientKey fornecida pelo banco.
```
```
string <= 100 characters
Campo clientSecret fornecida pelo banco.
```
```
string <= 100 characters
Campo clientId fornecida pelo banco.
```
```
boolean
```
accountDac

accountType

accountPayment

convenioAgency

convenioNumber

remessaSequential

webservice

codeContract

ddaActived

clientKey

clientSecret

clientId

recipientNotification


```
Emissão de aviso ao Favorecido: Campo recipientNotification
disponível apenas para Banco Santender (033).
```
```
Quando campo recipientNotification for false no arquivo de remessa
ficará com o valor 0 posição 230 do segmento A para pagamentos do
tipo 41.
```
```
Quando campo recipientNotification for true no arquivo de remessa
ficará com o valor 1 posição 230 do segmento A para pagamentos do
tipo 41.
```
]

### Responses

```
201
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Request samples

```
Payload
```
```
POST /api/v1/account
```
```
application/json
```
```
Copy Expand all Collapse all
```
```
Content type
```

##### Response samples

```
201 401 422
```
```
[
```
- {
    "bankCode": "001",
    "agency": "1111",
    "agencyDigit": "",
    "accountNumber": "0001",
    "convenioAgency": "",
    "convenioNumber": "22",
    "remessaSequential": 0 ,
    "accountDac": "",
    "accountType": "",
    "accountPayment": false,
    "webservice": false,
    "codeContract": "",
    "clientKey": "",
    "clientSecret": "",
    "clientId": "",
    "recipientNotification": false
}
]

```
application/json
```
```
Copy Expand all Collapse all
{
```
- "accounts": [
    + { ... }
]
}

## Deletar conta

A documentação a seguir mostrará como **deletar** contas.

###### Observação:

```
Verificar atentamente antes de apagar uma conta
```
```
Content type
```

```
É possível enviar mais de uma conta para deletar
```
```
Contas com pagamentos conciliados não será permitido apagar
```
HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
REQUEST BODY SCHEMA: application/json

```
Array of strings non-empty unique
```
### Responses

```
200
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Request samples

```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
accountHash
required
```
```
DELETE /api/v1/account
```

```
Payload
```
##### Response samples

```
200 401 422
```
```
application/json
```
```
Copy Expand all Collapse all
{
```
- "accountHash": [
    "AAAAA",
    "BBBBB"
]
}

```
application/json
```
```
Copy Expand all Collapse all
{
```
- "accounts": [
    + { ... }
]
}

## Listar contas

A documentação a seguir mostrará como efetuar a **consulta** de contas.

Este método retornará todas as contas cadastradas pelo pagador.

HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
cnpjsh
required
```
```
Content type
```
```
Content type
```

```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
### Responses

```
200
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Response samples

```
200 401 422
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
GET /api/v1/account
```
```
application/json
```
```
Copy Expand all Collapse all
{
```
- "accounts": [
    + { ... }
]
}

```
Content type
```

## Listar contas utilizando accountHash

A documentação a seguir mostrará como efetuar a **consulta** de um conta utilizando o accountHash da
mesma.

PATH PARAMETERS

```
string
```
HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
### Responses

```
200
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
```
accountHash
required
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```

##### Response samples

```
200 401 422
```
```
GET /api/v1/account/{accountHash}
```
```
application/json
```
```
Copy Expand all Collapse all
{
```
- "accounts": {
    "bankCode": "string",
    "accountHash": "string",
    "agency": "string",
    "agencyDigit": "string",
    "accountNumber": "string",
    "accountNumberDigit": "string",
    "convenioAgency": "string",
    "convenioNumber": "string",
    "remessaSequential": 0
}
}

## Atualizar conta

A documentação a seguir mostrará como **atualizar** contas.

###### Observação:

```
É possível atualizar um parâmetro por vez.
```
PATH PARAMETERS

```
string
```
HEADER PARAMETERS

```
accountHash
required
```
```
Content type
```

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
REQUEST BODY SCHEMA: application/json

```
string [ 1 .. 3 ] characters
Enum: "001" "004" "033" "041" "104" "136" "237"
"318" "341" "422" "745" "748" "756"
Código do banco
```
```
001 : Banco do Brasil
004 : Banco do Nordeste do Brasil
033 : Banco Santander
041 : Banco Banrisul
104 : Caixa Econônima Federal
136 : Banco Unicred
237 : Banco Bradesco
318 : Banco BMG
341 : Banco Itaú
422 : Banco Safra
745 : Banco Citibank
748 : Banco Sicredi
756 : Banco Sicoob
```
```
string <= 10 characters
Agência Mantenedora da Conta. Código adotado pelo Banco
responsável pela conta, para identificar a qual unidade está vinculada a
conta corrente.
```
```
string <= 2 characters
Dígito Verificador da Agência. Código adotado pelo Banco responsável
pela conta corrente, para verificação da autenticidade do Código da
Agência.
```
```
string <= 12 characters
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
bankCode
```
```
agency
```
```
agencyDigit
```
```
accountNumber
```

```
Número da Conta Corrente. Número adotado pelo Banco, para
identificar univocamente a conta corrente utilizada pelo Cliente.
```
```
string <= 2 characters
Dígito Verificador da Conta. Código adotado pelo responsável pela
conta corrente, para verificação da autenticidade do Número da Conta
Corrente.
```
```
string <= 2 characters
Dígito de Autoconferência. O valor a ser informado deve ser o mesmo
que corresponde ao dígito da conta. Este campo tem como objetivo
validar e garantir a consistência dos dados da conta bancária.
```
```
number
Este campo é utilizado apenas para o banco 104-Caixa, e pode ser
chamado de operação de conta no banco em questão.
```
```
number or null [ 0 .. 9999999999 ]
Este campo é utilizado para informar uma outra agência vinculada ao
seu convênio atual, que deve ser preenchido caso o banco informe
alguma agência específica.
```
```
string <= 20 characters
Código do Convênio no Banco. Código adotado pelo Banco para
identificar o Contrato entre este e a Empresa Cliente.
```
```
number [ 0 .. 9999999999 ]
Número Sequencial do Arquivo. Informar o último número sequencial
utilizado pela geração do arquivo de remessa.
```
```
boolean
Campo recipientNotification disponível apenas para Banco Santender
(033).
```
```
Quando campo recipientNotification for false no arquivo de remessa
ficará com o valor 0 posição 230 do segmento A para pagamentos do
tipo 41.
```
```
Quando campo recipientNotification for true no arquivo de remessa
ficará com o valor 1 posição 230 do segmento A para pagamentos do
tipo 41.
```
### Responses

```
200
```
```
Sucesso
```
```
accountNumberDigit
```
```
accountDac
```
```
accountType
```
```
convenioAgency
```
```
convenioNumber
```
```
remessaSequential
```
```
recipientNotification
```

```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Request samples

```
Payload
```
##### Response samples

```
200 401 422
```
```
PUT /api/v1/account/{accountHash}
```
```
application/json
```
```
Copy
{
"bankCode": "001",
"agency": "1111",
"agencyDigit": "",
"accountNumber": "0001",
"convenioAgency": "",
"accountDac": "1",
"convenioNumber": "22",
"remessaSequential": "0"
}
```
```
application/json
```
```
Copy
{
"bankCode": "001",
"accountHash": "**********",
"agency": "1111",
"agencyDigit": "",
```
```
Content type
```
```
Content type
```

## Certificado

Nessa seção, você encontrará informações sobre como criar, consultar, atualizar e deletar o
certificado.

```
"accountNumber": "0001",
"accountNumberDigit": "7",
"convenioAgency": "123",
"convenioNumber": "22",
"remessaSequential": 0
}
```
## Criar certificado

A documentação a seguir mostrará como efetuar o **cadastro** de certificado.

HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com multipart/form-data. Exemplo: Content-Type:
multipart/form-data
```
REQUEST BODY SCHEMA: application/json

```
string<binary>
Certificado com extensão .pfx ou .csr.
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
file
required
```

```
string
Extensão do arquivo. Válidos: pfx e csr
```
```
string<binary>
Certificado com extensão .key. Obrigatório caso a extensão seja csr.
```
```
string
Senha do certificado .pfx. Obrigatório caso a extensão seja pfx.
```
### Responses

```
—default
```
```
Default response
```
##### Request samples

```
Payload
```
```
extension
required
```
```
key
```
```
password
```
```
POST /api/v1/account/{accountHash}/certificate
```
```
application/json
```
```
Copy
{
"expirationDate": "2023-12-06T03:00:00.000Z",
"commonName": "string",
"createdCertificate": "2024-03-06T03:00:00.000Z"
}
```
## Editar certificado

A documentação a seguir mostrará como efetuar o **editar** de certificado.

HEADER PARAMETERS

```
Content type
```

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com multipart/form-data. Exemplo: Content-Type:
multipart/form-data
```
REQUEST BODY SCHEMA: application/json

```
string<binary>
Certificado com extensão .pfx ou .csr.
```
```
string
Extensão do arquivo. Válidos: pfx e csr
```
```
string<binary>
Certificado com extensão .key. Obrigatório caso a extensão seja csr.
```
```
string
Senha do certificado .pfx. Obrigatório caso a extensão seja pfx.
```
```
boolean
Campo responsavel por ativar e desativar o certificado
```
### Responses

```
—default
```
```
Default response
```
##### Request samples

```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
file
```
```
extension
```
```
key
```
```
password
```
```
certificate
```
```
PUT /api/v1/account/{accountHash}/certificate
```

```
Payload
```
```
application/json
```
```
Copy
{
"certificate": false,
"expirationDate": "2023-12-06T03:00:00.000Z",
"commonName": "string",
"updatedCertificate": "2024-03-06T03:00:00.000Z"
}
```
## Buscar certificado

A documentação a seguir mostrará como efetuar **busca** de certificado.

PATH PARAMETERS

```
string
```
HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
### Responses

```
accountHash
required
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
Content type
```

```
200
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Response samples

```
200 401 422
```
```
GET /api/v1/account/{accountHash}/certificate
```
```
application/json
```
```
Copy
{
"active": null,
"commonName": null,
"createdCertificate": null,
"deletedCertificate": null,
"updatedCertificate": null
}
```
## Deletar certificado

A documentação a seguir mostrará como **remover** de forma permanante o certificado.

PATH PARAMETERS

```
string
```
HEADER PARAMETERS

```
accountHash
required
```
```
Content type
```

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
### Responses

```
200
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Response samples

```
200 401 422
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
DELETE /api/v1/account/{accountHash}/certificate
```
```
application/json
```
```
Copy
{
"deletedCertificate": null
```
```
Content type
```

## Pagamento

Nessa seção, você encontrará informações sobre como criar e consultar pagamentos.

Cada pedido de pagamento que for gerado irá receber um identificar único ( **uniqueID** ) e este
identificador será utilizado no momento de efetuar a geração das remessas.

É com este **uniqueId** que você também irá efetuar a consulta de seus pagamentos, para saber o
resultado do processamento bancário (quando ele ocorrer)!

```
}
```
## Títulos, concessionárias e tributos com código de barras

Nesta documentação iremos definir como será feita a geração de um pedido de pagamento de títulos,
concessionárias e tributos através de **boletos/bloquetos**.

Segmento do tipo de pagamento -> Consulte o trecho com os segmentos por tipo de pagamento.

HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
REQUEST BODY SCHEMA: application/json

```
string <= 50 characters
Hash da conta
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
accountHash
required
```

```
string <= 50 characters
Forma de pagamento. Consulte a tabela com os códigos de formas de
pagamento.
```
```
string <= 250 characters
Descrição do pagamento.
```
```
string [ 1 .. 54 ] characters
Código de barras ou linha digitável do boleto que irá realizar o pagamento.
```
```
string<date>
Data de vencimento. Formato: AAAA-MM-DD
```
```
string or (any or null)
Data do pagamento. Formato: AAAA-MM-DD
```
```
Obs: Ao informar paymentDate = null, será atribuido o próximo dia útil.
```
```
number [ 0 .. 999999999999.99 ]
Valor nominal do boleto. Há um limite em ambiente de homologação.
```
```
number [ 0 .. 999999999999.99 ]
Valor de desconto.
```
```
number [ 0 .. 999999999999.99 ]
Valor montante de Juros.
```
```
number or null [ 0 .. 999999999999.99 ]
Valor líquido. Há um limite em ambiente de homologação.
```
```
string = 2 characters
Código da Instrução para Movimento. Código adotado pelo banco, para
identificar a ação a ser realizada com o lançamento enviado no arquivo. A
forma de utilização deverá ser acordada entre banco e cliente. Disponível
apenas para os bancos BB, Bradesco, Nordeste, Safra, Santander, Sicredi e
Unicred.
```
```
string <= 250 characters
Razão Social, ou nome do Beneficiário.
```
```
string <= 14 characters
CNPJ ou CPF do Avalista/Sacador.
```
```
number
Parâmetro de transmissão.
```
```
Utilizado apenas para o banco Caixa , preencher com o número informado
pelo banco
```
paymentForm
required

```
description
```
barcode
required

dueDate
required

```
paymentDate
```
nominalAmount
required

```
discountAmount
```
```
feeAmount
```
```
amount
```
```
movimentCode
```
```
avalistaName
```
```
avalistaCpfCnpj
```
```
compromiseType
```

```
number [ 0 .. 100 ]
Parâmetro de transmissão.
```
```
Utilizado apenas para o banco Caixa , preencher com o número informado
pelo banco
```
```
object
```
```
Array of strings[ items <= 50 characters ]
Tags para a identificação do pagamento
```
### Responses

```
201
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Request samples

```
Payload
```
```
transmissionParam
```
```
beneficiary
required
```
```
tags
```
```
POST /api/v1/payment/billet
```
```
application/json
```
```
Copy Expand all Collapse all
{
"accountHash": "8MsOxGFsZh",
"description": "Pagamento de Boletos e Bloquetos",
"paymentForm": "30",
"nominalAmount": 1.5,
"paymentDate": "2019-09-20",
```
```
Content type
```

##### Response samples

```
201 401 422
```
```
"amount": 1.5,
"barcode": "34193808200000001011090000366261234123451000",
```
- "beneficiary": {
    "name": "BENEFICIARIO TESTE",
    "cpfCnpj": "13201437000135"
},
"dueDate": "2019-09-20",
- "tag": [
    "teste"
]
}

```
application/json
```
```
Copy Expand all Collapse all
{
"uniqueId": "string",
"status": "string",
"accountHash": "string",
"description": "string",
"paymentType": 0 ,
"paymentForm": "string",
"barcode": "string",
"dueDate": "2019-08-24",
"paymentDate": "2019-08-24",
"nominalAmount": 0 ,
"discountAmount": 0 ,
"feeAmount": 0 ,
"amount": 0 ,
"movimentCode": "string",
"avalistaName": "string",
"avalistaCpfCnpj": "string",
"compromiseType": 0 ,
"transmissionParam": 0 ,
```
```
Content type
```

- "beneficiary": {
    "name": "string",
    "cpfCnpj": "string",
    "bankCode": "string",
    "agency": "string",
    "agencyDigit": "string",
    "accountNumber": "string",
    "street": "string",
    "accountNumberDigit": "string",
    "accountDac": "string",
    "neighborhood": "string",
    "addressNumber": "string",
    "addressComplement": "string",
    "city": "string",
    "state": "string",
    "zipcode": "string"
},
- "tags": [
    "string"
]
}

## Transferência bancária

Nesta documentação iremos definir como será feita a geração de um pedido de pagamento via
**transferências bancárias**.

Segmento do tipo de pagamento -> Consulte o trecho com os segmentos por tipo de pagamento.

HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```

```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
REQUEST BODY SCHEMA: application/json

One of case 1 case 2

```
string <= 50 characters
Hash da conta
```
```
string <= 250 characters
Descrição do pagamento
```
```
string <= 50 characters
Value:^3
Forma de pagamento. Consulte a tabela com os códigos de formas de
pagamento.
```
```
string or (any or null)
Data do pagamento. Formato: AAAA-MM-DD
```
```
Obs: Ao informar paymentDate = null, será atribuido o próximo dia útil.
```
```
string<date>
Data do vencimento. Formato: AAAA-MM-DD
```
```
number [ 0 .. 999999999999.99 ]
Valor do pagamento. Há um limite em ambiente de homologação.
```
```
number [ 0 .. 999999999999.99 ]
Valor nominal do pagamento
```
```
number [ 0 .. 999999999999.99 ]
Valor do abatimento
```
```
number [ 0 .. 999999999999.99 ]
Valor dos juros. Este campo é apenas para fins descritivos na remessa, ele
não gera nenhum impacto no cálculo do valor nominal, portanto, caso o
título tenha esse acréscimo é necessário incluir o campo Amount já com
esse valor calculado
```
```
number [ 0 .. 999999999999.99 ]
Valor dos descontos
```
```
number [ 0 .. 999999999999.99 ]
Valor da multa. Este campo é apenas para fins descritivos na remessa, ele
não gera nenhum impacto no cálculo do valor nominal, portanto, caso o
```
```
Content-Type
required
```
```
accountHash
required
```
```
description
```
```
paymentForm
required
```
```
paymentDate
```
```
dueDate
```
```
amount
required
```
```
nominalAmount
```
```
rebateAmount
```
```
interestAmount
```
```
discountAmount
```
```
fineAmount
```

```
título tenha esse acréscimo é necessário incluir o campo Amount já com
esse valor calculado
```
```
number or null [ 0 .. 9999999999 ]
Value: 2
Código da câmara centralizadora adotado pela FEBRABAN com a função de
identificar quem será responsável pelo processamento dos pagamentos por
transferência. (Verifique o código seu banco **aqui)
```
```
string = 2 characters
Código da Instrução para Movimento. Código adotado pelo banco, para
identificar a ação a ser realizada com o lançamento enviado no arquivo. A
forma de utilização deverá ser acordada entre banco e cliente. Disponível
apenas para os bancos BB, Bradesco, Nordeste, Safra, Santander, Sicredi e
Unicred.
```
```
string
Enum: "CC" "PP"
Código Finalidade Complementar. Código adotado para complemento da
finalidade pagamento. A forma de utilização deverá ser acordada entre
banco e cliente.
```
```
number
Enum:^123
Indicador Forma Parcelamento.
```
```
1 : Data Fixa
2 : Periodicamente
3 : Dia Útil
```
```
number [ 1 .. 31 ]
Utilizado pelo Banco Caixa: Período ou Dia de Vencimento, preencher com
número desejado para o tratamento do Indicador da Forma de
Parcelamento (Para mais particularidade do campo consultar o manual
bancário)
```
```
number
Parâmetro de transmissão.
```
```
Utilizado apenas para o banco Caixa , preencher com o número informado
pelo banco
```
```
number [ 0 .. 100 ]
Parâmetro de transmissão.
```
```
Utilizado apenas para o banco Caixa , preencher com o número informado
pelo banco
```
compensation

movimentCode

complementaryCode

installmentForm

periodicDueDate

compromiseType

transmissionParam


```
object
```
```
Array of strings[ items <= 50 characters ]
Tags para a identificação do pagamento
```
### Responses

```
201
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Request samples

```
Payload
```
```
beneficiary
required
```
```
tags
```
```
POST /api/v1/payment/transfer
```
```
application/json
```
```
case 1
```
```
Copy Expand all Collapse all
{
"accountHash": "b8aKHR6tXS",
"paymentDate": "2019-11-12",
"dueDate": "2019-11-13",
"amount": 1.5,
"nominalAmount": 1.5,
"interestAmount": 0 ,
"discountAmount": 0 ,
"fineAmount": "0",
```
```
Content type
```
```
Example
```

##### Response samples

```
201 401 422
```
```
"paymentForm": 3 ,
"compensation": 2 ,
```
- "beneficiary": {
    "name": "Teste Beneficiario",
    "cpfCnpj": "38947633000184",
    "bankCode": "001",
    "agency": "1111",
    "agencyDigit": "2",
    "accountNumber": "3333",
    "accountNumberDigit": "3",
    "street": "Rua Teste",
    "neighborhood": "Bairro Teste",
    "addressNumber": "123",
    "addressComplement": "",
    "city": "Maringa",
    "state": "PR",
    "zipcode": "87000000",
    "accountType": "1",
    "transferOptions": "01"
},
- "tags": [
    "string"
]
}

```
application/json
```
```
Copy Expand all Collapse all
{
"uniqueId": "string",
"status": "string",
"accountHash": "string",
"description": "string",
"paymentForm": "string",
"paymentDate": "2019-08-24",
"dueDate": "2019-08-24",
"amount": 0 ,
"nominalAmount": 0 ,
"rebateAmount": 0 ,
"interestAmount": 0 ,
```
```
Content type
```

```
"discountAmount": 0 ,
"fineAmount": 0 ,
"compensation": 0 ,
"movimentCode": "string",
"complementaryCode": "CC",
"installmentForm": 0 ,
"periodicDueDate": 0 ,
"compromiseType": 0 ,
"transmissionParam": 0 ,
```
- "beneficiary": {
    "name": "string",
    "cpfCnpj": "string",
    "bankCode": "string",
    "agency": "string",
    "agencyDigit": "string",
    "accountOperation": "string",
    "accountNumber": "string",
    "accountNumberDigit": "string",
    "street": "string",
    "neighborhood": "string",
    "addressNumber": "string",
    "addressComplement": "string",
    "city": "string",
    "state": "string",
    "zipcode": "string",
    "accountType": 0 ,
    "transferOptions": 0
},
- "tags": [
    "string"
]
}

## Salários

Nesta documentação iremos definir como será feita a geração de um pedido de pagamento de
**salários**.

Segmento do tipo de pagamento -> Consulte o trecho com os segmentos por tipo de pagamento.

HEADER PARAMETERS


```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
REQUEST BODY SCHEMA: application/json

```
string <= 50 characters
Hash da conta
```
```
string <= 250 characters
Descrição do pagamento
```
```
string <= 50 characters
Forma de pagamento. Consulte a tabela com os códigos de formas de
pagamento.
```
```
string<date>
Data do pagamento. Formato: AAAA-MM-DD
```
```
string<date>
Data do vencimento. Formato: AAAA-MM-DD
```
```
number
Valor do pagamento. Há um limite em ambiente de homologação.
```
```
number
Valor do abatimento
```
```
number
Valor dos juros. Este campo é apenas para fins descritivos na remessa, ele
não gera nenhum impacto no cálculo do valor nominal, portanto, caso o
título tenha esse acréscimo é necessário incluir o campo Amount já com
esse valor calculado
```
```
number
Valor dos descontos
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
accountHash
required
```
```
description
```
```
paymentForm
required
```
```
paymentDate
required
```
```
dueDate
required
```
```
amount
required
```
```
rebateAmount
```
```
interestAmount
```
```
discountAmount
```

```
number
Valor da multa. Este campo é apenas para fins descritivos na remessa, ele
não gera nenhum impacto no cálculo do valor nominal, portanto, caso o
título tenha esse acréscimo é necessário incluir o campo Amount já com
esse valor calculado
```
```
string = 2 characters
Código da Instrução para Movimento. Código adotado pelo banco, para
identificar a ação a ser realizada com o lançamento enviado no arquivo. A
forma de utilização deverá ser acordada entre banco e cliente. Disponível
apenas para os bancos BB, Bradesco, Nordeste, Safra, Santander, Sicredi e
Unicred.
```
```
string
Enum: "CC" "PP"
Código Finalidade Complementar. Código adotado para complemento da
finalidade pagamento. A forma de utilização deverá ser acordada entre
banco e cliente.
```
```
number
Parâmetro de transmissão.
```
```
Utilizado apenas para o banco Caixa , preencher com o número informado
pelo banco
```
```
number [ 0 .. 100 ]
Parâmetro de transmissão.
```
```
Utilizado apenas para o banco Caixa , preencher com o número informado
pelo banco
```
```
object
```
```
Array of strings[ items <= 50 characters ]
Tags para a identificação do pagamento
```
### Responses

```
201
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
fineAmount
```
```
movimentCode
```
```
complementaryCode
```
```
compromiseType
```
```
transmissionParam
```
```
beneficiary
required
```
```
tags
```

```
422
```
```
Invalid Param
```
##### Request samples

```
Payload
```
```
POST /api/v1/payment/paycheck
```
```
application/json
```
```
Copy Expand all Collapse all
{
"accountHash": "b8aKHR6tXS",
"paymentDate": "2019-11-12",
"dueDate": "2019-11-12",
"paymentForm": 41 ,
"amount": 1.5,
"interestAmount": 0 ,
"discountAmount": 0 ,
"fineAmount": "0",
```
- "beneficiary": {
    "name": "Teste Beneficiario",
    "cpfCnpj": "38947633000184",
    "bankCode": "001",
    "agency": "1111",
    "agencyDigit": "2",
    "accountNumber": "3333",
    "accountNumberDigit": "3",
    "neighborhood": "Rua Teste",
    "addressNumber": "123",
    "addressComplement": "",
    "city": "Maringa",
    "state": "PR",
    "zipcode": "87000000",
    "accountType": "1",
    "transferOptions": "01"
},

```
Content type
```

##### Response samples

```
201 401 422
```
- "tags": [
    "string"
]
}

```
application/json
```
```
Copy Expand all Collapse all
{
"uniqueId": "string",
"status": "string",
"accountHash": "string",
"description": "string",
"paymentForm": "string",
"paymentDate": "2019-08-24",
"amount": 0 ,
"rebateAmount": 0 ,
"interestAmount": 0 ,
"discountAmount": 0 ,
"fineAmount": 0 ,
"movimentCode": "string",
"complementaryCode": "CC",
"compromiseType": 0 ,
"transmissionParam": 0 ,
"transferOptions": 0 ,
```
```
Content type
```

- "beneficiary": {
    "name": "string",
    "cpfCnpj": "string",
    "bankCode": "string",
    "agency": "string",
    "agencyDigit": "string",
    "accountNumber": "string",
    "accountNumberDigit": "string",
    "street": "string",
    "neighborhood": "string",
    "addressNumber": "string",
    "addressComplement": "string",
    "city": "string",
    "state": "string",
    "zipcode": "string"
},
- "tags": [
    "string"
]
}

## GARE

Nesta documentação iremos definir como será feita a geração de um pedido de pagamento da **GARE
(Guia de Arrecadação da Receita Estadual)**.

Segmento do tipo de pagamento -> Consulte o trecho com os segmentos por tipo de pagamento.

HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```

```
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
REQUEST BODY SCHEMA: application/json

```
string <= 50 characters
Hash da conta cadastrada
```
```
string <= 6 characters
Código de pagamento
```
```
string<date>
Data do pagamento. Formato: AAAA-MM-DD
```
```
string<date>
Data de vencimento Formato: AAAA-MM-DD
```
```
number
Valor do pagamento. Há um limite em ambiente de homologação.
```
```
string <= 250 characters
Descrição do pagamento
```
```
string <= 20 characters
Identificação do contribuinte
```
```
string <= 12 characters
Número da Inscrição Estadual / Código do Município / Número
Declaração
```
```
string <= 20 characters
Código da Dívida Ativa / Número da Etiqueta do Tributo
```
```
string <= 7 characters
Período de referência Formato: AAAA-MM
```
```
string <= 50 characters
Número da Parcela / Notificação do Tributo
```
```
number
Valor dos juros. Este campo é apenas para fins descritivos na remessa,
ele não gera nenhum impacto no cálculo do valor nominal, portanto, caso
o título tenha esse acréscimo é necessário incluir o campo Amount já
com esse valor calculado
```
```
number
Valor da multa. Este campo é apenas para fins descritivos na remessa,
ele não gera nenhum impacto no cálculo do valor nominal, portanto, caso
```
```
accountHash
required
```
```
revenueCode
```
```
paymentDate
required
```
```
dueDate
required
```
```
amount
required
```
```
description
```
```
contributorDocument
required
```
```
stateRegistration
required
```
```
activeDebit
required
```
```
referencePeriod
required
```
```
installment
required
```
```
interestAmount
```
```
fineAmount
```

```
o título tenha esse acréscimo é necessário incluir o campo Amount já
com esse valor calculado
```
```
number
Valor Nominal do documento, expresso em moeda corrente. Há um limite
em ambiente de homologação.
```
```
number
Valor de Acréscimo
```
```
number
Valor de Honorários
```
```
Array of strings[ items <= 50 characters ]
Tags para a identificação do pagamento
```
### Responses

```
201
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Request samples

```
Payload
```
```
nominalAmount
required
```
```
increaseAmount
```
```
honoraryAmount
```
```
tags
```
```
POST /api/v1/payment/taxes/gare
```
```
application/json
```
```
Copy Expand all Collapse all
{
"accountHash": "b8aKHR6tXS",
```
```
Content type
```

##### Response samples

```
201 401 422
```
```
"revenueCode": "1234",
"paymentDate": "2019-12-20",
"amount": 1.5,
"description": "Pagamento da GARE Teste",
"contributorDocument": "93848368005",
"dueDate": "2019-12-20",
"stateRegistration": "PR",
"activeDebit": "123",
"referencePeriod": "2019-12",
"installment": "01",
"interestAmount": 0 ,
"fineAmount": 0 ,
"nominalAmount": 1.5,
"increaseAmount": 2.5,
"honoraryAmount": 6.5,
```
- "tags": [
    "teste"
]
}

```
application/json
```
```
Copy Expand all Collapse all
{
"uniqueId": "string",
"status": "string",
"accountHash": "string",
"paymentType": 0 ,
"revenueCode": "string",
"paymentDate": "2019-08-24",
"dueDate": "2019-08-24",
"description": "string",
"contributorDocument": "string",
"amount": 0 ,
"stateRegistration": "string",
"activeDebit": "string",
"referencePeriod": "string",
"installment": "string",
"interestAmount": 0 ,
"fineAmount": 0 ,
```
```
Content type
```

```
"nominalAmount": 0 ,
```
- "tags": [
    "string"
]
}

## IPVA

Nesta documentação iremos definir como será feita a geração de um pedido de pagamento do **IPVA**.

Segmento do tipo de pagamento -> Consulte o trecho com os segmentos por tipo de pagamento.

HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
REQUEST BODY SCHEMA: application/json

```
string <= 50 characters
Hash da conta cadastrada
```
```
string <= 6 characters
Código de pagamento
```
```
string<date>
Data do pagamento. Formato: AAAA-MM-DD
```
```
string <= 20 characters
Preencher com o documento de identificação do contribuinte. Exemplo:
Número do NIT/PIS/PASEP do contribuinte.
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
accountHash
required
```
```
revenueCode
```
```
paymentDate
required
```
```
contributorDocument
required
```

```
string <= 250 characters
Descrição do pagamento
```
```
number
Valor nominal do pagamento. Há um limite em ambiente de
homologação.
```
```
string <= 200 characters
Nome do contribuinte
```
```
string <= 4 characters
Ano de cálculo
```
```
number
Valor do pagamento. Há um limite em ambiente de homologação.
```
```
string<date>
Data de vencimento Formato: AAAA-MM-DD
```
```
string <= 7 characters
Código do município (padrão IBGE)
```
```
number
Valor do desconto
```
```
string = 2 characters
Sigla da UF (2 caracteres)
```
```
string <= 8 characters
Placa do veículo. Formato XXX9999 (Ex.: ABC1234)
```
```
number
Enum: 1 2 3 4 5 6 7 8
Opção de pagamento. Pode ser:
```
```
1 - Parcela Única com Desconto
2 - Parcela Única sem Desconto
3 - Parcela Nº 1
4 - Parcela Nº 2
5 - Parcela Nº 3
6 - Parcela Nº 4
7 - Parcela Nº 5
8 - Parcela Nº 6
```
```
string <= 20 characters
Renavam do veículo
```
```
number
```
```
description
```
nominalAmount
required

contributorName
required

calculationYear
required

amount
required

dueDate
required

municipalCode
required

discountAmount
required

state
required

vehiclePlates
required

paymentOption
required

vehicleRenavam
required

```
CRVLWithdrawalOption
```

```
Enum:^12
Opção de Retirada do CRVL.
```
```
Pode ser:
```
```
1 - Correio
2 - DETRAN / CIRETRAN
```
```
Array of strings[ items <= 50 characters ]
Tags para identificação do pagamento
```
### Responses

```
201
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Request samples

```
Payload
```
```
tags
```
```
POST /api/v1/payment/taxes/ipva
```
```
application/json
```
```
Copy Expand all Collapse all
{
"accountHash": "b8aKHR6tXS",
"revenueCode": "1234",
"paymentDate": "2019-12-20",
"contributorDocument": "93848368005",
"description": "Teste de IPVA",
"nominalAmount": 1.5,
```
```
Content type
```

##### Response samples

```
201 401 422
```
```
"contributorName": "João Teste",
"calculationYear": "2019",
"amount": "1.50",
"dueDate": "2019-12-20",
"municipalCode": "4115200",
"discountAmount": 0 ,
"state": "PR",
"vehiclePlates": "AAA0000",
"paymentOption": 1 ,
"vehicleRenavam": "aaaaaaaaaa",
"CRVLWithdrawalOption": 1 ,
```
- "tags": [
    "teste"
]
}

```
application/json
```
```
Copy Expand all Collapse all
{
"uniqueId": "string",
"status": "string",
"paymentType": 0 ,
"accountHash": "string",
"revenueCode": "string",
"paymentDate": "2019-08-24",
"contributorDocument": "string",
"description": "string",
"nominalAmount": 0 ,
"contributorName": "string",
"calculationYear": "string",
"amount": 0 ,
"dueDate": "2019-08-24",
"municipalCode": "string",
"discountAmount": 0 ,
"state": "st",
"vehiclePlates": "string",
"paymentOption": 1 ,
"vehicleRenavam": "string",
"CRVLWithdrawalOption": 1 ,
```
```
Content type
```

- "tags": [
    "string"
]
}

## DPVAT

Nesta documentação iremos definir como será feita a geração de um pedido de pagamento do
**DPVAT**.

Segmento do tipo de pagamento -> Consulte o trecho com os segmentos por tipo de pagamento.

HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
REQUEST BODY SCHEMA: application/json

```
string <= 50 characters
Hash da conta cadastrada
```
```
string <= 6 characters
Código de pagamento
```
```
string<date>
Data do pagamento Formato: AAAA-MM-DD
```
```
string <= 250 characters
Descrição do pagamento
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
accountHash
required
```
```
revenueCode
```
```
paymentDate
required
```
```
description
```

```
string <= 20 characters
Preencher com o documento de identificação do contribuinte. Exemplo:
Número do NIT/PIS/PASEP do contribuinte.
```
```
number
Valor nominal do pagamento. Há um limite em ambiente de
homologação.
```
```
string <= 200 characters
Nome do contribuinte
```
```
string <= 4 characters
Ano de cálculo
```
```
number
Valor do pagamento. Há um limite em ambiente de homologação.
```
```
string<date>
Data de vencimento. Formato: AAAA-MM-DD
```
```
string <= 7 characters
Código do município (padrão IBGE)
```
```
number
Valor do desconto
```
```
string <= 250 characters
Nome da cidade
```
```
string <= 2 characters
Sigla da UF (2 caracteres)
```
```
string <= 8 characters
Placa do veículo. Formato XXX9999 (Ex.: ABC1234)
```
```
number
Enum:^12345678
Opção de pagamento. Pode ser:
```
```
1 - Parcela Única com Desconto
2 - Parcela Única sem Desconto
3 - Parcela Nº 1
4 - Parcela Nº 2
5 - Parcela Nº 3
6 - Parcela Nº 4
7 - Parcela Nº 5
8 - Parcela Nº 6
```
contributorDocument
required

nominalAmount
required

contributorName
required

calculationYear
required

amount
required

dueDate
required

municipalCode
required

discountAmount
required

city
required

state
required

vehiclePlates
required

paymentOption
required


```
string <= 20 characters
Renavam do veículo
```
```
number
Enum:^12
Opção de Retirada do CRVL.
```
```
Pode ser:
```
```
1 - Correio
2 - DETRAN / CIRETRAN
```
```
Array of strings[ items <= 50 characters ]
Tags para identificação do pagamento
```
### Responses

```
201
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Request samples

```
Payload
```
```
vehicleRenavam
required
```
```
CRVLWithdrawalOption
required
```
```
tags
```
```
POST /api/v1/payment/taxes/dpvat
```
```
application/json
```
```
Copy Expand all Collapse all
{
"accountHash": "b8aKHR6tXS",
"paymentDate": "2019-12-20",
```
```
Content type
```

##### Response samples

```
201 401 422
```
```
"contributorDocument": "93848368005",
"description": "Teste de DPVAT",
"nominalAmount": 1.5,
"contributorName": "João Teste",
"calculationYear": "2019",
"amount": "1.50",
"dueDate": "2019-12-20",
"municipalCode": "4115200",
"discountAmount": 0 ,
"state": "PR",
"vehiclePlates": "AAA0000",
"paymentOption": 1 ,
"vehicleRenavam": "aaaaaaaaaa",
"CRVLWithdrawalOption": 1 ,
```
- "tags": [
    "teste"
]
}

```
application/json
```
```
Copy Expand all Collapse all
{
"uniqueId": "string",
"status": "string",
"paymentType": 0 ,
"accountHash": "string",
"revenueCode": "string",
"paymentDate": "2019-08-24",
"description": "string",
"contributorDocument": "string",
"nominalAmount": 0 ,
"contributorName": "string",
"calculationYear": "string",
"amount": 0 ,
"dueDate": "2019-08-24",
"municipalCode": "string",
"discountAmount": 0 ,
"city": "string",
"state": "string",
```
```
Content type
```

```
"vehiclePlates": "string",
"paymentOption": 0 ,
"vehicleRenavam": "string",
"CRVLWithdrawalOption": 0 ,
```
- "tags": [
    "string"
]
}

## DARF

Nesta documentação iremos definir como será feita a geração de um pedido de pagamento da **DARF**.

Segmento do tipo de pagamento -> Consulte o trecho com os segmentos por tipo de pagamento.

HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
REQUEST BODY SCHEMA: application/json

```
string <= 50 characters
Hash da conta cadastrada
```
```
string <= 6 characters
Código de pagamento. Obrigatório para o banco 237 - Bradesco
```
```
string<date>
Data do pagamento. Formato: AAAA-MM-DD
```
```
string <= 20 characters
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
accountHash
required
```
```
revenueCode
```
```
paymentDate
required
```
```
contributorDocument
```

```
Número do documento do contribuinte
```
```
string <= 250 characters
Descrição do pagamento
```
```
string<date>
Período de apuração. Formato: AAAA-MM-DD
```
```
string<date>
Período de referência. Formato: AAAA-MM-DD
```
```
string <= 30 characters
Número de referência do tributo
```
```
number
Valor dos juros. Este campo é apenas para fins descritivos na remessa,
ele não gera nenhum impacto no cálculo do valor nominal, portanto, caso
o título tenha esse acréscimo é necessário incluir o campo Amount já
com esse valor calculado
```
```
number
Valor da multa. Este campo é apenas para fins descritivos na remessa,
ele não gera nenhum impacto no cálculo do valor nominal, portanto, caso
o título tenha esse acréscimo é necessário incluir o campo Amount já
com esse valor calculado
```
```
number
Valor principal. Há um limite em ambiente de homologação.
```
```
string<date>
Data de vencimento. Formato: AAAA-MM-DD
```
```
Array of strings[ items <= 50 characters ]
Tags para identificação do pagamento
```
```
number
Taxa
```
```
number
Outros valores
```
```
string
Nome do contribuinte
```
```
string = 2 characters
Código da Instrução para Movimento. Código adotado pelo banco, para
identificar a ação a ser realizada com o lançamento enviado no arquivo. A
forma de utilização deverá ser acordada entre banco e cliente. Disponível
```
required

```
description
```
```
reportingPeriod
```
referencePeriod
required

referenceNumber
required

```
interestAmount
```
```
fineAmount
```
nominalAmount
required

dueDate
required

```
tags
```
```
feeAmount
```
```
otherAmount
```
contributorName
required

movimentCode


```
apenas para os bancos BB, Bradesco, Nordeste, Safra, Santander, Sicredi
e Unicred.
```
### Responses

```
201
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Request samples

```
Payload
```
```
POST /api/v1/payment/taxes/darf
```
```
application/json
```
```
Copy Expand all Collapse all
{
"accountHash": "b8aKHR6tXS",
"revenueCode": "1234",
"referencePeriod": "2019-12-01",
"reportingPeriod": "2019-12-20",
"paymentDate": "2019-12-20",
"dueDate": "2019-12-20",
"description": "Pagamento da DARF Teste",
"contributorDocument": "93848368005",
"contributorName": "Teste homologacao",
"referenceNumber": "123",
"interestAmount": 1 ,
"fineAmount": 0 ,
"nominalAmount": 0 ,
```
```
Content type
```

##### Response samples

```
201 401 422
```
```
"movimentCode": "09",
```
- "tags": [
    "teste"
]
}

```
application/json
```
```
Copy Expand all Collapse all
{
"uniqueId": "string",
"status": "string",
"paymentType": 0 ,
"accountHash": "string",
"revenueCode": "string",
"paymentDate": "2019-08-24",
"contributorDocument": "string",
"description": "string",
"reportingPeriod": "string",
"referencePeriod": "string",
"referenceNumber": "string",
"interestAmount": 0 ,
"fineAmount": 0 ,
"nominalAmount": 0 ,
"dueDate": "2019-08-24",
```
- "tags": [
    "string"
],
"feeAmount": 0 ,
"otherAmount": 0 ,
"contributorName": "string",
"movimentCode": "st"
}

## GPS

```
Content type
```

Nesta documentação iremos definir como será feita a geração de um pedido de pagamento da **GPS
(Guia de previdência social)**.

Segmento do tipo de pagamento -> Consulte o trecho com os segmentos por tipo de pagamento.

HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
REQUEST BODY SCHEMA: application/json

```
string <= 50 characters
Hash da conta
```
```
string
Value: "17"
Forma de pagamento. Consulte a tabela com os códigos de formas de
pagamento.
```
```
string <= 6 characters
Código de pagamento (consulte na página de cálculo da GPS a sua
categoria).
```
```
string <= 20 characters
Preencher com o documento de identificação do contribuinte. Exemplo:
Número do NIT/PIS/PASEP do contribuinte.
```
```
string<date>
Data de pagamento. Formato AAAA-MM-DD
```
```
number
Valor do Tributo. Valor previsto do pagamento do INSS. Há um limite em
ambiente de homologação.
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
accountHash
required
```
```
paymentForm
required
```
```
revenueCode
```
```
contributorDocument
required
```
```
paymentDate
required
```
```
amount
required
```

```
string <= 250 characters
Descrição do pagamento
```
```
string <= 7 characters
Período de referência/competência (ano/mês de referência do
recolhimento). Formato AAAA-MM
```
```
number
Valor de Outras Entidades. Valor somado ao valor do documento.
```
```
number
Outros valores
```
```
number
Valor da atualização Monetária.
```
```
Array of strings[ items <= 50 characters ]
Tags para identificação do pagamento
```
### Responses

```
201
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Request samples

```
Payload
```
```
description
```
```
referencePeriod
required
```
```
taxAmount
required
```
```
otherAmount
```
```
monetaryAdjustment
required
```
```
tags
```
```
POST /api/v1/payment/taxes/gps
```
```
application/json
```
```
Content type
```

##### Response samples

```
201 401 422
```
```
Copy Expand all Collapse all
{
"accountHash": "b8aKHR6tXS",
"revenueCode": "1234",
"paymentForm": "17",
"contributorDocument": "93848368005",
"paymentDate": "2019-12-20",
"amount": 1.5,
"nominalAmount": "1.50",
"description": "Teste GPS",
"referencePeriod": "122019",
"taxAmount": "0",
"otherAmount": 0 ,
"monetaryAdjustment": 0 ,
```
- "tags": [
    "string"
]
}

```
application/json
```
```
Copy Expand all Collapse all
{
"uniqueId": "string",
"status": "string",
"paymentType": 0 ,
"accountHash": "string",
"paymentForm": "string",
"revenueCode": "string",
"contributorDocument": "string",
"paymentDate": "2019-08-24",
"amount": 0 ,
"description": "string",
"referencePeriod": "string",
"taxAmount": 0 ,
"otherAmount": 0 ,
"monetaryAdjustment": 0 ,
```
- "tags": [
    "string"
]

```
Content type
```

```
}
```
## FGTS

Nesta documentação iremos definir como será feita a geração de um pedido de pagamento de **FGTS**.

Para auxiliar no preenchimento dos campos, clique aqui para visualizar o exemplo.

Forma de pagamento disponível apenas para o **Banco do Brasil** , **Itaú** e **Santander**.

Segmento do tipo de pagamento -> Consulte o trecho com os segmentos por tipo de pagamento.

HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
REQUEST BODY SCHEMA: application/json

```
string <= 50 characters
Hash da conta
```
```
string
Value: "35"
Forma de pagamento. Consulte a tabela com os códigos de
formas de pagamento.
```
```
string <= 250 characters
Descrição do pagamento.
```
```
string [ 1 .. 54 ] characters
Código de barras ou linha digitável do boleto que irá realizar o
pagamento.
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
accountHash
required
```
```
paymentForm
required
```
```
description
```
```
barcode
required
```

```
string<date>
Data do pagamento. Formato: AAAA-MM-DD
```
```
string<date>
Data do vencimento. Formato: AAAA-MM-DD
```
```
number [ 0 .. 999999999999.99 ]
Valor líquido. Há um limite em ambiente de homologação.
```
```
string <= 20 characters
Identificação do contribuinte
```
```
string <= 200 characters
Nome do contribuinte
```
```
string <= 6 characters
Código de pagamento
```
```
string <= 16 characters
Identificador do FGTS
```
```
Até 16 é pra informar, passou de 16 para o banco Itau, não
informar.
```
```
number [ 0 .. 999999999 ]
Lacre de Conectividade Social
```
```
number [ 0 .. 99 ]
Dígito do Lacre de Conectividade Social
```
```
Array of strings[ items <= 50 characters ]
Tags para a identificação do pagamento
```
### Responses

```
201
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
paymentDate
required
```
```
dueDate
```
```
amount
required
```
```
contributorDocument
required
```
```
contributorName
required
```
```
revenueCode
required
```
```
fgtsIdentifier
required
```
```
sealSocialConnectivity
required
```
```
sealSocialConnectivityDigit
required
```
```
tags
```

```
422
```
```
Invalid Param
```
##### Request samples

```
Payload
```
##### Response samples

```
201 401 422
```
```
POST /api/v1/payment/taxes/fgts
```
```
application/json
```
```
Copy Expand all Collapse all
{
"accountHash": "string",
"paymentForm": "35",
"description": "string",
"barcode": "string",
"paymentDate": "2019-08-24",
"dueDate": "2019-08-24",
"amount": 999999999999.99,
"contributorDocument": "string",
"contributorName": "string",
"revenueCode": "string",
"fgtsIdentifier": "string",
"sealSocialConnectivity": 999999999 ,
"sealSocialConnectivityDigit": 99 ,
```
- "tags": [
    "string"
]
}

```
application/json
```
```
Copy Expand all Collapse all
```
```
Content type
```
```
Content type
```

```
{
"uniqueId": "string",
"status": "string",
"paymentType": 0 ,
"accountHash": "string",
"paymentForm": "string",
"description": "string",
"barcode": "string",
"paymentDate": "2019-08-24",
"dueDate": "2019-08-24",
"amount": 0 ,
"contributorDocument": "string",
"contributorName": "string",
"revenueCode": "string",
"fgtsIdentifier": "string",
"sealSocialConnectivity": 0 ,
"sealSocialConnectivityDigit": 0 ,
```
- "tags": [
    "string"
]
}

## Diversos

Nesta documentação iremos definir como será feita a geração de um pedido de pagamento definido
pelo banco como sendo do tipo **"Diversos"**.

Segmento do tipo de pagamento -> Consulte o trecho com os segmentos por tipo de pagamento.

HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
```

```
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
REQUEST BODY SCHEMA: application/json

```
string <= 50 characters
Hash da conta
```
```
string <= 250 characters
Descrição do pagamento
```
```
string <= 50 characters
Forma de pagamento. Consulte a tabela com os códigos de formas
de pagamento.
```
```
string<date>
Data do pagamento. Formato: AAAA-MM-DD
```
```
string
Campo utilizado para remessas de PIX, e realiza a indicação do tipo
de pagamento, caso não preenchido, o campo vai com o código 98
(diversos) na remessa. Alguns bancos podem solicitar o
preenchimento do campo como 20 (Pagamento a fornecedores)
também, e a confirmação de como o campo deve ser preenchido
depende de conta para conta e pode ser confirmado com sua
instituição financeira.
```
```
string<date>
Data de vencimento. Formato: AAAA-MM-DD
```
```
number
Valor do pagamento. Há um limite em ambiente de homologação.
```
```
number
Valor do abatimento
```
```
number
Valor dos juros. Este campo é apenas para fins descritivos na
remessa, ele não gera nenhum impacto no cálculo do valor nominal,
portanto, caso o título tenha esse acréscimo é necessário incluir o
campo Amount já com esse valor calculado
```
```
number
Valor dos descontos
```
```
number
Valor da multa. Este campo é apenas para fins descritivos na
remessa, ele não gera nenhum impacto no cálculo do valor nominal,
```
```
required
```
```
accountHash
required
```
```
description
```
```
paymentForm
required
```
```
paymentDate
required
```
```
paymentType
```
```
dueDate
```
```
amount
required
```
```
rebateAmount
```
```
interestAmount
```
```
discountAmount
```
```
fineAmount
```

```
portanto, caso o título tenha esse acréscimo é necessário incluir o
campo Amount já com esse valor calculado
```
```
number or null [ 0 .. 9999999999 ]
Código da câmara centralizadora adotado pela FEBRABAN com a
função de identificar quem será responsável pelo processamento dos
pagamentos por transferência. (Verifique o código seu banco **aqui)
```
```
string = 2 characters
Código da Instrução para Movimento. Código adotado pelo banco,
para identificar a ação a ser realizada com o lançamento enviado no
arquivo. A forma de utilização deverá ser acordada entre banco e
cliente. Disponível apenas para os bancos BB, Bradesco, Nordeste,
Safra, Santander, Sicredi e Unicred.
```
```
string
Enum: "CC" "PP"
Código Finalidade Complementar. Código adotado para complemento
da finalidade pagamento. A forma de utilização deverá ser acordada
entre banco e cliente.
```
```
number
Parâmetro de transmissão.
```
```
Utilizado apenas para o banco Caixa , preencher com o número
informado pelo banco
```
```
number [ 0 .. 100 ]
Parâmetro de transmissão.
```
```
Utilizado apenas para o banco Caixa , preencher com o número
informado pelo banco
```
```
number
Enum: "01" "02" "03" "04" "05"
Onde:
```
```
01 - Telefone
02 - E-mail
03 - CPF/CNPJ
04 - Chave Aleatória
05 - Dados Bancários (apenas para os bancos Sicredi e Itaú
(Para o Itaú será necesário informar o campo
registrationComplement))
Define o tipo da chave PIX (utilizado apenas para o paymentForm
45 dos bancos Itaú, Sicredi e Bradesco)
```
```
string <= 99 characters
```
compensation

movimentCode

complementaryCode

compromiseType

transmissionParam

pixType

pixKey


```
Chave PIX (utilizado apenas para o paymentForm 45 dos bancos Itaú
e Bradesco)
```
```
string <= 99 characters
Informar o Código do ISPB (Identificador de Sistema de Pagamento
Brasileiro) do banco de destino. (Utilizado para o paymentForm 45 do
banco Bradesco e Itau)
```
```
number
Enum: "01" "02" "03" "04"
Onde:
```
```
01 - Conta corrente
02 - Conta Pagamento
03 - Conta Poupança
04 - Chave pix ( Disponível apenas para o banco Itaú)
```
```
Complemento do Registro (utilizado apenas para o paymentForm 45
do banco Bradesco e Itaú)
```
```
string <= 77 characters
URL do PSP do recebedor / CHAVE DE ENDEREÇAMENTO, também
conhecido como LOCATION (utilizado apenas para o paymentForm 47
do banco Itaú e Bradesco). Campo obrigatório, quando se tratar de QR
CODE dinâmico / personalizado, deve ser informada a URL capturada
a partir da leitura do QR CODE. A URL deverá ser informada sem o
“https://”. Exemplo:
pix.example.com/8b3da2f39a4140d1a91abd93113bd441 ,
qrpix-h.bradesco.com.br/9d36b84f-c70b-478f-b95c-
12729b90ca25.
```
```
string <= 32 characters
CÓDIGO DE IDENTIFICAÇÃO DO QR-CODE (utilizado apenas para o
paymentForm 47 do banco Itaú e Bradesco)
```
```
xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
```
```
object
Para pagamentos através de DOC, TED, PIX Transferência e Ordem de
Pagamento, por determinação do BACEN é obrigatória a identificação
do CNPJ/CPF do favorecido
```
```
Array of strings[ items <= 50 characters ]
Tags para a identificação do pagamento
```
### Responses

```
ispbCode
```
```
registrationComplement
```
```
pixUrl
```
```
pixTxid
```
```
beneficiary
required
```
```
tags
```

```
201
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Request samples

```
Payload
```
```
POST /api/v1/payment/various
```
```
application/json
```
```
Copy Expand all Collapse all
{
"accountHash": "b8aKHR6tXS",
"paymentDate": "2019-11-12",
"paymentForm": "1",
"amount": 1.5,
"interestAmount": 0 ,
"discountAmount": 0 ,
"fineAmount": "0",
```
```
Content type
```

##### Response samples

```
201 401 422
```
- "beneficiary": {
    "name": "Teste Beneficiario",
    "cpfCnpj": "38947633000184",
    "bankCode": "001",
    "agency": "1111",
    "agencyDigit": "2",
    "accountNumber": "3333",
    "accountNumberDigit": "3",
    "neighborhood": "Rua Teste",
    "addressNumber": "123",
    "addressComplement": "",
    "city": "Maringa",
    "state": "PR",
    "zipcode": "87000000"
},
- "tags": [
    "string"
]
}

```
application/json
```
```
Copy Expand all Collapse all
{
"uniqueId": "string",
"status": "string",
"accountHash": "string",
"description": "string",
"paymentForm": "string",
"paymentDate": "2019-08-24",
"dueDate": "2019-08-24",
"amount": 0 ,
"rebateAmount": 0 ,
"interestAmount": 0 ,
"discountAmount": 0 ,
"fineAmount": 0 ,
"compensation": 0 ,
"movimentCode": "st",
"complementaryCode": "CC",
"compromiseType": 0 ,
```
```
Content type
```

```
"transmissionParam": 0 ,
```
- "beneficiary": {
    "name": "string",
    "cpfCnpj": "string",
    "bankCode": "string",
    "agency": "string",
    "agencyDigit": "string",
    "accountNumber": "string",
    "accountNumberDigit": "string",
    "street": "string",
    "neighborhood": "string",
    "addressNumber": "string",
    "addressComplement": "string",
    "city": "string",
    "state": "string",
    "zipcode": "string"
},
- "tags": [
    "string"
]
}

## Consultar os pagamentos gerados

Depois de gerar os pagamentos, e obter o **uniqueId** , você pode consultar os pagamentos gerados para
saber o resultado do processamento pela nossa API.

Também, será por esta rota que você consultará os uniqueIds para saber qual o resultado do
processamento dos boletos após serem conciliados pelo retorno gerado pelo banco.

**OBSERVAÇÕES:**

Campos como **occurrenceDate** , **createdAt** e **updatedAt** possuem uma definição de fuso horário GMT
-3.

Nesse caso, leve em consideração essa diferença!

QUERY PARAMETERS

```
string
Identificador único do pagamento gerado no passos anteriores (rotas POST)
```
HEADER PARAMETERS

```
uniqueId
```

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
### Responses

```
200
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Response samples

```
200 401 422
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
GET /api/v1/payment?uniqueId=
```
```
application/json
```
```
Copy Expand all Collapse all
{
"uniqueId": "string",
"status": "string",
```
```
Content type
```

```
"accountHash": "string",
"paymentType": "string",
"paymentForm": "string",
"description": "string",
"compensation": 0 ,
"avalistaName": "string",
"avalistaCpfCnpj": "string",
"compromiseType": 0 ,
"transmissionParam": "string",
"installmentForm": "string",
"periodicDueDate": "string",
"pixKey": "string",
"pixType": "string",
"pixUrl": "string",
"pixTxid": "string",
"ispbCode": "string",
"registrationComplement": "string",
```
- "beneficiary": {
    "name": "string",
    "cpfCnpj": "string",
    "bankCode": "string",
    "agency": "string",
    "agencyDigit": "string",
    "accountOperation": "string",
    "accountNumber": "string",
    "street": "string",
    "accountNumberDigit": "string",
    "accountDac": "string",
    "neighborhood": "string",
    "addressNumber": "string",
    "addressComplement": "string",
    "city": "string",
    "state": "string",
    "zipcode": "string"
},
"paymentDate": "2019-08-24",
"dueDate": "2019-08-24",
"effectiveDate": "2019-08-24",
"feeAmount": 0 ,
"amount": 0 ,
"nominalAmount": 0 ,
"fineAmount": 0 ,
"interestAmount": 0 ,
"ourNumber": "string",
"barcode": "string",


```
"digitableLine": "string",
"discountAmount": 0 ,
"contributorDocument": "string",
"contributorName": "string",
"stateRegistration": "string",
"activeDebit": "string",
"revenueCode": "string",
"referencePeriod": "string",
"paymentCode": "string",
"installment": "string",
"taxAmount": 0 ,
"otherAmount": 0 ,
"monetaryAdjustment": 0 ,
"reportingPeriod": "string",
"referenceNumber": "string",
"assessmentPeriod": "string",
"calculationYear": "string",
"vehicleRenavam": "string",
"state": "string",
"municipalCode": "string",
"vehiclePlates": "string",
"paymentOption": 0 ,
"CRVLWithdrawalOption": 0 ,
"movimentCode": "string",
"complementaryCode": "CC",
```
- "occurrences": [
    + { ... }
],
- "remittanceLinked": {
    + "processed": [ ... ],
    + "processing": [ ... ]
},
- "reconciliationLinked": [
    + { ... }
],
"authenticationRegister": "string",
- "tags": [
    "string"
],
"createdAt": "string",
"updatedAt": "string"
}


## Excluir pagamento

Nesta documentação iremos definir como será feita a **exclusão** de um pedido de pagamento.
Lembrando que a exclusão só é permitida para pagamentos no status de **CREATED**.

HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
REQUEST BODY SCHEMA: application/json

```
Array of strings
Lista dos uniqueIds dos pagamentos a serem excluídos.
```
### Responses

```
200
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
payments
required
```

##### Request samples

```
Payload
```
##### Response samples

```
200 401 422
```
```
DELETE /api/v1/payment
```
```
application/json
```
```
Copy Expand all Collapse all
{
```
- "payments": [
    "_Q-DZBEDMN",
    "SD85ZBCSMN"
]
}

```
application/json
```
```
Copy Expand all Collapse all
{
"status": "string",
```
- "payments": [
    + { ... }
]
}

## Listar pagamentos gerados

Após gerar os pagamentos, você pode consultá-los por meio desta rota para verificar o resultado do
processamento realizado pela nossa API. Os filtros disponíveis incluem **accountHash** , **status** e
**paymentForm** , e cada um deles pode receber **mais de um valor** , separados por vírgulas.

Além disso, esta rota também é utilizada para acompanhar o status dos boletos após a conciliação
com o retorno bancário.

```
Content type
```
```
Content type
```

A consulta deve abranger um **período máximo de 31 dias consecutivos** , independentemente da data
em que ocorreu, e os resultados são paginados com um **limite de até 20 pagamentos por página**.

QUERY PARAMETERS

```
string
Número limite de pagamentos por página
```
```
string
Número da página
```
```
string<date>
Data de ínicio. Formato: AAAA-MM-DD
```
```
string<date>
Data de fim. Formato: AAAA-MM-DD
```
```
string
Identificador único da conta vinculada ao pagamento
```
```
string
Status do pagamento
```
```
string
Forma de pagamento
```
HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
### Responses

```
200
```
```
Sucesso
```
```
pageLimit
```
```
page
```
```
dateStart
```
```
dateEnd
```
```
accountHash
required
```
```
status
```
```
paymentForm
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```

## Pagamento em lote

Nessa seção, você encontrará informações sobre como criar e consultar pagamentos em lote.

Cada pedido de pagamento em lote que for gerado irá receber um identificar único ( **uniqueID** ) do lote e
este identificador será utilizado para consultar as situações dos pagamentos presentes no lote.

A geração em lote é limitada em **100 pagamentos** simultâneos.

```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Response samples

```
200 401 422
```
```
GET /api/v1/payment/list
```
```
application/json
```
```
Copy Expand all Collapse all
{
```
- "data": [
    + { ... },
    + { ... }
],
- "meta": {
    "page": 1 ,
    "totalPages": 1
}
}

```
Content type
```

## Salários

Nesta documentação iremos definir como será feita a geração de um pedido de pagamento de
**salários em lote**.

Segmento do tipo de pagamento -> Consulte o trecho com os segmentos por tipo de pagamento.

HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
REQUEST BODY SCHEMA: application/json

Array ([ 1 .. 100 ] items) [

```
string <= 50 characters
Hash da conta
```
```
string <= 250 characters
Descrição do pagamento
```
```
string <= 50 characters
Forma de pagamento. Consulte a tabela com os códigos de formas de
pagamento.
```
```
string<date>
Data do pagamento. Formato: AAAA-MM-DD
```
```
number >= 0.01
Valor do pagamento
```
```
number
Valor do abatimento
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
accountHash
required
```
```
description
```
```
paymentForm
required
```
```
paymentDate
required
```
```
amount
required
```
```
rebateAmount
```

```
number
Valor dos juros. Este campo é apenas para fins descritivos na remessa, ele
não gera nenhum impacto no cálculo do valor nominal, portanto, caso o
título tenha esse acréscimo é necessário incluir o campo Amount já com
esse valor calculado
```
```
number
Valor dos descontos
```
```
number
Valor da campo. Este valor é apenas para fins descritivos na remessa, ele
não gera nenhum impacto no cálculo do valor nominal, portanto, caso o
título tenha esse acréscimo é necessário incluir o campo Amount já com
esse valor calculado
```
```
string = 2 characters
```
```
string
Enum: "CC" "PP"
Código Finalidade Complementar. Código adotado para complemento da
finalidade pagamento. A forma de utilização deverá ser acordada entre
banco e cliente.
```
```
number
Parâmetro de transmissão.
```
```
Utilizado apenas para o banco Caixa , preencher com o número informado
pelo banco
```
```
number [ 0 .. 99 ]
Parâmetro de transmissão.
```
```
Utilizado apenas para o banco Caixa , preencher com o número informado
pelo banco
```
```
object
```
```
Array of strings[ items <= 50 characters ]
Tags para a identificação do pagamento
```
]

### Responses

```
201
```
```
Sucesso
```
```
interestAmount
```
```
discountAmount
```
```
fineAmount
```
```
movimentCode
```
```
complementaryCode
```
```
compromiseType
```
```
transmissionParam
```
```
beneficiary
required
```
```
tags
```

```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Request samples

```
Payload
```
##### Response samples

```
201 401 422
```
```
POST /api/v1/payment/paycheck/batch
```
```
application/json
```
```
Copy Expand all Collapse all
[
```
- {
    "accountHash": "b8aKHR6tXS",
    "paymentDate": "2019-11-12",
    "amount": 1.5,
    "interestAmount": 0 ,
    "discountAmount": 0 ,
    "fineAmount": "0",
+ "beneficiary": { ... },
+ "tags": [ ... ]
}
]

```
application/json
```
```
Copy Expand all Collapse all
{
"uniqueId": "string",
"status": "string",
```
```
Content type
```
```
Content type
```

- "payments": [
    + { ... }
]
}

## Transferência bancária em lote

Nesta documentação iremos definir como será feita a geração de um pedido de pagamento via
**transferências bancárias em lote**. (Em caso de erro em algum pagamento, não será gerado lote
parcial)

HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
REQUEST BODY SCHEMA: application/json

Array ([ 1 .. 100 ] items) [
One of case 1 case 2

```
string <= 50 characters
Hash da conta
```
```
string <= 250 characters
Descrição do pagamento
```
```
string <= 50 characters
Value:^3
Forma de pagamento. Consulte a tabela com os códigos de formas de
pagamento.
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
accountHash
required
```
```
description
```
```
paymentForm
required
```

```
string or (any or null)
Data do pagamento. Formato: AAAA-MM-DD
```
```
string<date>
Data do vencimento. Formato: AAAA-MM-DD
```
```
number [ 0.01 .. 999999999999.99 ]
Valor do pagamento
```
```
number [ 0 .. 999999999999.99 ]
Valor do abatimento
```
```
number [ 0 .. 999999999999.99 ]
Valor dos juros. Este campo é apenas para fins descritivos na remessa, ele
não gera nenhum impacto no cálculo do valor nominal, portanto, caso o
título tenha esse acréscimo é necessário incluir o campo Amount já com
esse valor calculado
```
```
number [ 0 .. 999999999999.99 ]
Valor dos descontos
```
```
number [ 0 .. 999999999999.99 ]
Valor da multa. Este campo é apenas para fins descritivos na remessa, ele
não gera nenhum impacto no cálculo do valor nominal, portanto, caso o
título tenha esse acréscimo é necessário incluir o campo Amount já com
esse valor calculado
```
```
number or null [ 0 .. 9999999999 ]
Value: 2
Código da câmara centralizadora adotado pela FEBRABAN com a função
de identificar quem será responsável pelo processamento dos
pagamentos por transferência. (Verifique o código seu banco **aqui)
```
```
string = 2 characters
Código da Instrução para Movimento. Código adotado pelo banco, para
identificar a ação a ser realizada com o lançamento enviado no arquivo. A
forma de utilização deverá ser acordada entre banco e cliente. Disponível
apenas para os bancos BB, Bradesco, Nordeste, Safra, Santander, Sicredi e
Unicred.
```
```
string
Enum: "CC" "PP"
Código Finalidade Complementar. Código adotado para complemento da
finalidade pagamento. A forma de utilização deverá ser acordada entre
banco e cliente.
```
```
number
Enum:^123
Indicador Forma Parcelamento.
```
paymentDate

dueDate

amount
required

rebateAmount

interestAmount

discountAmount

fineAmount

compensation

movimentCode

complementaryCode

installmentForm


```
1 : Data Fixa
2 : Periodicamente
3 : Dia Útil
```
```
number [ 1 .. 31 ]
Utilizado pelo Banco Caixa: Período ou Dia de Vencimento, preencher com
número desejado para o tratamento do Indicador da Forma de
Parcelamento (Para mais particularidade do campo consultar o manual
bancário)
```
```
number
Parâmetro de transmissão.
```
```
Utilizado apenas para o banco Caixa , preencher com o número informado
pelo banco
```
```
number [ 0 .. 99 ]
Parâmetro de transmissão.
```
```
Utilizado apenas para o banco Caixa , preencher com o número informado
pelo banco
```
```
object
```
```
Array of strings[ items <= 50 characters ]
Tags para a identificação do pagamento
```
]

### Responses

```
201
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
```
periodicDueDate
```
```
compromiseType
```
```
transmissionParam
```
```
beneficiary
required
```
```
tags
```

##### Request samples

```
Payload
```
##### Response samples

```
201 401 422
```
```
POST /api/v1/payment/transfer/batch
```
```
application/json
```
```
Copy Expand all Collapse all
[
```
- {
    "accountHash": "b8aKHR6tXS",
    "paymentDate": "2019-11-12",
    "dueDate": "2019-11-13",
    "amount": 1.5,
    "interestAmount": 0 ,
    "discountAmount": 0 ,
    "fineAmount": "0",
    "paymentForm": 1 ,
    "compensation": 1 ,
+ "beneficiary": { ... },
+ "tags": [ ... ]
}
]

```
application/json
```
```
Copy Expand all Collapse all
{
"uniqueId": "string",
"status": "string",
```
- "payments": [
    + { ... }
]
}

```
Content type
```
```
Content type
```

## Consultar os pagamentos gerados

Depois de gerar o lote, e obter o **uniqueId** , você pode consultar os pagamentos gerados para saber o
resultado do processamento pela nossa API.

Também, é possível consultar esta rota para saber qual o resultado do processamento dos
pagamentos do lote após serem conciliados pelo retorno gerado pelo banco.

PATH PARAMETERS

```
string
```
HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
### Responses

```
200
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
```
batchId
required
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```

##### Response samples

```
200 401 422
```
```
GET /api/v1/payment/batch/{batchId}
```
```
application/json
```
```
Copy Expand all Collapse all
```
```
Content type
```

[


- {
    "uniqueId": "string",
    "status": "string",
    "accountHash": "string",
    "paymentType": 0 ,
    "description": "string",
    "paymentForm": "string",
    "compensation": 0 ,
    "avalistaName": "string",
    "avalistaCpfCnpj": "string",
    "compromiseType": 0 ,
    "transmissionParam": 0 ,
    "installmentForm": 0 ,
    "periodicDueDate": 0 ,
+ "beneficiary": { ... },
"paymentDate": "2019-08-24",
"dueDate": "2019-08-24",
"feeAmount": 0 ,
"amount": 0 ,
"nominalAmount": 0 ,
"fineAmount": 0 ,
"interestAmount": 0 ,
"ourNumber": "string",
"barcode": "string",
"discountAmount": 0 ,
"contributorDocument": "string",
"contributorName": "string",
"stateRegistration": "string",
"activeDebit": "string",
"referencePeriod": "string",
"paymentCode": "string",
"installment": "string",
"taxAmount": 0 ,
"otherAmount": 0 ,
"monetaryAdjustment": 0 ,
"reportingPeriod": "string",
"referenceNumber": "string",
"assessmentPeriod": "string",
"calculationYear": "string",
"vehicleRenavam": "string",
"state": "string",
"municipalCode": "string",
"vehiclePlates": "string",
"paymentOption": 0 ,
"CRVLWithdrawalOption": 0 ,
"movimentCode":"string"


## Remessa

Nessa seção, você encontrará informações sobre como criar e consultar remessas.

```
movimentCode : string,
"complementaryCode": "CC",
+ "occurrences": [ ... ],
"authenticationRegister": "string",
+ "tags": [ ... ],
"createdAt": "string",
"updatedAt": "string"
}
]
```
## Gerar remessa

Após gerar os pagamentos **e obter os uniqueIds** , é hora de fazer a Solicitação da Remessa.

O processo de **solicitação da remessa acontece de forma assíncrona.** Ou seja, neste passo você irá
**solicitar a remessa** e receberá um protocolo (uniqueId) referente à esta solicitação. Recomendamos
que sejam informados **no máximo 700 a 800 pagamentos por remessa.** Vale destacar que quanto
**maior o número de pagamentos** na remessa, **mais tempo será necessário** para a geração do arquivo.

No passo seguinte, você fará a consulta deste protocolo.

É importante lembrar que a correta geração da remessa e sua aceitação pelo banco é o que garante
que os pagamentos serão efetivados com sucesso!

Por fim, é importante ressaltar que caso você esteja utilizando o **paymentForm 45 para o Itaú** , as
remessas de PIX desse banco não podem conter outros tipos de pagamento em seu conteúdo,
portanto, para esta instituição financeira, caso você deseje emitir um pagamento via PIX, somente
títulos deste tipo podem estar no arquivo.

HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
cnpjsh
required
```

```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
REQUEST BODY SCHEMA: application/json

```
Array of strings
Lista dos uniqueIds que devem estar presentes na remessa que será gerada.
```
```
number <= 999999999
Complemento de registro
```
### Responses

```
201
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Request samples

```
Payload
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
payments
required
```
```
complement
```
```
POST /api/v1/remittance
```
```
application/json
```
```
Content type
```

##### Response samples

```
201 401 422
```
```
Copy Expand all Collapse all
{
```
- "payments": [
    "_Q-DZBEDMN",
    "SD85ZBCSMN"
],
"complement": "123"
}

```
application/json
```
```
Copy Expand all Collapse all
[
```
- {
    "uniqueId": "string",
    "status": "string",
    "remittanceType": "string",
    "complement": 0 ,
+ "payments": [ ... ]
}
]

## Consultar remessa por período

QUERY PARAMETERS

```
string<date>
Data inicial que limitará a busca pelas remessas geradas
```
```
string<date>
Data final que limitará a busca pelas remessas geradas
```
```
number
Página que deve retornar as informações
```
```
dateStart
required
```
```
dateEnd
required
```
```
page
```
```
Content type
```

```
number
Limite de informação retornados por página
```
HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
### Responses

```
200
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Response samples

```
200 401 422
```
```
limit
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
GET /api/v1/remittance
```
```
application/json
```
```
Content type
```

```
Copy Expand all Collapse all
{
```
- "data": [
    + { ... }
],
- "meta": {
    "count": 1 ,
    "page": 1 ,
    "totalPages": 1
}
}

## Consultar remessa

Depois de fazer a **solicitação da remessa** , é hora de obtermos o arquivo de remessa que será
encaminhado ao banco!

Nesta rota nós iremos consulta o **uniqueId (protocolo)** que obtivemos na rota POST, onde a remessa
foi solicitada.

PATH PARAMETERS

```
string
Identificador único da remessa gerado no passo anterior (rota POST)
```
HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
```
uniqueId
required
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```

### Responses

```
200
```
```
Sucesso
```
```
202
```
```
Em processamento
```
```
401
```
```
Unauthorized
```
```
404
```
```
Bad Request
```
```
422
```
```
Invalid Param
```
##### Response samples

```
200 202 401 404 422
```
```
GET /api/v1/remittance/{uniqueId}
```
```
application/json
```
```
Copy Expand all Collapse all
{
"status": "PROCESSED",
"remittanceType": "DEFAULT",
```
- "payments": [
    + { ... }
],
"complement": 123 ,
"content": "<<<Base64 com o conteúdo da remessa>>"
}

```
Content type
```

## Baixar arquivo de remessa

Depois de fazer a **solicitação do arquivo de remessa** , o arquivo poderá ser baixado por esta rota. Este
passo não é obrigatório para o fluxo, serve apenas para o download do arquivo gerado, caso utilize a
Transmissão Manual, por exemplo, o arquivo pode ser baixado por essa rota.

PATH PARAMETERS

```
string
Identificador único da remessa
```
HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
### Responses

```
200
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
404
```
```
Not Found
```
```
uniqueId
required
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```

```
422
```
```
Invalid Param
```
##### Response samples

```
200 401 404 422
```
```
GET /api/v1/remittance/{uniqueId}/download
```
```
application/json
```
```
Copy
"string"
```
## Cancelar pagamento previamente agendado

Após gerar os pagamentos, **obter os uniqueIds** e os status atualizarem para **agendado** , é possível
fazer a Solicitação da Remessa de cancelamento.

O processo de **solicitação da remessa acontece de forma assíncrona.** Ou seja, neste passo você irá
**solicitar a remessa** e receberá um protocolo (uniqueId) referente à esta solicitação.

Recomendamos que sejam geradas diferentes remessas para cada forma de pagamento
(paymentForm).

No passo seguinte, você fará a consulta deste protocolo.

É importante lembrar que a correta geração da remessa e sua aceitação pelo banco é o que garante
que os pagamentos previamente agendados serão cancelados com sucesso!

```
Bancos Disponível
```
###### Bradesco ✔

###### Sicredi ✔

###### Itaú ✔

###### Banco do Brasil ✔

```
Content type
```

```
Bancos Disponível
```
###### Caixa ✔

HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
REQUEST BODY SCHEMA: application/json

```
Array of strings
Lista dos uniqueIds que devem estar presentes na remessa que será gerada.
```
### Responses

```
201
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
payments
required
```
```
POST /api/v1/remittance/cancel
```

## Retorno

Nessa seção, você encontrará informações sobre como criar e consultar arquivos de retorno.

##### Request samples

```
Payload
```
##### Response samples

```
201 401 422
```
```
application/json
```
```
Copy Expand all Collapse all
{
```
- "payments": [
    "string"
]
}

```
application/json
```
```
Copy Expand all Collapse all
[
```
- {
    "uniqueId": "string",
    "status": "string",
    "remittanceType": "string",
+ "payments": [ ... ]
}
]

## Enviar arquivo de retorno

```
Content type
```
```
Content type
```

Após ocorrer o processamento da remessa pelo banco, o banco gera o arquivo de retorno contendo as
informações referentes ao processamento da remessa. A confirmação do pagamento ou eventuais
mensagens de erros estarão presentes neste arquivo.

Nesta rota definiremos como enviar o arquivo de retorno para a nossa API e posteriormente, como
consultar o protocolo referente a esta conciliação.

**Obs.:** Esta rotina não precisa ser implementada caso você opte por utilizar a transmissão automática
das remessas e retornos, pois, o recebimento dos retornos ocorrerá de forma automática por nossa
API.

HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
REQUEST BODY SCHEMA: multipart/form-data
required

```
string
Arquivo de retorno (.ret, .txt).
```
### Responses

```
200
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
file
required
```

```
422
```
```
Invalid Param
```
##### Response samples

```
200 401 422
```
```
POST /api/v1/reconciliation
```
```
application/json
```
```
Copy
{
"uniqueId": "string",
"status": "string",
"createdAt": "string",
"updatedAt": "string"
}
```
## Consultar retorno por período

QUERY PARAMETERS

```
string<date>
Data inicial que limitará a busca pelos retornos conciliados
```
```
string<date>
Data final que limitará a busca pelos retornos conciliados
```
```
number
Página que deve retornar as informações
```
```
number
Limite de informação retornados por página
```
HEADER PARAMETERS

```
dateStart
required
```
```
dateEnd
required
```
```
page
```
```
limit
```
```
Content type
```

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
### Responses

```
200
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Response samples

```
200 401 422
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
GET /api/v1/reconciliation
```
```
application/json
```
```
Copy Expand all Collapse all
```
```
Content type
```

```
{
```
- "data": [
    + { ... }
],
- "meta": {
    "count": 1 ,
    "page": 1 ,
    "totalPages": 1
}
}

## Consultar arquivo de retorno

Depois de fazer a **solicitação do envio do retorno** no passo anterior, é hora de obtermos o resultado do
processamento deste arquivo.

Nesta rota nós iremos consulta o **uniqueId (protocolo)** que obtivemos na rota POST, onde o retorno foi
enviado.

PATH PARAMETERS

```
string
Identificador único do arquivo de retorno gerado no passo anterior (rota
POST)
```
HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
```
uniqueId
required
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```

### Responses

```
200
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Response samples

```
200 401 422
```
```
GET /api/v1/reconciliation/{uniqueId}
```
```
application/json
```
```
Copy Expand all Collapse all
{
"uniqueId": "string",
"status": "string",
"accountHash": "string",
"reason": "string",
```
- "payments": [
    + { ... }
],
"createdAt": "string",
"updatedAt": "string"
}

## Baixar arquivo de retorno

Depois de **conciliar o arquivo de retorno** , o arquivo poderá ser baixado por esta rota.

```
Content type
```

PATH PARAMETERS

```
string
Identificador único do retorno
```
HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
### Responses

```
200
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
404
```
```
Not Found
```
```
422
```
```
Invalid Param
```
```
uniqueId
required
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
GET /api/v1/reconciliation/{uniqueId}/download
```

## Comprovante

Nessa seção, você encontrará informações sobre como criar e consultar comprovantes de
pagamento.

##### Response samples

```
200 401 404 422
```
```
application/json
```
```
Copy
"string"
```
## Solicitar comprovante de pagamento

HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
REQUEST BODY SCHEMA: application/json

```
Array of strings
Lista dos uniqueIds dos pagamentos que devem ter os comprovantes
gerados. Os pagamentos deverão estar com o status PAID para que o
comprovante possa ser gerado.
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
payments
required
```
```
Content type
```

```
Limite de 100 pagamentos por requisição
```
### Responses

```
201
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Request samples

```
Payload
```
##### Response samples

```
201 401 422
```
```
POST /api/v1/voucher
```
```
application/json
```
```
Copy Expand all Collapse all
{
```
- "payments": [
    "_Q-DZBEDMN",
    "SD85ZBCSMN"
]
}

```
application/json
```
```
Copy Expand all Collapse all
```
```
Content type
```
```
Content type
```

```
{
"uniqueId": "string",
"status": "string",
```
- "payments": [
    + { ... }
]
}

## Consultar comprovante de pagamento

Depois de fazer a **solicitação do comprovante de pagamento** , é hora de realizarmos o download do
comprovante!

Nesta rota, nós iremos consultar o **uniqueId (protocolo)** que obtivemos na rota POST, em que o
comprovante foi solicitado.

Em caso de sucesso, o resultado da consulta será um **Buffer** contendo o arquivo PDF.

PATH PARAMETERS

```
string
Identificador único do comprovante gerado no passo anterior (rota POST)
```
HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
### Responses

```
uniqueId
required
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```

```
200
```
```
Sucesso - Arquivo PDF
```
```
202
```
```
Em processamento
```
```
401
```
```
Unauthorized
```
```
404
```
```
Not Found
```
```
422
```
```
Invalid Param
```
##### Response samples

```
202 401 404 422
```
```
GET /api/v1/voucher/{uniqueId}
```
```
application/json
```
```
Copy Expand all Collapse all
{
"uniqueId": "ATiGxW_nA97h2_xuTObXU",
"status": "PROCESSING",
```
- "payments": [
    + { ... }
]
}

```
Content type
```

## Bilhetagem

Nesta seção, você encontrará informações detalhadas sobre como funciona a bilhetagem na API de
Pagamentos, incluindo os status de pagamentos que consomem créditos. A bilhetagem é um
mecanismo utilizado para registrar e gerenciar o consumo da SH.

## Consulta de bilhetagem

Os status a seguir representam as possíveis situações dos pagamentos realizados via bilhetagem,
oferecendo clareza sobre as regras de consumo de créditos:

```
PAID
```
```
SCHEDULED
```
```
CANCELLED
```
```
REJECTED
```
```
REFUNDED
```
```
STATEMENT
```
QUERY PARAMETERS

```
string<date>
Data inicial da pesquisa
```
```
string<date>
Data final da pesquisa
```
```
number
Campo onde é definido quantos pagamentos retornarão por página (valor
máximo: 1000)
```
```
number
Campo onde é definida a página atual que os pagamentos serão retornados
```
HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
```
```
data_inicial
```
```
data_final
```
```
limit
```
```
page
```
```
cnpjsh
required
```
```
tokensh
```

```
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
### Responses

```
200
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Response samples

```
200 401 422
```
```
required
```
```
Content-Type
required
```
```
GET /api/v1/report
```
```
application/json
```
```
Copy Expand all Collapse all
{
"status": "string",
"message": "string",
"cnpjsh": "string",
"totalPayments": 0 ,
"totalVan": 0 ,
```
- "payers": [
    + { ... }
],
"page": 0 ,

```
Content type
```

## Notificação

Os **Webhooks** são as notificações que enviamos para você sempre que um evento ocorre, ou seja,
sempre que seus pagamentos sofrerem alguma alteração oriunda dos retornos gerados pelo banco.

A cada atualização, o sistema envia um **HTTP POST** para a URL configurada no webhook, com todas
as informações relevantes sobre cobranças, pedidos, assinaturas, entre outros. Desta maneira, o seu
sistema, ao receber essa notificação, pode executar os próximos passos.

Você tem total liberdade para escolher sobre quais eventos quer ser notificado e para qual URL cada
webhook será enviado.

```
"totalPages": 0 ,
"totalRecords": 0
}
```
## Criar notificação

A documentação a seguir mostrará como **criar** notificações.

**Atenção: É importante ressaltar que o envio das notificações webhook estão disponíveis apenas para
o ambiente de produção.**

HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
REQUEST BODY SCHEMA: application/json

```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```

```
string
Value: "webhook"
Tipo de notificação
```
```
string<email>
Email da Software House
```
```
string
Com cópia. Email a ser notificado em conjunto com a Software House
```
```
object
Cabeçalho da notificação
```
```
string
Endereço web a ser enviado na notificação
```
```
string
Telefone celular da Software House
```
```
Array of strings
ItemsEnum: "DDA" "PAID" "CREATED" "SCHEDULED" "CANCELLED"
"REJECTED" "REFUNDED" "STATEMENT" "DISCARDED"
Eventos disponíveis para a API de pagamento:
```
```
CREATED: O pagamento foi criado
PAID: O pagamento foi pago
SCHEDULED: O pagamento foi agendado
CANCELLED: O pagamento foi cancelado
REJECTED: O pagamento foi rejeitado
REFUNDED: O pagamento foi devolvido
STATEMENT: Extrato recebido
DDA: Movimentos DDA
```
### Responses

```
201
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
```
type
required
```
```
email
```
```
cc
```
```
headers
```
```
url
```
```
mobile
```
```
happen
```

##### Request samples

```
Payload
```
##### Response samples

```
201 401 422
```
```
POST /api/v1/notification
```
```
application/json
```
```
Copy Expand all Collapse all
{
"type": "webhook",
```
- "happen": [
    "CREATED",
    "PAID",
    "SCHEDULED",
    "CANCELLED",
    "REJECTED",
    "REFUNDED"
],
"url": "https://seusite.com.br",
- "headers": {
    "login": "exemplo",
    "token": "exemplo"
}
}

```
application/json
```
```
Copy Expand all Collapse all
{
"uniqueId": "XXXXXX",
"type": "webhook",
"email": "",
"cc": "",
```
```
Content type
```
```
Content type
```

- "headers": {
    "login": "exemplo",
    "token": "exemplo"
},
"url": "https://seusite.com.br",
"mobile": "",
- "happen": [
    "CREATED",
    "PAID",
    "SCHEDULED",
    "CANCELLED",
    "REJECTED",
    "REFUNDED"
]
}

## Listar notificações

A documentação a seguir mostrará como **consultar** notificações

**Atenção: É importante ressaltar que o envio das notificações webhook estão disponíveis apenas para
o ambiente de produção.**

HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
### Responses

```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```

```
200
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Response samples

```
200 401 422
```
```
GET /api/v1/notification
```
```
application/json
```
```
Copy Expand all Collapse all
{
```
- "notification": [
    + { ... }
]
}

## Apagar notificação

A documentação a seguir mostrará como **deletar** notificações

**Atenção: É importante ressaltar que o envio das notificações webhook estão disponíveis apenas para
o ambiente de produção.**

HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
cnpjsh
required
```
```
Content type
```

```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
REQUEST BODY SCHEMA: application/json

```
Array of strings
Identificadores únicos das notificações a serem excluídas
```
### Responses

```
200
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Request samples

```
Payload
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
uniqueId
required
```
```
DELETE /api/v1/notification
```
```
application/json
```
```
Copy Expand all Collapse all
```
```
Content type
```

##### Response samples

```
200 401 422
```
```
{
```
- "uniqueId": [
    "XXXXX"
]
}

```
application/json
```
```
Copy Expand all Collapse all
{
```
- "notification": [
    + { ... }
]
}

## Atualizar notificação

A documentação a seguir mostrará como **atualizar** notificações

**Atenção: É importante ressaltar que o envio das notificações webhook estão disponíveis apenas para
o ambiente de produção.**

HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content type
```

```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
REQUEST BODY SCHEMA: application/json

```
string <= 20 characters
```
```
string
Value: "webhook"
Tipo de notificação
```
```
string<email> [ 10 .. 200 ] characters
Email da Software House
```
```
string <= 200 characters
Com cópia. Quem você deseja que receba os avisos também
```
```
object
Cabeçalho da notificação
```
```
string <= 250 characters
Endereço web a ser enviado na notificação
```
```
string <= 20 characters
Telefone celular da Software House
```
```
Array of strings
ItemsEnum: "DDA" "PAID" "CREATED" "SCHEDULED" "CANCELLED"
"REJECTED" "REFUNDED" "STATEMENT" "DISCARDED"
Eventos disponíveis para a API de pagamento:
```
```
CREATED: O pagamento foi criado
PAID: O pagamento foi pago
SCHEDULED: O pagamento foi agendado
CANCELLED: O pagamento foi cancelado
REJECTED: O pagamento foi rejeitado
REFUNDED: O pagamento foi devolvido
STATEMENT: Extrato recebido
DDA: Movimentos DDA
```
### Responses

```
Content-Type
required
```
```
uniqueId
required
```
```
type
```
```
email
```
```
cc
```
```
headers
```
```
url
```
```
mobile
```
```
happen
```

```
200
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Request samples

```
Payload
```
##### Response samples

```
200 401 422
```
```
PUT /api/v1/notification
```
```
application/json
```
```
Copy Expand all Collapse all
{
"uniqueId": "XXXXXX",
"type": "webhook",
```
- "happen": [
    "CREATED",
    "PAID"
],
"url": "https://seusite.com.br"
}

```
application/json
```
```
Copy Expand all Collapse all
```
```
Content type
```
```
Content type
```

## Extrato bancário

Nesta documentação iremos definir como será feita a conversão de um arquivo de extrato bancário
para JSON.

Para realizar a conversão é necessário enviar o arquivo de extrato na rota de POST. Feito isso, será
gerado um hash (uniqueid) para realizar a consulta na rota GET e consultar o seu JSON.

```
{
```
- "notification": [
    + { ... }
]
}

## Enviar extrato bancário

HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
REQUEST BODY SCHEMA: multipart/form-data
required

```
string
Arquivo extensão (.ofx, .ret ou .ext).
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
file
required
```

### Responses

```
200
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Response samples

```
200 401 422
```
```
POST /api/v1/statement/parser
```
```
application/json
```
```
Copy
{
"uniqueId": "string"
}
```
## Consultar extrato bancário

PATH PARAMETERS

```
string
Identificador único do extrato gerado no passo anterior (rota POST)
```
HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
uniqueId
required
```
```
cnpjsh
required
```
```
Content type
```

```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
### Responses

```
200
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Response samples

```
200 401 422
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
GET /api/v1/statement/parser/{uniqueId}
```
```
application/json
```
```
Copy Expand all Collapse all
```
```
Content type
```

```
{
```
- "bankStatement": {
    "bankCode": "string",
    "bank": "string",
    "currency": "string",
    "balance": 0 ,
    "balanceFinal": 0 ,
    "date": "2019-08-24",
    "type": "string",
    "totalTransactions": 0 ,
    "accountHash": "string",
    "dateStart": "string",
    "dateEnd": "string"
},
- "transactions": {
    + "credit": [ ... ],
    + "debit": [ ... ],
    + "balance": { ... }
}
}

## Consultar extrato bancário por período

QUERY PARAMETERS

```
string<date>
Data inicial que o extrato foi exportado do banco
```
```
string<date>
Data final que o extrato foi exportado do banco
```
```
string <= 3 characters
Enum: "341" "001" "237" "756" "104" "748" "033"
Código do banco
```
```
string
Enum: "ofx" "ret"
Tipo do extrato (ofx, ret)
```
```
string
Hash identificador da conta
```
```
number
```
```
dateStart
required
```
```
dateEnd
required
```
```
bankCode
```
```
type
```
```
accountHash
```
```
page
```

```
Página que deve retornar as informações
```
```
number
Limite de informação retornados por página
```
HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
### Responses

```
200
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
404
```
```
Unauthorized
```
```
422
```
```
Invalid Param
```
##### Response samples

```
limit
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
GET /api/v1/statement
```

```
200 401 404 422
```
```
application/json
```
```
Copy Expand all Collapse all
{
```
- "data": [
    + { ... }
],
- "meta": {
    "count": 0 ,
    "page": 0 ,
    "totalPages": 0
}
}

## Baixar arquivo de extrato

Depois de fazer a **inclusão do arquivo de extrato** , o mesmo poderá ser baixado por esta rota.

PATH PARAMETERS

```
string
Identificador único do extrato
```
HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
```
uniqueId
required
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
Content type
```

## DDA

A **API de DDA** é um recurso que faz parte da API de Pagamentos, um sistema completo que gerencia
seu contas a pagar, possibilitando realizar o envio de pagamentos de diversos tipos (pagamentos de
títulos, transferências, salários,...) de forma padronizada, compatível com diversos bancos e com
possibilidade de automatização na comunicação bancária para a troca de arquivos.

### Responses

```
200
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
404
```
```
Not Found
```
```
422
```
```
Invalid Param
```
##### Response samples

```
200 401 404 422
```
```
GET /api/v1/statement/{uniqueId}/download
```
```
application/json
```
```
Copy
"string"
```
```
Content type
```

Se trata de uma API REST, portanto, toda a comunicação entre seu sistema e o nosso será feita via
requisições HTTPs. **Ou seja, se a linguagem de programação que você desenvolve possibilita a troca
de informações via requisições HTTPs, seu sistema é compatível com o nosso!**

## Criar DDA

A solicitação de envio do arquivo é o método pelo qual o arquivo contendo a movimentação dos DDA,
liberado pelo banco, em layout CNAB 240 padrão Febraban é encaminhado para ser processado pela
Tecnospeed.

Se **trata da primeira etapa** do processo assíncrono, onde o sistema integrado com nossa API poderá
realizar o envio do arquivo e obterá um protocolo em caso de sucesso. Este protocolo deverá ser
consultado na segunda etapa do processo de consulta, para que se tenha acesso ao resultado do
processamento do arquivo.

HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com multipart/form-data. Exemplo: Content-
Type: multipart/form-data
```
REQUEST BODY SCHEMA: application/json

```
File
Arquivo padrão Febraban CNAB 240, com mapeamento de segmentos G e H
```
```
string
AccountHash válido, cadastrado previamente, pertencente ao CNPJ do Pagador
informado no header da requisição.
```
```
O cadastro da conta é feito através deste processo:
https://docs.pagamentobancario.com.br/#tag/account/operation/createAccount.
```
### Responses

```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
multipart/form-data
required
```
```
file
required
```
```
accountHash
required
```

```
201
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
404
```
```
Not Found
```
```
422
```
```
Unprocessable Entity
```
##### Request samples

```
Payload
```
##### Response samples

```
201 401 404 422
```
```
POST /api/v1/dda
```
```
application/json
```
```
Copy
{
"file": null,
"accountHash": "string"
}
```
```
application/json
```
```
Copy
```
```
Content type
```
```
Content type
```

```
{
"uniqueid": "aaabbbccc",
"status": "PROCESSING"
}
```
## Listar DDA - Consulta via UniqueID

A consulta do processamento do DDA via UniqueID (valor este, retornado no campo **uniqueId** da
primeira etapa do processo) é a segunda etapa do fluxo, e é o momento onde o resultado do
processamento do arquivo será verificado.

O objetivo aqui é visualizarmos o resultado do processamento do arquivo ou eventuais erros que
tenham ocorrido.

HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
### Responses

```
200
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```

```
404
```
```
Not Found
```
```
422
```
```
Unprocessable Entity
```
##### Response samples

```
200 401 404 422
```
```
GET /api/v1/dda/[UniqueID]
```
```
application/json
```
```
Copy Expand all Collapse all
{
```
- "dda": {
    "status": "SUCCESS",
    "uniqueId": "AAABBBCCC",
    "origin": "USER",
    "createdAt": "2024-09-25T18:59:48.665Z",
    "accountHash": "ABC123",
    "message": "Sucesso ao ler arquivo DDA"
},
- "ddaPayments": [
    + { ... }
],
- "ddaDuplicatePayments": [
    + { ... }
]
}

## Listar DDA - Últimos arquivos recebidos

A consulta dos últimos arquivos processados é uma etapa opcional do fluxo. Nesta rota, retornaremos
os últimos arquivos DDA processados, com seus respectivos UniqueID.

```
Content type
```

O objetivo aqui é apresentar à SH uma lista dos arquivos processados, para que esta capture os
UniqueId e faça a consulta completa.

HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
### Responses

```
200
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
404
```
```
Not Found
```
```
422
```
```
Unprocessable Entity
```
##### Response samples

```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
GET /api/v1/dda
```

```
200 401 404 422
```
```
application/json
```
```
Copy Expand all Collapse all
{
```
- "dda": {
    "status": "SUCCESS",
    "uniqueId": "AAABBBCCC",
    "origin": "USER",
    "createdAt": "2024-09-25T18:59:48.665Z",
    "accountHash": "ABC123"
}
}

## Consulta de pagamentos processados via DDA

A consulta de pagamentos processados traz um detalhamento dos pagamentos processados e
obtidos na etapa de consulta via UniqueID.

Ou seja, a consulta via UniqueID retorna uma lista de 1 ou N pagamentos, e cada pagamento recebe
um identificador (uniqueId do pagamento). O objetivo desta consulta é consultar individualmente os
identificadores dos pagamentos.

HEADER PARAMETERS

```
string
CPF ou CNPJ da Software House que possui contrato com a Tecnospeed
```
```
string
Token da Software House. É disponibilizado no portal de contas da
TecnoSpeed, que pode ser acessado através da URL:
https://conta.tecnospeed.com.br/.
```
```
string
CPF ou CNPJ do Pagador
```
```
string
Tipo de mídia, preencher com application/json. Exemplo: Content-Type:
application/json
```
```
cnpjsh
required
```
```
tokensh
required
```
```
payercpfcnpj
required
```
```
Content-Type
required
```
```
Content type
```

### Responses

```
200
```
```
Sucesso
```
```
401
```
```
Unauthorized
```
```
404
```
```
Not Found
```
```
422
```
```
Unprocessable Entity
```
##### Response samples

```
200 401 404 422
```
```
GET /api/v1/dda/payment/[UniqueID]
```
```
application/json
```
```
Copy Expand all Collapse all
{
"uniqueId": "AAABBB",
"createPayment": "false",
"description": "",
"barcode": "34195826200000000000000000000000000000027000",
"digitableLine": "00190000030108029200303001570133210530003248642",
"dueDate": "2023-03-31",
"paymentDate": "",
"nominalAmount": "289.02",
"discountAmount": "0.00",
"feeAmount": "",
"amount": "289.02",
"movimentCode": "02",
"avalistaName": "AVALISTA NOME TESTE",
"avalistaCpfCnpj": "111111111111111",
```
```
Content type
```

```
"compromiseType": "",
"transmissionParam": "",
```
- "beneficiary": {
    "name": "BENEFICIARIO TESTE",
    "cpfCnpj": "222222222222"
},
"tags": "",
"codRegistro": "",
"sacadoCpfCnpj": "",
"sacadoName": "",
"sacadoAddress": "",
"sacadoNeighborhood": "",
"sacadoZipCode": "",
"sacadoCity": "",
"sacadoUF": "",
"invoiceNumber1": "",
"invoiceAmount1": "",
"invoiceDate1": "",
"invoiceNumber2": "",
"invoiceAmount2": "",
"invoiceDate2": "",
"invoiceNumber3": "",
"invoiceAmount3": "",
"invoiceDate3": "",
"invoiceNumber4": "",
"invoiceAmount4": "",
"invoiceDate4": "",
"invoiceNumber5": "",
"invoiceAmount5": "",
"invoiceDate5": "",
"typePayment": "",
"quantityPayment": "",
"typeValuePayment": "",
"maxValuePayment": "",
"minValuePayment": "",
"maxPercentualPayment": "",
"minPercentualPayment": ""
}


## Bancos homologados e tipos de pagamento

Abaixo, separamos uma lista com os bancos e métodos homologados pela nossa API de Pagamento.

```
Símbolo Legenda
```
###### ✔ Disponível na API.

###### ❌ Não homologado na API.

###### ⚠ Indisponível no layout do banco.

#### Extrato Bancário

**Bancos homologados para conciliação do extrato bancário:**

```
Banco OFX EXT
```
```
ITAÚ ✔ ✔
```
```
BANCO DO BRASIL ✔ ✔
```
```
BRADESCO ✔ ✔
```
```
SICOOB ✔ ✔
```
```
CAIXA ECONÔMICA ✔
```
```
SICREDI ✔
```
```
SANTANDER ✔
```
#### Segmento A - Pagamento Através de Crédito em Conta, Cheque, OP,

#### DOC, TED, PIX ou Pagamento com Autenticação

**Formas de lançamentos disponíveis:**


```

```
```

```
```
Forma de lançamento (paymentForm) Descrição
```
```
1 Conta Corrente
```
```
3 DOC
```
```
5 Poupança
```
```
41 TED Outra Titularidade
```
```
43 TED Mesma Titularidade
```
```
45 (beta) PIX
```
**Bancos homologados:**

```
Forma de lançamento disponíveis (paymentForm)
```
```
Banco 1 3 5 41 43 45 (beta)
```
```
Banco do
Nordeste ✔ ✔ ✔ ✔ ✔ ❌
```
```
Banco Inter ❌ ❌ ❌ ❌ ❌ ✔
```
```
Banco do
Brasil ✔ ✔ ✔ ✔ ✔ ❌
```
```
Caixa
Econômica ✔ ✔ ✔ ✔ ✔ ❌
```
```
Citibank ✔ ✔ ⚠ ✔ ✔ ❌
```
```
Bradesco ✔ ✔ ✔ ✔ ✔ ✔
```
```
Santander ✔ ✔ ✔ ✔ ✔ ✔
```
```
Itaú ✔ ✔ ✔ ✔ ✔ ✔
```
```
Sicredi ✔ ✔ ⚠ ✔ ✔ ✔
```
```
Sicoob ✔ ✔ ✔ ✔ ✔ ❌
```
```
Safra ✔ ✔ ⚠ ❌ ❌ ❌
```
```
Banrisul ✔ ✔ ⚠ ✔ ✔ ✔
```
```
BMG ⚠ ⚠ ⚠ ✔ ✔ ❌
```
```
BRB ✔ ❌ ✔ ✔ ✔ ❌
```
```
Unicred ✔ ✔ ✔ ✔ ✔ ❌
```
```
BTG ✔ ✔ ✔ ✔ ✔ ✔
```
```
BANK OF
AMERICA
✔ ✔ ❌ ✔ ✔ ❌
```

#### Segmento J - Títulos de Cobrança (Próprio e Outros Bancos)

**Formas de lançamentos disponíveis:**

```
Forma de lançamento (paymentForm) Descrição
```
```
30 Títulos de cobrança do próprio banco
```
```
31 Títulos de cobrança de outros bancos
```
```
47 (beta) PIX QRCODE
```
**Bancos homologados:**

```
Forma de lançamento disponíveis (paymentForm)
```
```
Banco 30 31 47 (beta)
```
```
Banco do
Nordeste
✔ ✔ ❌
```
```
Banco Inter ✔ ✔ ❌
```
```
Banco do
Brasil
✔ ✔ ❌
```
```
Caixa
Econômica
✔ ✔ ❌
```
```
Citibank ✔ ✔ ❌
```
```
Bradesco ✔ ✔ ✔
```
```
Santander ✔ ✔ ✔
```
```
Itaú ✔ ✔ ✔
```
```
BTG ✔ ✔ ✔
```
```
Itaú ✔ ✔ ✔
```
```
BANK OF
AMERICA
```
```
✔ ✔ ❌
```
```
Itaú ✔ ✔ ✔
```
```
Sicredi ✔ ✔ ❌
```
```
Sicoob ✔ ✔ ❌
```

```

```
```
Forma de lançamento disponíveis (paymentForm)
```
```
Safra ✔ ✔ ❌
```
```
Banrisul ✔ ✔ ❌
```
```
BMG ⚠ ⚠ ❌
```
```
BRB ❌ ❌ ❌
```
#### Segmento O - Concessionárias e Tributos com Código de Barras

**Formas de lançamentos disponíveis:**

```
Forma de lançamento (paymentForm) Descrição
```
```
11 Tributos com código de barras
```
```
13 Pagamentos de concessionárias
```
```
19 IPTU/ISS/OUTROS TRIBUTOS MUNICIPAIS
```
**Bancos homologados:**

```
Forma de lançamento disponíveis (paymentForm)
```
```
Banco 11 13 19
```
```
Banco do
Nordeste
✔ ✔ ❌
```
```
Banco Inter ✔ ✔ ❌
```
```
Banco do
Brasil
✔ ✔ ❌
```
```
Caixa
Econômica
✔ ✔ ❌
```
```
Citibank ✔ ✔ ❌
```
```
Bradesco ✔ ✔ ✔
```
```
Santander ✔ ✔ ✔
```
```
Itaú ✔ ✔ ✔
```
```
Sicredi ✔ ✔ ❌
```

```

```
```

```
```
Forma de lançamento disponíveis (paymentForm)
```
```
Sicoob ✔ ✔ ❌
```
```
Safra ⚠ ⚠ ❌
```
```
Banrisul ✔ ❌ ❌
```
```
BMG ⚠ ⚠ ❌
```
```
BRB ❌ ❌ ❌
```
```
BTG ✔ ✔ ❌
```
```
BANK OF
AMERICA ✔ ❌ ❌
```
#### Segmento N - Tributos sem Código de Barras

**Formas de lançamentos disponíveis:**

```
Forma de lançamento (paymentForm) Descrição
```
```
16 Tributo DARF Normal
```
```
17 Tributo GPS
```
```
18 Tributo DARF Simples
```
```
21 Tributo DARJ
```
```
22 Tributo GARE SP ICMS
```
```
23 Tributo GARE SP DR
```
```
24 Tributo GARE SP ITCMD
```
```
25 IPVA
```
```
26 Licenciamento
```
```
27 DPVAT
```
**Bancos homologados:**

```
Forma de lançamento disponíveis (paymentForm)
```
```
Banco 16 17 18 21 22 23 24 25 26 27
```

```
Forma de lançamento disponíveis (paymentForm)
```
```
Banco do
Nordeste ✔ ✔ ❌ ❌ ✔ ❌ ❌ ✔ ❌ ✔
```
```
Banco Inter ✔ ❌ ✔ ❌ ❌ ❌ ❌ ❌ ❌ ❌
```
```
Banco do
Brasil ✔ ✔ ✔ ❌ ✔ ❌ ❌ ✔ ❌ ❌
```
```
Caixa
Econômica ✔ ✔ ⚠ ⚠ ✔ ❌ ❌ ⚠ ⚠ ⚠
```
```
Citibank ✔ ✔ ⚠ ⚠ ⚠ ⚠ ⚠ ⚠ ⚠ ⚠
```
```
Bradesco ✔ ✔ ❌ ❌ ✔ ❌ ❌ ✔ ❌ ✔
```
```
Santander ❌ ✔ ✔ ⚠ ✔ ❌ ❌ ✔ ❌ ❌
```
```
Itaú ✔ ✔ ❌ ❌ ✔ ⚠ ⚠ ✔ ⚠ ✔
```
```
Sicredi ✔ ✔ ✔ ⚠ ⚠ ⚠ ⚠ ⚠ ⚠ ⚠
```
```
Sicoob ✔ ✔ ❌ ⚠ ⚠ ⚠ ⚠ ⚠ ⚠ ⚠
```
```
Safra ⚠ ⚠ ⚠ ⚠ ⚠ ⚠ ⚠ ⚠ ⚠ ⚠
```
```
Banrisul ✔ ✔ ✔ ⚠ ⚠ ⚠ ⚠ ⚠ ⚠ ❌
```
```
BMG ⚠ ⚠ ⚠ ⚠ ⚠ ⚠ ⚠ ⚠ ⚠ ⚠
```
```
BRB ❌ ❌ ❌ ❌ ❌ ❌ ❌ ❌ ❌ ❌
```
```
BANK OF
AMERICA
✔ ✔ ❌ ❌ ✔ ✔ ❌ ❌ ❌ ❌
```
## Código da câmara centralizadora (compensation)

```
Símbolo Legenda
```
###### ⚠ Código indisponível no layout do banco.

```
Forma de Pagamento Código
```
```
Crédito em Conta 0
```
```
Finalidade TED 1
```
```
Finalidade DOC e OP 2
```

```
Forma de Pagamento Código
```
```
TED STR 3
```
```
Boleted/ISPB 4
```
```
TED (STR/CIP) ISPB 5
```
```
PIX 6
```
```
0 1 2 3 4 5 6
```
```
Banco do
Nordeste (000) (018) (700) (810) (888) (988) ⚠
```
```
Banco do
Brasil
(000) (018) (700) ⚠ ⚠ (988) ⚠
```
```
Caixa
Econômica
(000) (018) (700) ⚠ (888) ⚠ ⚠
```
```
Bradesco (000) (018) (700) ⚠ (888) ⚠ ⚠
```
```
Santander (000) (018) (700) (810) ⚠ ⚠ ⚠
```
```
Itaú (000) (018) (700) (810) (888) (988) ⚠
```
```
Sicredi (000) (018) (700) ⚠ ⚠ ⚠ ⚠
```
```
Sicoob ⚠ (018) (700) ⚠ ⚠ ⚠ ⚠
```
```
Safra (000) (018) (700) ⚠ ⚠ ⚠ ⚠
```
```
Banrisul ⚠ (018) (700) ⚠ (888) ⚠ (009)
```
```
BMG ⚠ ⚠ ⚠ ⚠ ⚠ ⚠ ⚠
```
## Transmissão automática

## Introdução

A Transmissão Automática é um recurso que faz o envio da Remessa e o upload do Retorno sem
manipulação de arquivos. Ou seja, quando gerada a remessa, ela será encaminhada ao banco
automaticamente, sem a necessidade de salvar em arquivo.

O mesmo vale para o arquivo de retorno, quando disponibilizado pelo banco, será processado pela API
automaticamente, não sendo necessário baixar o arquivo no internet banking e encaminhar para a
nossa API.


## Como solicitar a Transmissão Automática junto ao

## Banco

**Iniciando o processo de liberação da Transmissão Automática na instituição bancária:**

**Passo 1** : Preenchimento da Carta

Para começarmos o processo de ativação da Transmissão Automática para sua conta, o primeiro
passo é preencher a carta de abertura de relacionamento de acordo com o serviço que será utilizado:

```
Cobrança, Pagamento, Extrato e DDA (Finnet) – Download - Template Carta
```
Alguns bancos podem solicitar a integração com a própria carta. Caso a sua solicitação seja para os
bancos **Itaú, Banco do Brasil, Safra ou Sicredi** , estão disponíveis os seguintes arquivos:

```
Itaú (Finnet) – [Download - Template Carta]
Banco do Brasil (Finnet) – [Download - Template Carta]
Safra (Finnet) – [Download - Template Carta]
Sicredi (Nexxera) – [Download - Template Carta]
```
Download template carta VAN Nexxera

Vale lembrar, que esse processo não é apenas para empresas (CNPJ), também pode ser solicitado por
clientes PF (CPF).

Junto ao preenchimento da carta, não alterar os dados referente aos responsáveis técnicos, para que o processo de
liberação junto à VAN possa seguir normalmente.

**Passo 2:** Entrega da carta à Tecnospeed

A carta preenchida pelo correntista com as informações da conta precisa ser enviada à Tecnospeed,
via chat ou ticket, para que nós possamos iniciar por aqui o fluxo de liberação da Transmissão
Automática.

Após nos entregar as cartas um ticket registrando o pedido será aberto, e para que o processo se
conclua com sucesso e no menor tempo possível, é essencial que a mesma carta seja entregue ao
banco para que o mesmo inicie o processo de configuração e ativação da Transmissão Automática
das remessas e retornos (explicaremos com mais detalhes este processo na sequência).

**Passo 3:** Entrega da Carta ao banco e validação das remessas

Após preencher a carta com os dados da conta do cliente, é necessário encaminhá-la ao gerente
bancário responsável pela conta do cedente, informando a necessidade de trafegar arquivos de

###### Cobrança Registrada com a VAN Nexxera (caso o gerente tenha dificuldades em fazer este processo,

###### logo abaixo traremos com mais detalhes o processo nos principais bancos);

###### *Exceto para o banco SICOOB, onde não é necessário a carta para abertura de relacionamento, a

###### abertura deve ser feita diretamente no Internet Banking.

Ao entregar ao gerente da conta as cartas, é de fundamental importância que você também solicite a
ele o processo de validação das remessas geradas. Para isso, utilize a nossa rotina de geração de
remessas para gerar pelo menos 10 arquivos (a quantidade varia de acordo com o banco) e
encaminhe-as ao gerente para a validação.


Feito isso, assim que o banco concluir todas as configurações ele notificará a Nexxera/Tecnospeed
para que nós possamos prosseguir com o processo de liberação

Desta forma, tendo qualquer novidade sobre o andamento e assim que estiver tudo pronto nós te
avisaremos pelo ticket!

**Mas e se o banco gerente tiver dificuldades em iniciar o processo de liberação?**

Por via de regra, a orientação junto aos bancos é solicitar uma alteração na forma como as remessas
e retornos são recepcionados.
Deixando de ser via internet banking, e passando a ser via VAN.

## Como preencher a carta para a transmissão

## automática

Para o preenchimento da carta, é necessário informar os dados referente ao **cedente** (Emissor da
cobrança), abaixo seque um modelo da carta utiliza para a **abertura de relacionamento.**


As informações destacadas na cor **amarela** deve ser substituídas pelos dados do **cedente.**

Segue abaixo os links das cartas de transmissão:

**Abaixo temos uma dica de preenchimento para os campos destacados**

```
Banco - Nome do banco onde será solicitado a transmissão automática.
```

```
Agência - Agência do cedente (Informar DV se tiver).
```
```
Cidade - Cidade onde esta localizada a empresa do cedente.
```
```
UF - Estado onde esta localizada a empresa do cedente.
```
```
A/C - Informar o nome do gerente da conta.
```
```
Fone - Telefone para contato do cedente.
```
```
Serviço no Banco - Serviço utilizado no banco, informar PAGAMENTO.
```
```
Conta/DV - Conta completa com DV (Mesmo valor cadastrado para o cedente).
```
```
Convênio: - Informar o mesmo convênio cadastrado para o cedente.
```
```
Layout Cliente - CNAB utilizado pelo cedente (240).
```
```
RAZÃO SOCIAL - Razão Social do cedente.CNPJ - CNPJ ou CPF do cedente.
```
```
NOME - Nome do solicitante.
```
```
CARGO - Cargo do solicitante dentro da empresa.
```
```
FONE - Telefone para contato do solicitante.
```
```
E-MAIL - E-mail para contado do solicitante.
```
## Como preencher a carta para a transmissão

## automática - Finnet

Para o preenchimento da carta, é necessário informar os dados referente ao **cedente** (Emissor da
cobrança), abaixo seque um modelo da carta utiliza e os passos para preenche-la para a **abertura de
relacionamento.**

**Passo 1:** Inicialmente devem ser preenchidas as informações em destaque do cabeçalho.
Respectivamente, informar o nome do banco ao qual a conta pertence. Após isso, indicar o gerente
bancário da conta que será responsável pelo encaminhamento da carta ao setor adequado da
instituição bancária. Por fim, informar a razão social do cedente.


As informações destacadas na cor **amarela** deve ser substituídas pelos dados do **cedente.**

**Passo 2:** Em “CONTRATANTE” devem ser informados os dados do cedente e em “VAN CONTRATADA”
há os dados já fixados da FINNET.

**Passo 3** Em “PRODUTOS FINANCEIROS” selecionar o serviço que será utilizado para trafegar os
arquivos de remessa e retorno.


**Passo 4:** Em “Contato da Empresa”, preencher o nome, e-mail e telefone do cedente responsável. Em
“Contato do gerente de conta”, preencher com os dados do gerente bancário e finalizar com a


assinatura do cedente.

## Como preencher a carta para a transmissão

## automática - Finnet (Itaú)

Para o preenchimento da carta, é necessário informar os dados referente ao cedente (emissor da
cobrança). Abaixo segue um modelo da carta utilizada para a abertura de relacionamento com o
Banco Itaú. **Selecionar a aba SISPAG na planilha.**


O campo destacado em amarelo é referente a aba para preenchimento dos dados para o serviço de
cobrança.

Preenchimento dos dados:

As informações destacadas na cor amarela devem ser substituídas pelos dados do cedente.

**Abaixo temos uma dica de preenchimento para os campos destacados**

```
Agência e conta - Agência e Conta do cedente (Informar DV se tiver).
```
```
CNAB - CNAB utilizado pelo cedente (240).
```
```
Ambiente - Produção.
```
```
Nome - Nome do solicitante.
```
```
E-mail - E-mail para contato do solicitante.
```

```
Telefone - Telefone para contato do cedente.
```
```
RAZÃO SOCIAL - Razão Social do cedente.CNPJ - CNPJ ou CPF do cedente.
```
## Como preencher a carta para a transmissão

## automática - Finnet (Banco do Brasil)

Para o preenchimento da carta, é necessário informar os dados referente ao cedente (emissor da
cobrança). Abaixo segue um modelo da carta utilizada para a abertura de relacionamento com o


Banco do Brasil.

As informações destacadas na cor amarela devem ser substituídas pelos dados do cedente.

**Abaixo temos uma dica de preenchimento para os campos destacados**

```
Agência - Agência do cedente (Informar DV se tiver).
```

**RAZÃO SOCIAL** - Razão Social do cedente.

```
CNPJ<> - CNPJ ou CPF do cedente.
```
**- Conta - Conta do cedente.**

```
Endereço - Endereço da empresa.
```
```
Tipo do arquivo - Marcados referente ao serviço que será utilizado.
```
```
CNAB - CNAB utilizado pelo cedente (240).
```
```
Ambiente - Produção.
```
**Os campos de Responsável técnico, preencher com os contatos da Tecnospeed, para que qualquer
dúvida referente ao processo seja direcionado à nós.**

**Nome: Suporte PlugBank**

**Telefone: (44) 3037-9500**

**Email: fintech@tecnospeed.com.br**

## Como habilitar a Transmissão Automática

**Assim que o processo de Transmissão Automática for concluído, a alteração na forma de
transmissão (da transmissão manual para a transmissão automática) será feita totalmente por nós
da TecnoSpeed.**

**Portanto, no momento em que a solicitação para a transmissão automática for finalizada pelo banco
e pela Nexxera, nossos consultores entrarão em contato com a Software House informando que o
novo recurso já está disponível pra uso e se a Software House desejar.**

**Após habilitada a transmissão automática é necessário apenas chamar a rota/método de geração da
remessa com os pagamentos que deseja transmitir ao banco. A partir deste momento, o tráfego de
arquivos (remessas e retorno) passará a ser feito pela Tecnospeed.**

## Tempo de envio e retorno de arquivos enviado via

## VAN Nexxera

```
Entendendo o fluxo de envio de remessas e retornos:
```
**De forma simplificada podemos sintetizar o processo de envio e recepção de remessas da seguinte
forma:**

**Passo 1: Remessa é enviada ao banco via VAN Nexxera;**

**Passo 2: Remessa é processada pelo banco e gera um retorno;**

**Passo 3: Conciliação do retorno.**

**O processo de envio e recebimento de remessas é algo bem simples, contudo, por conta da
particularidade de cada banco o tempo de envio e recebimento para os arquivos variam de banco
para banco.**


**Para os principais bancos elaboramos um descritivo, mostrando qual o tempo de envio de remessas
e recebimento de retornos para o serviço de Pagamentos:**

```
Bradesco:
```
**Envio: Pagamentos enviados até 17:59 autorizados**

**Retorno: 2 horas para o processamento.**

```
Itaú:
```
**Envio: Pagamentos enviados até 17h, após este horário será bloqueado.**

**Retorno: 2 horas para o processamento.**

```
Santander:
```
**Envio: Pagamentos enviados até às 17h podem ser autorizados. Após às 17h será bloqueado.
Pagamentos a receber até às 20hrs.**

**Retorno: Próximo dia útil**

```
Banco do Brasil:
```
**Envio: Pagamentos enviados até 17:59 autorizados**

**Retorno: Próximo dia útil**

## Exemplos de WebHooks

## Introdução

Para esta seção deixamos disponíveis alguns exemplos de Webhooks, que podem estar sendo
encaminhandos para o seu sevidor.

## WebHooks DDA

```
{
"happen": "DDA",
"uniqueId": "DDA001",
"status": "PENDING",
"origin": "Banco Itaú",
"message": "Novo título disponível no DDA",
"accountHash": "abc123xyz",
"createdAt": "2025-09-30T20:30:00.000Z"
}
```
**WebHooks Pagamentos**


PAID:

```
{
"status": "PAID",
"uniqueId": "xxxxxx",
"authenticationRegister":"xxxxxxxxxxxx",
"createdAt": "2020-08-27T17:04:58.796Z",
"paymentDate": "2020-08-27",
"occurrences": [{
"code":"00",
"message": "PAGAMENTO EFETUADO",
"createdAt":"2020-08-28T18:13:58.824Z",
"occurrenceDate": "2020-08-28T18:13:58.817Z"
}],
"accountHash":"xxxxxxxxx"
}
```
REFUNDED:

```
{
"status": "REFUNDED",
"uniqueId": "xxxxxx",
"createdAt":"2020-08-27T16:17:42.570Z",
"occurrences": [{
"code": "DV",
"message": "DOC / TED DEVOLVIDO PELO BANCO FAVORECIDO",
"createdAt": "2020-08-28T18:13:58.817Z",
"occurrenceDate":"2020-08-28T18:13:58.817Z"
}],
"accountHash": "xxxxxxxxx"
}
```
SCHEDULED:

```
{
"status":"SCHEDULED",
"uniqueId": "xxxxxx",
"createdAt":"2020-08-26T14:48:32.416Z",
"occurrences": [{
"code": "BD",
"message": "PAGAMENTO AGENDADO",
"createdAt": "2020-08-27T15:51:40.733Z",
"occurrenceDate":"2020-08-27T15:51:40.711Z"
}],
"accountHash": "xxxxxxxxx"
}
```
REJECTED:


```
{
"status":"REJECTED",
"uniqueId": "xxxxxx",
"createdAt":"2020-08-26T14:48:32.416Z",
"occurrences": [{
"code": "RJ",
"message": "REGISTRO REJEITADO",
"createdAt": "2020-08-27T15:51:40.733Z",
"occurrenceDate":"2020-08-27T15:51:40.711Z"},
{
"code": "AL",
"message": "CÓDIGO DO BANCO FAVORECIDO INVÁLIDO",
"createdAt": "2020-08-27T15:51:40.740Z",
"occurrenceDate":"2020-08-27T15:51:40.742Z"
},
{
"code": "IN",
"message": "BANCO / AGENCIA NÃO CADASTRADOS",
"createdAt": "2020-08-27T15:51:40.749Z",
"occurrenceDate":"2020-08-27T15:51:40.751Z"
}],
"accountHash": "xxxxxxxxx"
}
```
STATEMENT:

```
{
"happen":"STATEMENT",
"balance": "-100.74",
"uniqueId": "xxxxxxxxxxx",
"createdAt":"2020-08-12T12:17:34.610Z",
"accountHash": "xxxxxxxxx"
}
```
CANCELLED:

```
{
"uniqueId":"xxxxxx",
"status": "CANCELLED",
"accountHash": "xxxxxxxxx",
"createdAt":"2021-05-03T18:01:18.489Z",
"occurrences":[{
"code": "02",
"message": "Crédito ou Débito Cancelado pelo Pagador/Credo",
"occurrenceDate": "2021-05-03T18:05:57.133Z",
"createdAt":"2021-05-03T18:05:57.136Z"
}],
"accountHash": "xxxxxxxxx"
}
```

## Campos para o preenchimento de um pagamento FGTS.

## Introdução

Para os campos relacionados a emissão de um pagamento FGTS, segue abaixo uma imagem de
orientação.

**1 - Razão social/Nome** = contributorName
**2- Cód Recolhimento** = revenueCode
**3- ID Recolhimento** = fgtsIdentifier
**4- Inscrição/Tipo** = contributorDocument
**5- Total a recolher** = amount
**6- Código de barras** = barcode

## Campos para o preenchimento de um pagamento DARF.

## Introdução


Para os campos relacionados a emissão de um pagamento DARF, segue abaixo uma imagem de
orientação.

**1 - Nome do contribuinte** = contributorName
**2- Período de Apuração** = reportingPeriod
**3- Documento do contribuinte** = contributorDocument
**4- Código da Receita/Tipo** = revenueCode
**5- Número de referência** = referenceNumber
**6- Data de vencimento** = dueDate
**7- Valor Principal** = barcode
**8- Valor da Multa** = fineAmount
**9- Valor dos Juros** = interestAmount


