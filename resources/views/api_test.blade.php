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

        .forms > div,
        .field {
            flex: 1;
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

    <div class="forms">
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
    </div>

    <script>
        const source = document.getElementById('cpfCnpj');
        const target = document.getElementById('cpfCnpjAccount');

        const sync = () => {
            if (source && target) {
                target.value = source.value;
            }
        };

        source?.addEventListener('input', sync);
    </script>
</body>

</html>
