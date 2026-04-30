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
            <h1>{{ env('OPENFINANCE_API_URL') }}payer</h1>
        </div>

        <div>
            <div>
                <form action="{{ route('openfinance.create_payer') }}" method="POST">
                    @method('POST')
                    @csrf

                    <div>
                        <h2>Auth Headers</h2>

                        <div>
                            <div>
                                <label for="tokensh">tokensh</label>
                                <input type="text" id="tokensh" name="tokensh" value="{{ env('TOKENSH') }}">
                            </div>

                            <div>
                                <label for="cnpjsh">cnpjsh</label>
                                <input type="text" id="cnpjsh" name="cnpjsh" value="{{ env('CNPJSH') }}">
                            </div>
                        </div>
                    </div>

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

            @if (session('response_account'))
                <div>
                    <h3>Account API Response:</h3>
                    <pre>{{ json_encode(session('response_acount'), JSON_PRETTY_PRINT) }}</pre>
                    <h3>Account Response:</h3>
                    <pre>{{ json_encode(session('response_account'), JSON_PRETTY_PRINT) }}</pre>
                </div>

                <form method='POST' action="{{ route('openfinance.create_account') }}">
                    @method('POST')
                    @csrf

                    @include('bank_dropdown')

                    <input type="hidden" id="cpfCnpjHidden" name="cpfCnpj" value=""/>
                </form>
            @endif
        </div>
    </div>

    <script>
        const source = document.getElementById('cpfCnpj');
        const target = document.getElementById('cpfCnpjHidden');

        if (source && target) {
            const sync = () => target.value = source.value;
            sync();
            source.addEventListener('input', sync);
        } else {
                console.error('CPF/CNPJ input or hidden field not found:', { source, target });
        }
    </script>

</body>

</html>