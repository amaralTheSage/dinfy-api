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
                radial-gradient(circle at top left, rgba(255, 255, 255, 0.65), transparent 26%),
                linear-gradient(180deg, #e6eef4 0%, var(--page-bg) 100%);
        }

        .phone {
            width: min(420px, 100%);
            padding: 10px;
            border-radius: 36px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.86), rgba(255, 255, 255, 0.4));
            box-shadow: 0 28px 60px var(--shadow);
        }

        .screen {
            position: relative;
            overflow: hidden;
            min-height: 780px;
            padding: 18px 18px 20px;
            border-radius: 28px;
            background: linear-gradient(180deg, #f7f8fa 0%, var(--screen) 100%);
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
            background:
                radial-gradient(circle at 20% 15%, rgba(255, 255, 255, 0.72), transparent 22%),
                linear-gradient(180deg, #f7f7f8 0%, #eef1f4 100%);
            overflow: hidden;
        }

        .shadow-blob,
        .hand-top,
        .hand-bottom,
        .arm-top,
        .arm-bottom,
        .terminal,
        .wallet-card {
            position: absolute;
        }

        .shadow-blob {
            width: 220px;
            height: 220px;
            border-radius: 999px;
            filter: blur(10px);
            opacity: 0.18;
            background: rgba(124, 132, 145, 0.8);
        }

        .shadow-blob.one {
            top: 44px;
            right: -56px;
        }

        .shadow-blob.two {
            left: -74px;
            bottom: 32px;
        }

        .arm-top,
        .arm-bottom,
        .hand-top,
        .hand-bottom {
            background: linear-gradient(180deg, #f1dbcf 0%, #e6c3b4 100%);
            box-shadow: 0 18px 26px rgba(87, 66, 56, 0.08);
        }

        .arm-top {
            top: -8px;
            left: 50%;
            width: 92px;
            height: 188px;
            border-radius: 60px 60px 36px 36px;
            transform: translateX(-28%) rotate(8deg);
        }

        .hand-top {
            top: 116px;
            left: 50%;
            width: 88px;
            height: 138px;
            border-radius: 32px;
            transform: translateX(-24%) rotate(8deg);
        }

        .arm-bottom {
            bottom: -10px;
            left: 50%;
            width: 82px;
            height: 184px;
            border-radius: 48px 48px 20px 20px;
            transform: translateX(-50%) rotate(-18deg);
        }

        .hand-bottom {
            bottom: 92px;
            left: 50%;
            width: 92px;
            height: 106px;
            border-radius: 32px;
            transform: translateX(-6%) rotate(-24deg);
        }

        .terminal {
            top: 148px;
            left: 50%;
            width: 84px;
            height: 150px;
            padding: 12px 10px;
            border-radius: 18px;
            background: linear-gradient(180deg, var(--terminal-top) 0%, var(--terminal) 100%);
            transform: translateX(-12%) rotate(16deg);
            box-shadow: 0 20px 30px rgba(20, 25, 31, 0.24);
        }

        .terminal-screen {
            height: 26px;
            margin-bottom: 10px;
            border-radius: 8px;
            background: linear-gradient(180deg, #162f1f 0%, #214d33 100%);
        }

        .terminal-keys {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6px;
        }

        .terminal-keys span {
            height: 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.12);
        }

        .terminal-keys span.green {
            background: #20ad63;
        }

        .terminal-keys span.red {
            background: #eb5757;
        }

        .terminal-keys span.orange {
            background: #f2a541;
        }

        .wallet-card {
            bottom: 122px;
            left: 50%;
            width: 128px;
            height: 82px;
            padding: 16px;
            border-radius: 18px;
            background: linear-gradient(180deg, #f4eae8 0%, var(--card) 100%);
            transform: translateX(-4%) rotate(-21deg);
            box-shadow: 0 20px 30px rgba(86, 72, 75, 0.16);
        }

        .wallet-card::before,
        .wallet-card::after {
            content: "";
            position: absolute;
            top: 18px;
            right: 18px;
            width: 16px;
            height: 16px;
            border-radius: 999px;
            opacity: 0.95;
        }

        .wallet-card::before {
            right: 26px;
            background: #f4b400;
        }

        .wallet-card::after {
            background: var(--accent);
        }

        .wallet-chip {
            width: 18px;
            height: 14px;
            border-radius: 4px;
            background: rgba(147, 129, 129, 0.28);
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
                    {{ strtolower($plan['name']) }}.
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

            <div class="home-indicator" aria-hidden="true"></div>
        </section>
    </main>
</body>

</html>