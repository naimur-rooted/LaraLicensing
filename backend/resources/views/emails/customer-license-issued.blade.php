<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Your License Key & Purchase Receipt</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #1e293b; background-color: #f8fafc; margin: 0; padding: 24px; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .header { background: #0f172a; color: #ffffff; padding: 28px 32px; text-align: left; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 600; letter-spacing: -0.025em; }
        .badge { display: inline-block; padding: 4px 10px; font-size: 12px; font-weight: 600; border-radius: 9999px; background-color: #10b981; color: #ffffff; margin-top: 8px; }
        .content { padding: 32px; }
        .key-card { background-color: #f0fdf4; border-radius: 8px; padding: 20px; margin: 20px 0; border: 1px solid #86efac; text-align: center; }
        .key-title { font-size: 12px; font-weight: 700; color: #15803d; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px; }
        .key-box { font-family: monospace; font-size: 18px; font-weight: 700; color: #14532d; letter-spacing: 0.05em; background: #ffffff; padding: 12px 16px; border-radius: 6px; border: 1px dashed #22c55e; word-break: break-all; }
        .details-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
        .details-table td { padding: 8px 0; border-bottom: 1px solid #f1f5f9; }
        .label { color: #64748b; font-weight: 500; }
        .val { font-weight: 600; color: #0f172a; text-align: right; }
        .footer { font-size: 12px; color: #94a3b8; padding: 20px 32px; background-color: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Thank You for Your Order!</h1>
            <span class="badge">Payment Verified ({{ strtoupper($gateway) }})</span>
        </div>
        <div class="content">
            <p>Hello,</p>
            <p>Your payment has been successfully processed for <strong>{{ $productName }}</strong>. Below is your official software license serial key:</p>

            <div class="key-card">
                <div class="key-title">Your Software License Key</div>
                <div class="key-box">{{ $serialKey }}</div>
            </div>

            <table class="details-table">
                <tr>
                    <td class="label">Product:</td>
                    <td class="val">{{ $productName }}</td>
                </tr>
                <tr>
                    <td class="label">Customer Email:</td>
                    <td class="val">{{ $customerEmail }}</td>
                </tr>
                <tr>
                    <td class="label">Payment Provider:</td>
                    <td class="val">{{ ucfirst($gateway) }}</td>
                </tr>
                <tr>
                    <td class="label">Transaction Reference:</td>
                    <td class="val font-mono">{{ $transactionId }}</td>
                </tr>
            </table>

            <p style="margin-top: 24px; font-size: 14px; color: #475569;">
                <strong>Activation Instructions:</strong> Launch the software application, navigate to the Activation / License prompt, paste the key above, and click <em>Activate</em>.
            </p>
        </div>
        <div class="footer">
            Automated Fulfillment &bull; Lara Licensing Protection Engine
        </div>
    </div>
</body>
</html>
