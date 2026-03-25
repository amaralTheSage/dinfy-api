<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperação de senha - Dinfy</title>
</head>

<body style="margin:0;padding:0;background:#f6f7f9;font-family:Arial,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
                    style="max-width:620px;background:#ffffff;border-radius:12px;padding:28px;">
                    <tr>
                        <td style="font-size:24px;font-weight:700;color:#0f172a;padding-bottom:8px;">
                            Dinfy
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size:20px;font-weight:600;color:#111827;padding-bottom:14px;">
                            Recuperação de senha
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size:15px;line-height:1.6;color:#374151;padding-bottom:10px;">
                            Recebemos uma solicitação para redefinir a senha da sua conta.
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size:15px;line-height:1.6;color:#374151;padding-bottom:16px;">
                            Use o token abaixo no app para definir uma nova senha:
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-bottom:18px;">
                            <div
                                style="display:inline-block;padding:12px 16px;border-radius:8px;background:#111827;color:#ffffff;font-size:16px;font-weight:700;letter-spacing:0.5px;">
                                {{ $token }}
                            </div>
                        </td>
                    </tr>
                    @if (!empty($resetUrl))
                        <tr>
                            <td style="padding-bottom:18px;">
                                <a href="{{ $resetUrl }}"
                                    style="display:inline-block;background:#111827;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:8px;font-size:15px;font-weight:600;">
                                    Redefinir senha
                                </a>
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td style="font-size:14px;line-height:1.6;color:#4b5563;padding-bottom:6px;">
                            Esse token expira em {{ $expireMinutes }} minutos.
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size:14px;line-height:1.6;color:#4b5563;">
                            Se você não solicitou essa alteração, ignore este e-mail.
                        </td>
                    </tr>

                    @if (!empty($deepLinkUrl))
                        <tr>
                            <td style="font-size:12px;line-height:1.5;color:#6b7280;padding-top:8px;word-break:break-all;">
                                Link alternativo:
                                <a href="{{ $deepLinkUrl }}"
                                    style="color:#2563eb;text-decoration:underline;word-break:break-all;">
                                    {{ $deepLinkUrl }}
                                </a>
                            </td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>
</body>

</html>