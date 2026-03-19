Recuperação de senha - Dinfy

Recebemos uma solicitação para redefinir a senha da sua conta.
Use o token abaixo no app para definir uma nova senha:

{{ $token }}

@if (!empty($resetUrl))
Redefinir senha (web):
{{ $resetUrl }}

@endif
@if (!empty($deepLinkUrl))
Abrir no app:
{{ $deepLinkUrl }}

@endif
Esse token expira em {{ $expireMinutes }} minutos.
Se você não solicitou essa alteração, ignore este e-mail.
