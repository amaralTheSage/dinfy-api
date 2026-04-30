<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Tecnospeed Payer Setup</title>
</head>

<body>

    <div>
        <div>
            <p>API Endpoint</p>
            <h1>{{ config('openfinance.url') }}payer</h1>
        </div>

        <div>
            <div>
                <form action="{{ route('openfinance.create_payer') }}" method="POST">
                    @method('POST')
                    @csrf

                    <div>
                        <h2>Request Body</h2>

                        <div>
                            <div>
                                <label for="name">Name</label>
                                <input type="text" id="name" name="name" value="CNPJ PARA TESTES">
                            </div>

                            <div>
                                <label for="cpfCnpj">CPF / CNPJ</label>
                                <input type="text" id="cpfCnpj" name="cpfCnpj" value="01001001000113">
                            </div>

                            <div>
                                <label for="zipcode">Zipcode</label>
                                <input type="text" id="zipcode" name="zipcode" value="87020025">
                            </div>

                            <div>
                                <label for="neighborhood">Neighborhood</label>
                                <input type="text" id="neighborhood" name="neighborhood" value="DUQUE DE CAXIAS">
                            </div>

                            <div>
                                <label for="addressNumber">Number</label>
                                <input type="text" id="addressNumber" name="addressNumber" value="882">
                            </div>

                            <div>
                                <label for="city">City</label>
                                <input type="text" id="city" name="city" value="MARINGA">
                            </div>

                            <div>
                                <label for="state">State</label>
                                <input type="text" id="state" name="state" value="PR">
                            </div>

                       
                        </div>

                        <div>
                            <button type="submit">Create Payer</button>
                        </div>
                    </div>
                </form>
            </div>

            @if (session('response'))
                <div>
                    <h3>Payer API Response:</h3>
                    <pre>{{ json_encode(session('response'), JSON_PRETTY_PRINT) }}</pre>
                </div>


        <form method='POST' action="{{ route('openfinance.create_account') }}">
                    @method('POST')
                    @csrf

                    @include('bank_dropdown')

                    <div>
                        <label for="cpfCnpjAccount">CPF / CNPJ</label>
                        <input type="text" id="cpfCnpjAccount" name="cpfCnpj" readonly>
                    </div>

                     <div>
        <label for="agency">Agency</label>
        <input type="text" id="agency" name="agency">
    </div>
    <div>
        <label for="agencyDigit">Agency Digit (Opcional)</label>
        <input type="text" id="agencyDigit" name="agencyDigit">
    </div>
    <div>
        <label for="accountNumber">Account Number</label>
        <input type="text" id="accountNumber" name="accountNumber">
    </div>

    <button type="submit">Create Account</button>
                </form>



            @endif
            
            
        


        @if (session('response_account'))
                <div>
                    <h3>Account API Response:</h3>
                    <pre>{{ json_encode(session('response_account'), JSON_PRETTY_PRINT) }}</pre>
                </div>

        @endif
</div>
    </div>

    <script>
        const source = document.getElementById('cpfCnpj');
        const target = document.getElementById('cpfCnpjAccount');

        const sync = () => {
            if (target) {
                target.value = source.value;
            }
        };

        // Sync on input change
        if (source) {
            source.addEventListener('input', sync);
        }

        // Also sync on page load/visibility change in case the form appears after initial load
        document.addEventListener('visibilitychange', sync);
        setTimeout(sync, 100);
        setTimeout(sync, 500);

        // Log responses after page fully renders
        setTimeout(() => {
            console.log('Response:', @json(session('response')));
            console.log('Response Account:', @json(session('response_account')));
        }, 100);
    </script>

</body>

</html>