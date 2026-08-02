<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Kata Sandi</title>
</head>
<body style="margin:0;padding:0;background-color:#0d0e10;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#0d0e10;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background-color:#1a1c1e;border-radius:24px;padding:32px;border:1px solid rgba(255,255,255,0.1);">
                    <tr>
                        <td align="center" style="font-size:13px;letter-spacing:0.05em;text-transform:uppercase;color:#ffce54;font-weight:bold;">Hydroponic Farm Management</td>
                    </tr>
                    <tr>
                        <td style="padding-top:24px;color:#ffffff;font-size:22px;font-weight:bold;line-height:1.4;">Reset Kata Sandi Anda</td>
                    </tr>
                    <tr>
                        <td style="padding-top:12px;color:#cbd5e1;font-size:14px;line-height:1.6;">
                            Halo {{ $name }},<br><br>
                            Kami menerima permintaan untuk mereset kata sandi akun Anda. Klik tombol di bawah untuk membuat kata sandi baru. Link ini berlaku selama {{ $expire }} menit.
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding-top:28px;">
                            <a href="{{ $url }}" style="display:inline-block;background-color:#ffce54;color:#1a1c1e;text-decoration:none;font-weight:bold;font-size:14px;padding:14px 32px;border-radius:14px;">Reset Kata Sandi</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-top:28px;color:#94a3b8;font-size:13px;line-height:1.6;">
                            Jika Anda tidak meminta reset kata sandi, abaikan email ini.<br><br>
                            — Kita Tumbuh
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
