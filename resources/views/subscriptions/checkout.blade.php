<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dinfy </title>
    <script src="https://sdk.mercadopago.com/js/v2"></script>
    <script src="https://www.mercadopago.com/v2/security.js" view="checkout"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

        :root {
            --phone-surface: #f5deb0;
            --phone-surface-2: #f8e8bf;
            --card-surface: rgba(234, 237, 238, 0.96);
            --field-surface: rgba(255, 255, 255, 0.96);
            --field-shadow: rgba(181, 137, 45, 0.08);
            --text: #17120d;
            --muted: rgba(23, 18, 13, 0.58);
            --line: rgba(67, 42, 9, 0.08);
            --button: #070707;
            --button-text: #fff8ef;
            --chip: rgba(8, 37, 92, 0.08);
            --success: #1f9f63;
            --success-soft: rgba(31, 159, 99, 0.14);
            --danger: #d64242;
            --danger-soft: rgba(214, 66, 66, 0.14);
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            min-height: 100%;
            
        }

        body {
            margin: 0;
            font-family: "Plus Jakarta Sans", sans-serif;
            color: var(--text);
            background:
            rgba(255, 255, 255, 0.86);
        }

        .logo {
            width: 120px;
        }

        body::before,
        body::after {
            content: "";
            position: fixed;
            inset: auto;
            width: 320px;
            height: 320px;
            border-radius: 999px;
            filter: blur(18px);
            opacity: 0.34;
            z-index: 0;
            pointer-events: none;
        }

        body::before {
            top: -120px;
            right: -90px;
        }

        body::after {
            left: -110px;
            bottom: -120px;
        }

        .page {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 28px 16px;
        }

        .phone {
            width: min(430px, 100%);

            padding-inline: 18px;
          
    background:
            rgba(255, 255, 255, 0.86);
        }

        .phone-inner {
            position: relative;
            overflow: hidden;

           background:
            rgba(255, 255, 255, 0.86);
        }

    
        .topbar {
            position: relative;
            display: grid;
            grid-template-columns: 44px 1fr 44px;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
        }

        .topbar-title {
            text-align: center;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .topbar-spacer,
        .back-link {
            width: 44px;
            height: 44px;
            border-radius: 999px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: var(--text);
            background: rgba(255, 250, 235, 0.82);
            box-shadow: 0 8px 24px rgba(120, 82, 18, 0.08);
            font-size: 22px;
        }

        .summary-strip,
        .payment-panel,
        .card-preview,
        .result-feedback {
            position: relative;
            z-index: 1;
        }

        .summary-strip {
            display: grid;
            gap: 8px;
            margin-bottom: 16px;
            padding: 4px 4px 2px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(18, 73, 164, 0.08);
            color: #123e90;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .summary-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: end;
        }

        .summary-title {
            margin: 0;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.04em;
        }

        .summary-price {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: rgba(7, 33, 87, 0.82);
            white-space: nowrap;
        }

        .summary-copy,
        .plan-row,
        .support-copy {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.55;
        }

        .plan-row {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px 16px;
            font-weight: 600;
        }

        .payment-panel {
            padding: 16px;
            border-radius: 30px;
            background: var(--card-surface);
            border: 1px solid rgba(255, 255, 255, 0.5);
         
        }

        .panel-title {
            margin: 0 0 16px;
            font-size: 16px;
            font-weight: 700;
        }

        .field-grid {
            display: grid;
            gap: 14px;
        }

        .field-row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .field,
        .mp-field {
            display: grid;
            gap: 8px;
        }

        .field.full,
        .mp-field.full {
            grid-column: 1 / -1;
        }

        .field label,
        .mp-field label {
            font-size: 12px;
            font-weight: 700;
            color: rgba(23, 18, 13, 0.88);
        }

        .field input,
        .field select,
        .mp-box {
            width: 100%;
            height: 52px;
            min-height: 52px;
            max-height: 52px;
            border: 1px solid transparent;
            border-radius: 18px;
            padding: 14px 16px;
            background: var(--field-surface);
            box-shadow:
                inset 0 -1px 0 rgba(255, 255, 255, 0.44),
                inset 0 0 0 1px rgba(255, 255, 255, 0.2),
                0 10px 24px var(--field-shadow);
            color: var(--text);
            font: inherit;
            transition: border-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
            overflow: hidden;
        }

        .field select {
            appearance: none;
            background-image:
                linear-gradient(45deg, transparent 50%, rgba(23, 18, 13, 0.48) 50%),
                linear-gradient(135deg, rgba(23, 18, 13, 0.48) 50%, transparent 50%);
            background-position:
                calc(100% - 18px) calc(50% - 2px),
                calc(100% - 12px) calc(50% - 2px);
            background-size: 6px 6px, 6px 6px;
            background-repeat: no-repeat;
            padding-right: 34px;
        }

        .field input:focus,
        .field select:focus,
        .mp-box:focus-within {
            outline: none;
            border-color: rgba(18, 73, 164, 0.28);
            box-shadow:
                inset 0 0 0 1px rgba(18, 73, 164, 0.18),
                0 12px 26px rgba(18, 73, 164, 0.16);
            transform: translateY(-1px);
        }

        .field input[readonly] {
            color: rgba(23, 18, 13, 0.62);
        }

        .mp-box iframe {
            width: 100% !important;
            height: 100% !important;
            border: none !important;
        }

        .aux-hidden {
            position: absolute;
            opacity: 0;
            pointer-events: none;
            height: 0;
            width: 0;
            overflow: hidden;
        }

        .status {
            display: none;
            margin-top: 14px;
            padding: 13px 14px;
            border-radius: 18px;
            font-size: 13px;
            line-height: 1.45;
        }

        .status.is-visible {
            display: block;
        }

        .status.success {
            color: #135f3c;
            background: var(--success-soft);
        }

        .status.error {
            color: #9d2626;
            background: var(--danger-soft);
        }

        .actions {
            display: grid;
            gap: 10px;
            margin-top: 18px;
        }

        .button {
            appearance: none;
            border: 0;
            border-radius: 999px;
            min-height: 56px;
            padding: 16px 18px;
            font: inherit;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            transition: transform 0.2s ease, opacity 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .button:hover {
            transform: translateY(-1px);
        }

        .button:disabled {
            cursor: wait;
            opacity: 0.78;
            transform: none;
        }

        .button-primary {
            background: var(--button);
            color: var(--button-text);
            box-shadow: 0 18px 26px rgba(7, 7, 7, 0.22);
        }

        .button-secondary {
            min-height: 46px;
            font-size: 13px;
            font-weight: 700;
            color: rgba(23, 18, 13, 0.72);
            background: rgba(23, 18, 13, 0.05);
        }

        .support-copy {
            margin-top: 12px;
        }

        .card-preview {
            margin-top: 18px;
            padding: 18px 18px 16px;
            border-radius: 28px;
            background:
                radial-gradient(circle at top left, rgba(255, 240, 164, 0.95), transparent 22%),
                linear-gradient(135deg, #ffd60a 0%, #f8cc0d 45%, #e3b400 100%);
          
            animation: float-card 4.8s ease-in-out infinite;
        }

        .card-preview-top,
        .card-preview-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-preview-top {
            margin-bottom: 30px;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.04em;
        }

        .wave-icon {
            font-size: 22px;
            transform: rotate(90deg);
        }

        .brand {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 0.05em;
        }

        .chip {
            width: 38px;
            height: 28px;
            border-radius: 8px;
            background:
              rgba(255, 245, 213, 0.9);
            box-shadow:
                inset 0 0 0 1px rgba(93, 67, 15, 0.16),
                0 6px 10px rgba(124, 88, 16, 0.22);
            position: relative;
        }

        .chip::before,
        .chip::after {
            content: "";
            position: absolute;
            top: 6px;
            bottom: 6px;
            width: 1px;
            background: rgba(114, 82, 23, 0.26);
        }

        .chip::before {
            left: 12px;
        }

        .chip::after {
            left: 20px;
        }

        .card-number {
            margin: 18px 0 10px;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 0.06em;
        }

        .card-preview-bottom {
            gap: 16px;
            align-items: end;
        }

        .card-name,
        .card-expiry {
            font-size: 14px;
            font-weight: 700;
        }

        .card-name {
            text-transform: uppercase;
            max-width: 68%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .result-feedback {
            display: none;
            grid-template-columns: auto 1fr;
            gap: 12px;
            align-items: center;
            margin-top: 16px;
            padding: 14px 16px;
            border-radius: 22px;
            background: rgba(255, 250, 241, 0.88);
            box-shadow: 0 16px 30px rgba(92, 63, 13, 0.08);
            animation: result-pop 0.32s ease;
        }

        .result-feedback.is-visible {
            display: grid;
        }

        .result-feedback.success {
            border: 1px solid rgba(31, 159, 99, 0.18);
        }

        .result-feedback.error {
            border: 1px solid rgba(214, 66, 66, 0.18);
        }

        .result-icon {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            font-size: 20px;
            font-weight: 800;
        }

        .result-feedback.success .result-icon {
            color: #135f3c;
            background: var(--success-soft);
        }

        .result-feedback.error .result-icon {
            color: #9d2626;
            background: var(--danger-soft);
        }

        .result-title {
            margin: 0 0 3px;
            font-size: 14px;
            font-weight: 800;
        }

        .result-message {
            margin: 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.45;
        }

        .error-panel {
            display: grid;
            gap: 12px;
        }

        @keyframes float-card {
            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-5px);
            }
        }

        @keyframes result-pop {
            from {
                opacity: 0;
                transform: translateY(8px) scale(0.98);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @media (max-width: 480px) {
            .page {
                padding: 12px;
            }

            .phone {
                border-radius: 30px;
                padding: 12px;
            }

            .phone-inner {
                border-radius: 24px;
                padding: 16px 14px 18px;
            }

            .summary-head {
                flex-direction: column;
                align-items: start;
            }

            .field-row {
                grid-template-columns: 1fr;
            }

            .card-number {
                font-size: 24px;
            }
        }
    </style>
</head>

<body>
    <main class="page">
        <section class="phone">
            <div class="phone-inner">
                <header class="topbar">
                    <img src="dinfy_logo.png" alt="" class="logo">
                    <div class="topbar-spacer" aria-hidden="true"></div>
                </header>

                <section class="summary-strip">
                    <div class="summary-head">
                        <h1 class="summary-title">{{ $plan['name'] }}</h1>
                        <p class="summary-price">
                            R$ {{ number_format((float) $plan['amount'], 2, ',', '.') }}
                            / {{ $plan['frequency'] === 12 ? 'ano' : 'mês' }}
                        </p>
                    </div>
                    <p class="summary-copy">{{ $plan['reason'] }}</p>
                    <p class="plan-row">
                        <span>{{ $plan['frequency'] }} {{ $plan['frequency_type'] === 'years' ? 'anos' : 'meses' }}</span>
                        <span>Pagamento via Mercado Pago</span>
                    </p>
                </section>

                <section class="payment-panel">
                    <h2 class="panel-title">Dados do Cartão</h2>

                    @if ($errorMessage)
                        <div class="error-panel">
                            <div class="status error is-visible">{{ $errorMessage }}</div>
                            <a class="button button-secondary" href="{{ $returnUrl }}">Voltar ao app</a>
                        </div>
                    @else
                        <form id="form-checkout">
                            <div class="field-grid">
                                <div class="field full">
                                    <label for="form-checkout__cardholderName">Nome do Titular</label>
                                    <input type="text" id="form-checkout__cardholderName" autocomplete="cc-name"
                                        placeholder="Nome Completo" required />
                                </div>

                                <div class="field-row">
                                    <div class="mp-field">
                                        <label for="form-checkout__securityCode">CVV</label>
                                        <div id="form-checkout__securityCode" class="mp-box"></div>
                                    </div>

                                    <div class="mp-field">
                                        <label for="form-checkout__expirationDate">Validade</label>
                                        <div id="form-checkout__expirationDate" class="mp-box"></div>
                                    </div>
                                </div>

                                <div class="mp-field full">
                                    <label for="form-checkout__cardNumber">Número do Cartão</label>
                                    <div id="form-checkout__cardNumber" class="mp-box"></div>
                                </div>

                                <div class="field-row">
                                    <div class="field">
                                        <label for="form-checkout__identificationType">Tipo de Documento</label>
                                        <select id="form-checkout__identificationType"></select>
                                    </div>

                                    <div class="field">
                                        <label for="form-checkout__identificationNumber">Número do Documento</label>
                                        <input type="text" id="form-checkout__identificationNumber"
                                            inputmode="numeric" placeholder="12345678900" 
                                            maxlength="14" pattern="[0-9]{11,14}" required />
                                    </div>
                                </div>

                                <div class="field full">
                                    <label for="form-checkout__cardholderEmail">E-mail da Assinatura</label>
                                    <input type="email" id="form-checkout__cardholderEmail"
                                        value="{{ $user->email }}" placeholder="seu@email.com" required />
                                </div>

                                <div class="aux-hidden" aria-hidden="true">
                                    <select id="form-checkout__issuer"></select>
                                    <select id="form-checkout__installments"></select>
                                </div>
                            </div>

                            <div id="status-box" class="status" aria-live="polite"></div>

                            <div class="actions">
                                <button type="submit" id="form-checkout__submit" class="button button-primary">
                                    Adicionar e Pagar
                                </button>
                                <a class="button button-secondary" href="{{ $returnUrl }}">Voltar ao app</a>
                            </div>


                        </form>
                    @endif
                </section>

                <section class="card-preview" aria-hidden="true">
                    <div class="card-preview-top">
                        <span class="wave-icon">)))</span>
                        <span class="brand">VISA</span>
                    </div>

                    <div class="chip"></div>

                    <div class="card-number">1234 1234 1234 1234</div>

                    <div class="card-preview-bottom">
                        <div class="card-name" id="card-preview-name">Seu Nome</div>
                        <div class="card-expiry">12/28</div>
                    </div>
                </section>

                <section id="result-feedback" class="result-feedback" aria-live="polite">
                    <div id="result-icon" class="result-icon">i</div>
                    <div>
                        <p id="result-title" class="result-title">Status do pagamento</p>
                        <p id="result-message" class="result-message"></p>
                    </div>
                </section>
            </div>
        </section>
    </main>

    @if (!$errorMessage)
        <script>
            (() => {
                const publicKey = @json($publicKey);
                const completionUrl = @json($completionUrl);
                const fallbackReturnUrl = @json($returnUrl);

                const statusBox = document.getElementById('status-box');
                const submitButton = document.getElementById('form-checkout__submit');
                const nameInput = document.getElementById('form-checkout__cardholderName');
                const previewName = document.getElementById('card-preview-name');
                const resultFeedback = document.getElementById('result-feedback');
                const resultIcon = document.getElementById('result-icon');
                const resultTitle = document.getElementById('result-title');
                const resultMessage = document.getElementById('result-message');

                const showStatus = (message, kind = 'error') => {
                    statusBox.textContent = message;
                    statusBox.className = `status ${kind} is-visible`;
                };

                const resetStatus = () => {
                    statusBox.textContent = '';
                    statusBox.className = 'status';
                };

                const showResult = (title, message, kind = 'success') => {
                    resultFeedback.className = `result-feedback ${kind} is-visible`;
                    resultTitle.textContent = title;
                    resultMessage.textContent = message;
                    resultIcon.textContent = kind === 'success' ? '✓' : '!';
                };

                const hideResult = () => {
                    resultFeedback.className = 'result-feedback';
                };

                const setSubmitting = (value) => {
                    submitButton.disabled = value;
                    submitButton.textContent = value ? 'Processando...' : 'Adicionar e Pagar';
                };

                const syncPreviewName = () => {
                    const value = nameInput.value.trim();
                    previewName.textContent = value !== '' ? value.toUpperCase() : 'SEU NOME';
                };

                const firstErrorFromPayload = (payload) => {
                    if (!payload || typeof payload !== 'object') return null;

                    if (typeof payload.message === 'string' && payload.message.trim() !== '') {
                        return payload.message.trim();
                    }

                    if (payload.errors && typeof payload.errors === 'object') {
                        for (const value of Object.values(payload.errors)) {
                            if (Array.isArray(value) && value.length > 0 && typeof value[0] === 'string') {
                                return value[0];
                            }
                        }
                    }

                    return null;
                };

                nameInput.addEventListener('input', syncPreviewName);
                syncPreviewName();

                const resolveDeviceSessionId = () => {
                    if (typeof window.MP_DEVICE_SESSION_ID !== 'string') {
                        return null;
                    }

                    const value = window.MP_DEVICE_SESSION_ID.trim();

                    return value !== '' ? value : null;
                };

                const mp = new MercadoPago(publicKey, { locale: 'pt-BR' });

                const cardForm = mp.cardForm({
                    amount: @json((string) $plan['amount']),
                    iframe: true,
                    form: {
                        id: 'form-checkout',
                        cardNumber: {
                            id: 'form-checkout__cardNumber',
                            placeholder: '1234 1234 1234 1234',
                        },
                        expirationDate: {
                            id: 'form-checkout__expirationDate',
                            placeholder: '09/2029',
                        },
                        securityCode: {
                            id: 'form-checkout__securityCode',
                            placeholder: '222',
                        },
                        cardholderName: {
                            id: 'form-checkout__cardholderName',
                            placeholder: 'Nome Completo',
                        },
                        issuer: {
                            id: 'form-checkout__issuer',
                        },
                        installments: {
                            id: 'form-checkout__installments',
                        },
                        identificationType: {
                            id: 'form-checkout__identificationType',
                        },
                        identificationNumber: {
                            id: 'form-checkout__identificationNumber',
                            placeholder: '12345678900',
                        },
                        cardholderEmail: {
                            id: 'form-checkout__cardholderEmail',
                            placeholder: 'voce@email.com',
                        },
                    },
                    callbacks: {
                        onFormMounted: (error) => {
                            if (error) {
                                showStatus('Não foi possível carregar o formulário de cartão do Mercado Pago.');
                                showResult(
                                    'Pagamento indisponível',
                                    'Os campos seguros do cartão não carregaram. Atualize a página e tente novamente.',
                                    'error',
                                );
                            }
                        },
                        onSubmit: async (event) => {
                            event.preventDefault();
                            hideResult();
                            resetStatus();
                            setSubmitting(true);

                            const controller = new AbortController();
                            const timeoutId = setTimeout(() => controller.abort(), 15000);

                            try {
                                const { token } = cardForm.getCardFormData();

                                if (!token) {
                                    clearTimeout(timeoutId);
                                    showStatus('Não foi possível gerar o token seguro do cartão.');
                                    showResult(
                                        'Pagamento negado',
                                        'Verifique os dados do cartão e tente novamente.',
                                        'error',
                                    );
                                    setSubmitting(false);
                                    return;
                                }

                                const response = await fetch(completionUrl, {
                                    method: 'POST',
                                    headers: {
                                        'Accept': 'application/json',
                                        'Content-Type': 'application/json',
                                    },
                                    body: JSON.stringify({
                                        card_token_id: token,
                                        device_session_id: resolveDeviceSessionId(),
                                    }),
                                    signal: controller.signal,
                                });

                                clearTimeout(timeoutId);
                                const payload = await response.json().catch(() => ({}));

                                if (!response.ok) {
                                    const message = firstErrorFromPayload(payload) ??
                                        'O pagamento não pôde ser concluído com este cartão.';

                                    showStatus(message);
                                    showResult('Pagamento negado', message, 'error');
                                    setSubmitting(false);
                                    return;
                                }

                                const redirectUrl = typeof payload.redirect_url === 'string' && payload.redirect_url.trim() !== ''
                                    ? payload.redirect_url.trim()
                                    : fallbackReturnUrl;

                                showStatus('Pagamento aprovado. Retornando ao app...', 'success');
                                showResult(
                                    'Pagamento aprovado',
                                    'Sua assinatura está ativa. Redirecionando para o Dinfy...',
                                    'success',
                                );

                                window.setTimeout(() => {
                                    window.location.href = redirectUrl;
                                }, 1400);
                            } catch (error) {
                                clearTimeout(timeoutId);
                                
                                if (error.name === 'AbortError') {
                                    showStatus('A requisição expirou. Verifique sua conexão e tente novamente.');
                                    showResult(
                                        'Conexão lenta',
                                        'A requisição levou muito tempo. Verifique sua internet.',
                                        'error',
                                    );
                                } else {
                                    showStatus('Não foi possível enviar os dados do pagamento. Tente novamente.');
                                    showResult(
                                        'Problema de conexão',
                                        'Seu pagamento não foi confirmado porque a requisição falhou.',
                                        'error',
                                    );
                                }
                                setSubmitting(false);
                            }
                        },
                    },
                });
            })();
        </script>
    @endif
</body>

</html>
