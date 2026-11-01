<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Welcome to the Reseller Partner Network</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #1e293b; background-color: #f8fafc; margin: 0; padding: 24px; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .header { background: #0f172a; color: #ffffff; padding: 28px 32px; text-align: left; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 600; letter-spacing: -0.025em; }
        .badge { display: inline-block; padding: 4px 10px; font-size: 12px; font-weight: 600; border-radius: 9999px; background-color: #10b981; color: #ffffff; margin-top: 8px; }
        .content { padding: 32px; }
        .card { background-color: #f1f5f9; border-radius: 8px; padding: 20px; margin: 20px 0; border: 1px solid #cbd5e1; }
        .card-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; }
        .card-row:last-child { margin-bottom: 0; }
        .label { color: #64748b; font-weight: 500; }
        .val { font-weight: 600; color: #0f172a; font-family: monospace; font-size: 15px; }
        .pwd-highlight { background-color: #fef08a; padding: 3px 8px; border-radius: 4px; border: 1px dashed #ca8a04; }
        .button { display: inline-block; background-color: #0d9488; color: #ffffff; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 14px; margin-top: 16px; }
        .footer { font-size: 12px; color: #94a3b8; padding: 20px 32px; background-color: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome to the Reseller Partner Network</h1>
            <span class="badge">Approved Partner</span>
        </div>
        <div class="content">
            <p>Hello <strong>{{ $companyName }}</strong>,</p>
            <p>Congratulations! Your reseller partner application has been approved by the platform administrator. Your reseller account and dedicated tenant are now active.</p>

            <div class="card">
                <div class="card-row">
                    <span class="label">Portal Login Email:</span>
                    <span class="val">{{ $loginEmail }}</span>
                </div>
                <div class="card-row">
                    <span class="label">License Key Prefix:</span>
                    <span class="val">{{ $prefix }}</span>
                </div>
                <div class="card-row">
                    <span class="label">Temporary Password:</span>
                    <span class="val pwd-highlight">{{ $temporaryPassword }}</span>
                </div>
            </div>

            <p>Please log in to your partner dashboard to begin managing your customers, license keys, and API quotas:</p>

            <a href="{{ $loginUrl }}" class="button" target="_blank">Log In to Reseller Portal</a>

            <p style="margin-top: 24px; font-size: 13px; color: #64748b;">
                * We recommend changing your password under your profile settings immediately upon first login.
            </p>
        </div>
        <div class="footer">
            Automated Reseller Service &bull; Licensing Operations Team
        </div>
    </div>
</body>
</html>
