<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; max-width: 600px; margin: 0 auto; padding: 40px 20px; color: #111;">
    <h2 style="margin-bottom: 8px;">You've been invited</h2>
    <p style="color: #555;">{{ $inviterName }} has invited you to join the <strong>Customer Happiness</strong> dashboard at The Despatch Company.</p>

    <p style="margin: 32px 0;">
        <a href="{{ $url }}" style="display: inline-block; padding: 12px 24px; background: #000; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 500;">
            Accept Invitation
        </a>
    </p>

    <p style="color: #888; font-size: 13px;">This invitation expires on {{ $expiresAt }}.</p>
    <p style="color: #888; font-size: 13px;">If you weren't expecting this email, you can safely ignore it.</p>
</body>
</html>
