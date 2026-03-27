<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dinfy </title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

        :root {
            --page-bg: #d9e3ea;
            --frame: rgba(255, 255, 255, 0.72);
            --screen: #f4f5f7;
            --text: #171717;
            --muted: rgba(23, 23, 23, 0.58);
            --shadow: rgba(48, 61, 80, 0.18);
            --terminal: #1f2428;
            --terminal-top: #2a3136;
            --card: #f2e6e3;
            --accent: #ff7a00;
            --success: #18a563;
            --warning-bg: rgba(255, 196, 0, 0.14);
            --warning-text: #7b5b00;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px 16px;
            font-family: "Plus Jakarta Sans", sans-serif;
            color: var(--text);
            background:
                rgba(255, 255, 255, 0.86)
        }

        .phone {
            width: min(420px, 100%);
            padding: 10px;
            border-radius: 36px;

        }

        .screen {
            position: relative;
            overflow: hidden;
            min-height: 780px;
            padding: 18px 18px 20px;
            border-radius: 28px;
        }

        .status-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 4px 12px;
            font-size: 12px;
            font-weight: 700;
        }

        .status-icons {
            display: inline-flex;
            gap: 8px;
            align-items: center;
        }

        .image-wrap {
            display: flex;
            justify-content: center;
            margin: 4px 0 0;
        }

        .image {
            display: block;
            width: min(100%, 380px);
            height: 440px;
            border-radius: 32px;
            object-fit: cover;
        }

        .island {
            position: absolute;
            left: 50%;
            top: 10px;
            width: 96px;
            height: 28px;
            border-radius: 999px;
            background: #0c0c0c;
            transform: translateX(-50%);
        }

        .topbar {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            margin-bottom: 12px;
        }

        .back {
            position: absolute;
            left: 0;
            width: 34px;
            height: 34px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: var(--text);
            background: rgba(32, 36, 39, 0.08);
            font-size: 20px;
        }

        .topbar-title {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .visual {
            position: relative;
            height: 456px;
            margin: 6px -2px 0;
            border-radius: 26px;

            overflow: hidden;
        }


        .content {
            padding: 18px 8px 0;
            text-align: center;
        }

        .eyebrow {
            margin: 0 0 10px;
            color: var(--success);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .content h1 {
            margin: 0;
            font-size: 34px;
            line-height: 1.02;
            letter-spacing: -0.05em;
        }

        .copy {
            margin: 12px auto 0;
            max-width: 280px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.55;
        }

        .plan-pill {
            display: inline-flex;
            margin-top: 14px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(23, 23, 23, 0.06);
            font-size: 13px;
            font-weight: 700;
            gap: 10px;
        }

        .cta {
            display: inline-flex;
            width: 100%;
            margin-top: 22px;
            min-height: 56px;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: #050505;
            color: #fff;
            text-decoration: none;
            font-size: 15px;
            font-weight: 800;
            box-shadow: 0 20px 28px rgba(5, 5, 5, 0.18);
        }

        .warning {
            margin-top: 12px;
            padding: 12px 14px;
            border-radius: 18px;
            background: var(--warning-bg);
            color: var(--warning-text);
            font-size: 12px;
            line-height: 1.5;
            text-align: left;
        }

        .home-indicator {
            width: 132px;
            height: 5px;
            margin: 18px auto 0;
            border-radius: 999px;
            background: rgba(0, 0, 0, 0.76);
        }

        @media (max-width: 480px) {
            body {
                padding: 12px;
            }

            .screen {
                min-height: 740px;
            }

            .visual {
                height: 420px;
            }

            .content h1 {
                font-size: 30px;
            }
        }
    </style>
</head>

@php
    $periodLabel = $plan['frequency'] === 12 ? 'ano' : 'mes';
@endphp

<body>
    <main class="phone">
        <section class="screen">
            <div class="image-wrap">
                <img src="{{ asset('customer_paying.jpg') }}" alt="Pessoa aproximando cartao da maquininha"
                    class="image">
            </div>

            <section class="content">
                <p class="eyebrow">Dinfy</p>
                <h1>Pague de forma segura</h1>
                <p class="copy">
                    Utilizamos o sistema de pagamento do Mercado Pago para garantir segurança aos nossos usuários.
                </p>
                <div class="plan-pill">
                    <span>{{ $plan['name'] }}</span>
                    <span>R$ {{ number_format((float) $plan['amount'], 2, ',', '.') }}/{{ $periodLabel }}</span>
                </div>
                <a href="{{ $checkoutUrl }}" class="cta">Continuar para o checkout</a>

                @if ($errorMessage)
                    <div class="warning">
                        {{ $errorMessage }}
                    </div>
                @endif
            </section>

        </section>
    </main>
</body>

</html>