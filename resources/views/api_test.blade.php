<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Tecnospeed Open Finance Test</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 32px;
        }

        .forms,
        .fields {
            display: flex;
            gap: 16px;
        }

        .forms {
            align-items: flex-start;
            flex-wrap: wrap;
        }

        .forms > div,
        .field {
            flex: 1;
        }

        .forms > div {
            min-width: 320px;
        }

        .fields {
            flex-wrap: wrap;
        }

        .field {
            min-width: 180px;
        }

        label {
            display: block;
            margin-bottom: 4px;
        }

        input,
        select,
        button {
            box-sizing: border-box;
            padding: 8px;
            width: 100%;
        }

        button {
            margin-top: 16px;
        }

        pre {
            border: 1px solid #ddd;
            overflow: auto;
            padding: 12px;
        }

        .error {
            color: #b00020;
        }
    </style>
</head>

<body>
    <p>API Endpoint</p>
    <h1>{{ config('openfinance.url') }}</h1>

    @if ($errors->any())
        <div class="error">
            <strong>Validation errors:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Fluxo de teste Open Finance/Tecnospeed:
        1. Criar payer.
        2. Criar conta para esse payer e abrir o openfinanceLink retornado.
        3. Depois de o usuário aprovar a conexão, gerar um protocolo de extrato.
        4. Consultar o protocolo pelo uniqueId para obter movimentos, duplicados e saldo.
    --}}
    <div class="forms">
        {{-- Passo 1: cadastra o pagador na Tecnospeed.
            A resposta pode criar o payer ou reativar statement caso a Tecnospeed retorne internalCode 7632.
            O CPF/CNPJ daqui é usado nos headers payercpfcnpj dos passos seguintes.
        --}}
        <div>
            <form action="{{ route('openfinance.create_payer') }}" method="POST">
                @csrf

                <h2>Create Payer</h2>

                <div class="fields">
                    <div class="field">
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" value="{{ old('name', 'CNPJ PARA TESTES') }}">
                    </div>

                    <div class="field">
                        <label for="cpfCnpj">CPF / CNPJ</label>
                        <input type="text" id="cpfCnpj" name="cpfCnpj" value="{{ old('cpfCnpj', '01001001000113') }}">
                    </div>

                    <div class="field">
                        <label for="zipcode">Zipcode</label>
                        <input type="text" id="zipcode" name="zipcode" value="{{ old('zipcode', '87020025') }}">
                    </div>

                    <div class="field">
                        <label for="neighborhood">Neighborhood</label>
                        <input type="text" id="neighborhood" name="neighborhood" value="{{ old('neighborhood', 'DUQUE DE CAXIAS') }}">
                    </div>

                    <div class="field">
                        <label for="addressNumber">Number</label>
                        <input type="text" id="addressNumber" name="addressNumber" value="{{ old('addressNumber', '882') }}">
                    </div>

                    <div class="field">
                        <label for="city">City</label>
                        <input type="text" id="city" name="city" value="{{ old('city', 'MARINGA') }}">
                    </div>

                    <div class="field">
                        <label for="state">State</label>
                        <input type="text" id="state" name="state" value="{{ old('state', 'PR') }}">
                    </div>
                </div>

                <button type="submit">Create Payer</button>
            </form>

            @if (session('response'))
                <h3>Payer API Response:</h3>
                <pre>{{ json_encode(session('response'), JSON_PRETTY_PRINT) }}</pre>
            @endif
        </div>

        {{-- Passo 2: cadastra uma conta bancária para o payer.
            A resposta retorna accountHash e openfinanceLink.
            O usuário precisa acessar o openfinanceLink e aprovar a conexão; a ativação pode levar horas.
            Guarde o accountHash para solicitar extratos depois.
        --}}
        <div>
            <form method="POST" action="{{ route('openfinance.create_account') }}">
                @csrf

                <h2>Create Account</h2>

                <div class="fields">
                    <div class="field">
                        <label for="bankCode">Bank</label>
                        @include('bank_dropdown')
                    </div>

                    <div class="field">
                        <label for="cpfCnpjAccount">CPF / CNPJ</label>
                        <input type="text" id="cpfCnpjAccount" name="cpfCnpj" value="{{ old('cpfCnpj', '01001001000113') }}">
                    </div>

                    <div class="field">
                        <label for="agency">Agency</label>
                        <input type="text" id="agency" name="agency" value="{{ old('agency') }}">
                    </div>

                    <div class="field">
                        <label for="agencyDigit">Agency Digit</label>
                        <input type="text" id="agencyDigit" name="agencyDigit" value="{{ old('agencyDigit') }}">
                    </div>

                    <div class="field">
                        <label for="accountNumber">Account Number</label>
                        <input type="text" id="accountNumber" name="accountNumber" value="{{ old('accountNumber') }}">
                    </div>

                    <div class="field">
                        <label for="accountNumberDigit">Account Number Digit</label>
                        <input type="text" id="accountNumberDigit" name="accountNumberDigit" value="{{ old('accountNumberDigit') }}">
                    </div>
                </div>

                <button type="submit">Create Account</button>
            </form>

            @if (session('response_account'))
                <h3>Account API Response:</h3>
                <pre>{{ json_encode(session('response_account'), JSON_PRETTY_PRINT) }}</pre>
            @endif
        </div>

        {{-- Passo 3: solicita o extrato Open Finance para uma conta já aprovada.
            Esta chamada ainda não retorna movimentos; ela só retorna uniqueId/status PROCESSING.
            Rate limit Tecnospeed informado na doc: 1 requisição de sucesso a cada 6 horas.
        --}}
        <div>
            <form method="POST" action="{{ route('openfinance.create_statement_protocol') }}">
                @csrf

                <h2>Generate Statement Protocol</h2>

                <div class="fields">
                    <div class="field">
                        <label for="cpfCnpjStatement">CPF / CNPJ</label>
                        <input type="text" id="cpfCnpjStatement" name="cpfCnpj" value="{{ old('cpfCnpj', '01001001000113') }}">
                    </div>

                    <div class="field">
                        <label for="accountHash">Account Hash</label>
                        <input type="text" id="accountHash" name="accountHash" value="{{ old('accountHash', data_get(session('response_account'), 'accounts.0.accountHash')) }}">
                    </div>

                    <div class="field">
                        <label for="dateStart">Date Start</label>
                        <input type="date" id="dateStart" name="dateStart" value="{{ old('dateStart', now()->subDays(7)->toDateString()) }}">
                    </div>

                    <div class="field">
                        <label for="dateEnd">Date End</label>
                        <input type="date" id="dateEnd" name="dateEnd" value="{{ old('dateEnd', now()->toDateString()) }}">
                    </div>

                    <div class="field">
                        <label for="today">Today Only</label>
                        <input type="checkbox" id="today" name="today" value="1" @checked(old('today'))>
                    </div>
                </div>

                <button type="submit">Generate Protocol</button>
            </form>

            @if (session('response_statement_protocol'))
                <h3>Statement Protocol Response:</h3>
                <pre>{{ json_encode(session('response_statement_protocol'), JSON_PRETTY_PRINT) }}</pre>
            @endif
        </div>

        {{-- Passo 4: consulta o resultado do processamento pelo uniqueId.
            O endpoint remoto da Tecnospeed é GET /statement/openfinance/{uniqueId}.
            Quando finalizado, a resposta traz statement, transaction, transactionDuplicated e balance.
            Rate limit Tecnospeed informado na doc: 3 consultas por minuto, com cache de 1 hora.
        --}}
        <div>
            <form method="POST" action="{{ route('openfinance.get_statement_result') }}">
                @csrf

                <h2>Get Statement Result</h2>

                <div class="fields">
                    <div class="field">
                        <label for="cpfCnpjStatementResult">CPF / CNPJ</label>
                        <input type="text" id="cpfCnpjStatementResult" name="cpfCnpj" value="{{ old('cpfCnpj', '01001001000113') }}">
                    </div>

                    <div class="field">
                        <label for="uniqueId">Unique ID</label>
                        <input type="text" id="uniqueId" name="uniqueId" value="{{ old('uniqueId', data_get(session('response_statement_protocol'), 'uniqueId')) }}">
                    </div>
                </div>

                <button type="submit">Get Statement</button>
            </form>

            @if (session('response_statement_result'))
                <h3>Statement Result Response:</h3>
                <pre>{{ json_encode(session('response_statement_result'), JSON_PRETTY_PRINT) }}</pre>
            @endif
        </div>
    </div>

    <script>
        // Mantém o CPF/CNPJ dos passos seguintes sincronizado com o payer do passo 1.
        const source = document.getElementById('cpfCnpj');
        const target = document.getElementById('cpfCnpjAccount');
        const targetStatement = document.getElementById('cpfCnpjStatement');
        const targetStatementResult = document.getElementById('cpfCnpjStatementResult');

        const sync = () => {
            if (source && target) {
                target.value = source.value;
            }

            if (source && targetStatement) {
                targetStatement.value = source.value;
            }

            if (source && targetStatementResult) {
                targetStatementResult.value = source.value;
            }
        };

        source?.addEventListener('input', sync);
    </script>
</body>

</html>
