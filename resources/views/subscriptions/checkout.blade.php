<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dinfy Premium</title>
    <script src="https://sdk.mercadopago.com/js/v2"></script>
    <style>
        :root {
            --bg: #f5efe5;
            --panel: rgba(255, 255, 255, 0.9);
            --text: #102027;
            --muted: #52616b;
            --accent: #0f766e;
            --accent-soft: rgba(15, 118, 110, 0.12);
            --border: rgba(16, 32, 39, 0.12);
            --danger: #b42318;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Montserrat", system-ui, sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(15, 118, 110, 0.16), transparent 34%),
                radial-gradient(circle at bottom right, rgba(180, 83, 9, 0.16), transparent 28%),
                linear-gradient(180deg, #fff9f0 0%, var(--bg) 100%);
        }

        .shell {
            width: min(960px, calc(100% - 32px));
            margin: 0 auto;
            padding: 32px 0 48px;
        }

        .hero {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 20px;
            margin-bottom: 24px;
        }

        .panel {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 24px;
            box-shadow: 0 24px 70px rgba(16, 32, 39, 0.1);
            backdrop-filter: blur(12px);
        }

        .summary,
        .form-panel,
        .plan-card {
            padding: 28px;
        }

        .eyebrow {
            display: inline-flex;
            padding: 8px 12px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: var(--accent);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        h1 {
            margin: 16px 0 12px;
            font-size: clamp(30px, 4vw, 42px);
            line-height: 1;
        }

        .lead {
            margin: 0;
            color: var(--muted);
            line-height: 1.6;
        }

        .plan-card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: linear-gradient(160deg, #113a3b 0%, #175f61 100%);
            color: #fffdf8;
        }

        .plan-card h2 {
            margin: 0;
            font-size: 20px;
        }

        .price {
            margin: 18px 0 6px;
            font-size: clamp(34px, 5vw, 48px);
            font-weight: 800;
            letter-spacing: -0.04em;
        }

        .price small {
            font-size: 16px;
            font-weight: 600;
            opacity: 0.82;
        }

        .plan-meta {
            display: grid;
            gap: 10px;
            margin-top: 16px;
            font-size: 14px;
            opacity: 0.9;
        }

        .section-title {
            margin: 0 0 18px;
            font-size: 20px;
            font-weight: 700;
        }

        .field-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .field,
        .mp-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .field.full,
        .mp-field.full {
            grid-column: 1 / -1;
        }

        .field label,
        .mp-field label {
            font-size: 13px;
            font-weight: 700;
            color: var(--muted);
        }

        .field input,
        .field select,
        .mp-box {
            width: 100%;
            min-height: 52px;
            padding: 14px 16px;
            border-radius: 16px;
            border: 1px solid rgba(16, 32, 39, 0.14);
            background: rgba(255, 255, 255, 0.9);
            font-size: 15px;
            color: var(--text);
        }

        .field input[readonly] {
            color: rgba(16, 32, 39, 0.68);
            background: rgba(16, 32, 39, 0.04);
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
            margin-top: 18px;
            padding: 14px 16px;
            border-radius: 16px;
            display: none;
            font-size: 14px;
            line-height: 1.5;
        }

        .status.is-visible {
            display: block;
        }

        .status.error {
            background: rgba(180, 35, 24, 0.08);
            border: 1px solid rgba(180, 35, 24, 0.18);
            color: var(--danger);
        }

        .status.success {
            background: rgba(15, 118, 110, 0.09);
            border: 1px solid rgba(15, 118, 110, 0.2);
            color: var(--accent);
        }

        .actions {
            margin-top: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }

        .button {
            appearance: none;
            border: 0;
            border-radius: 16px;
            padding: 15px 20px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: transform 0.18s ease, opacity 0.18s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .button:hover {
            transform: translateY(-1px);
        }

        .button:disabled {
            cursor: wait;
            opacity: 0.72;
            transform: none;
        }

        .button-primary {
            background: var(--accent);
            color: #fff;
            min-width: 220px;
        }

        .button-secondary {
            color: var(--text);
            background: rgba(16, 32, 39, 0.06);
        }

        .microcopy {
            margin-top: 14px;
            font-size: 13px;
            color: var(--muted);
        }

        @media (max-width: 860px) {
            .hero,
            .field-grid {
                grid-template-columns: 1fr;
            }

            .shell {
                padding-top: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="shell">
        <section class="hero">
            <div class="panel summary">
                <span class="eyebrow">Dinfy Premium</span>
                <h1>Pagamento seguro para sua assinatura.</h1>
                <p class="lead">
                    Preencha os dados do cartão nesta página. O token do cartão é gerado pelo Mercado Pago e enviado ao Dinfy para criar sua assinatura recorrente.
                </p>
                <div class="microcopy">
                    O e-mail do Dinfy já será usado automaticamente como identificador da assinatura.
                </div>
            </div>

            <aside class="panel plan-card">
                <div>
                    <h2>{{ $plan['name'] }}</h2>
                    <div class="price">
                        R$ {{ number_format((float) $plan['amount'], 2, ',', '.') }}
                        <small>/ {{ $plan['frequency'] === 12 ? 'ano' : 'mês' }}</small>
                    </div>
                    <div>{{ $plan['reason'] }}</div>
                </div>

                <div class="plan-meta">
                    <div>Frequência: {{ $plan['frequency'] }} {{ $plan['frequency_type'] }}</div>
                    <div>E-mail da assinatura: {{ $user->email }}</div>
                    <div>Pagamento processado pelo Mercado Pago.</div>
                </div>
            </aside>
        </section>

        <section class="panel form-panel">
            <h2 class="section-title">Dados do cartão</h2>

            @if ($errorMessage)
                <div class="status error is-visible">{{ $errorMessage }}</div>
                <div class="actions">
                    <a class="button button-secondary" href="{{ $returnUrl }}">Voltar ao app</a>
                </div>
            @else
                <form id="form-checkout">
                    <div class="field-grid">
                        <div class="mp-field full">
                            <label for="form-checkout__cardNumber">Número do cartão</label>
                            <div id="form-checkout__cardNumber" class="mp-box"></div>
                        </div>

                        <div class="mp-field">
                            <label for="form-checkout__expirationDate">Validade</label>
                            <div id="form-checkout__expirationDate" class="mp-box"></div>
                        </div>

                        <div class="mp-field">
                            <label for="form-checkout__securityCode">Código de segurança</label>
                            <div id="form-checkout__securityCode" class="mp-box"></div>
                        </div>

                        <div class="field full">
                            <label for="form-checkout__cardholderName">Nome impresso no cartão</label>
                            <input type="text" id="form-checkout__cardholderName" autocomplete="cc-name" />
                        </div>

                        <div class="field">
                            <label for="form-checkout__identificationType">Documento</label>
                            <select id="form-checkout__identificationType"></select>
                        </div>

                        <div class="field">
                            <label for="form-checkout__identificationNumber">Número do documento</label>
                            <input type="text" id="form-checkout__identificationNumber" inputmode="numeric" />
                        </div>

                        <div class="field full">
                            <label for="form-checkout__cardholderEmail">E-mail da assinatura</label>
                            <input
                                type="email"
                                id="form-checkout__cardholderEmail"
                                value="{{ $user->email }}"
                                readonly
                            />
                        </div>

                        <div class="aux-hidden" aria-hidden="true">
                            <select id="form-checkout__issuer"></select>
                            <select id="form-checkout__installments"></select>
                        </div>
                    </div>

                    <div id="status-box" class="status"></div>

                    <div class="actions">
                        <button type="submit" id="form-checkout__submit" class="button button-primary">
                            Confirmar assinatura
                        </button>
                        <a class="button button-secondary" href="{{ $returnUrl }}">Voltar ao app</a>
                    </div>

                    <div class="microcopy">
                        Ao confirmar, o Dinfy usa o token seguro do cartão para criar sua assinatura no Mercado Pago.
                    </div>
                </form>
            @endif
        </section>
    </div>

    @if (!$errorMessage)
        <script>
            (() => {
                const publicKey = @json($publicKey);
                const completionUrl = @json($completionUrl);
                const fallbackReturnUrl = @json($returnUrl);
                const statusBox = document.getElementById('status-box');
                const submitButton = document.getElementById('form-checkout__submit');

                const showStatus = (message, kind = 'error') => {
                    statusBox.textContent = message;
                    statusBox.className = `status ${kind} is-visible`;
                };

                const setSubmitting = (value) => {
                    submitButton.disabled = value;
                    submitButton.textContent = value ? 'Processando...' : 'Confirmar assinatura';
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

                const mp = new MercadoPago(publicKey, { locale: 'pt-BR' });

                const cardForm = mp.cardForm({
                    amount: @json((string) $plan['amount']),
                    iframe: true,
                    form: {
                        id: 'form-checkout',
                        cardNumber: {
                            id: 'form-checkout__cardNumber',
                            placeholder: 'Número do cartão',
                        },
                        expirationDate: {
                            id: 'form-checkout__expirationDate',
                            placeholder: 'MM/AA',
                        },
                        securityCode: {
                            id: 'form-checkout__securityCode',
                            placeholder: 'CVV',
                        },
                        cardholderName: {
                            id: 'form-checkout__cardholderName',
                            placeholder: 'Nome no cartão',
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
                            placeholder: 'CPF ou documento',
                        },
                        cardholderEmail: {
                            id: 'form-checkout__cardholderEmail',
                            placeholder: 'seu@email.com',
                        },
                    },
                    callbacks: {
                        onFormMounted: (error) => {
                            if (error) {
                                showStatus('Não foi possível carregar o formulário do Mercado Pago.');
                            }
                        },
                        onSubmit: async (event) => {
                            event.preventDefault();
                            setSubmitting(true);
                            statusBox.className = 'status';

                            try {
                                const { token } = cardForm.getCardFormData();
                                if (!token) {
                                    showStatus('Não foi possível gerar o token do cartão. Revise os dados e tente novamente.');
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
                                    }),
                                });

                                const payload = await response.json().catch(() => ({}));

                                if (!response.ok) {
                                    showStatus(firstErrorFromPayload(payload) ?? 'Não foi possível concluir o pagamento.');
                                    setSubmitting(false);
                                    return;
                                }

                                const redirectUrl = typeof payload.redirect_url === 'string' && payload.redirect_url.trim() !== ''
                                    ? payload.redirect_url.trim()
                                    : fallbackReturnUrl;

                                showStatus('Assinatura criada. Voltando ao app...', 'success');
                                window.location.href = redirectUrl;
                            } catch (_) {
                                showStatus('Falha ao enviar o token do cartão. Tente novamente.');
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
