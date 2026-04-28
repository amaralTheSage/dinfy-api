<!DOCTYPE html>
<html lang="en">

<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>Tecnospeed Payer Setup</title>
</head>

<body class="bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">

        @session('response')
                {{ $response }}
        @endsession


        <div class="max-w-2xl mx-auto">
                <div class="mb-8 text-center">
                        <p class="text-xs font-mono text-gray-400 uppercase tracking-widest mb-1">API Endpoint</p>
                        <h1
                                class="text-sm font-mono bg-gray-800 text-green-400 p-3 rounded-md shadow-inner inline-block">
                                {{ env('OPENFINANCE_API_URL') }}/payer
                        </h1>
                </div>

                <div class="bg-white shadow-xl rounded-xl overflow-hidden">
                        <form action="{{ route('openfinance.create_payer') }}" method="POST" class="p-8">
                                @method('POST')
                                @csrf

                                <div class="mb-10">
                                        <h2
                                                class="text-lg font-semibold text-gray-900 border-b border-gray-100 pb-2 mb-6 flex items-center">
                                                <span class="w-2 h-6 bg-blue-500 rounded mr-3"></span>
                                                Auth Headers
                                        </h2>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                <div>
                                                        <label for="tokensh"
                                                                class="block text-sm font-medium text-gray-700 mb-1">tokensh</label>
                                                        <input type="text" id="tokensh" name="tokensh"
                                                                value="{{ env('TOKENSH') }}"
                                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                                                </div>

                                                <div>
                                                        <label for="cnpjsh"
                                                                class="block text-sm font-medium text-gray-700 mb-1">cnpjsh</label>
                                                        <input type="text" id="cnpjsh" name="cnpjsh"
                                                                value="{{ env('CNPJSH') }}"
                                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                                                </div>
                                        </div>
                                </div>

                                <div>
                                        <h2
                                                class="text-lg font-semibold text-gray-900 border-b border-gray-100 pb-2 mb-6 flex items-center">
                                                <span class="w-2 h-6 bg-green-500 rounded mr-3"></span>
                                                Request Body
                                        </h2>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                                                <div class="md:col-span-2">
                                                        <label for="name"
                                                                class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                                        <input type="text" id="name" name="name"
                                                                value="CNPJ PARA TESTES"
                                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                                                </div>

                                                <div>
                                                        <label for="cpfCnpj"
                                                                class="block text-sm font-medium text-gray-700 mb-1">CPF
                                                                / CNPJ</label>
                                                        <input type="text" id="cpfCnpj" name="cpfCnpj"
                                                                value="01001001000113"
                                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                                                </div>

                                                <div>
                                                        <label for="zipcode"
                                                                class="block text-sm font-medium text-gray-700 mb-1">Zipcode</label>
                                                        <input type="text" id="zipcode" name="zipcode" value="87020025"
                                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                                                </div>

                                                <div class="md:col-span-2 grid grid-cols-3 gap-4">
                                                        <div class="col-span-2">
                                                                <label for="neighborhood"
                                                                        class="block text-sm font-medium text-gray-700 mb-1">Neighborhood</label>
                                                                <input type="text" id="neighborhood" name="neighborhood"
                                                                        value="DUQUE DE CAXIAS"
                                                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                                                        </div>
                                                        <div>
                                                                <label for="addressNumber"
                                                                        class="block text-sm font-medium text-gray-700 mb-1">Number</label>
                                                                <input type="text" id="addressNumber"
                                                                        name="addressNumber" value="882"
                                                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                                                        </div>
                                                </div>

                                                <div>
                                                        <label for="city"
                                                                class="block text-sm font-medium text-gray-700 mb-1">City</label>
                                                        <input type="text" id="city" name="city" value="MARINGA"
                                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                                                </div>

                                                <div>
                                                        <label for="state"
                                                                class="block text-sm font-medium text-gray-700 mb-1">State</label>
                                                        <input type="text" id="state" name="state" value="PR"
                                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                                                </div>

                                                <div class="md:col-span-2">
                                                        <label for="statementActived"
                                                                class="block text-sm font-medium text-gray-700 mb-1">Statement
                                                                Actived</label>
                                                        <input type="text" id="statementActived" name="statementActived"
                                                                value="true"
                                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 font-mono text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                                                </div>
                                        </div>
                                </div>

                                <div class="mt-10">
                                        <button type="submit"
                                                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg shadow-lg hover:shadow-xl transition-all active:scale-[0.98]">
                                                Create Payer
                                        </button>
                                </div>
                        </form>
                </div>

                <p class="text-center text-gray-400 text-xs mt-6 italic">
                        Make sure your environment variables are correctly set before submitting.
                </p>
        </div>

</body>

</html>