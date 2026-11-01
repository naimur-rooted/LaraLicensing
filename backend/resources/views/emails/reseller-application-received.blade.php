<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>New Reseller Partner Application</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #0f172a; background-color: #f8fafc; margin: 0; padding: 32px 16px;">
  <div style="max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
    
    <!-- Header -->
    <div style="background-color: #0f172a; padding: 24px 32px; border-bottom: 3px solid #10b981;">
      <h2 style="margin: 0; font-size: 18px; color: #ffffff; font-weight: 700; letter-spacing: -0.02em;">
        Lara Licensing • Admin Alert
      </h2>
      <p style="margin: 4px 0 0; font-size: 13px; color: #94a3b8;">
        New Prospective Reseller Partner Application Received
      </p>
    </div>

    <!-- Body -->
    <div style="padding: 32px;">
      <p style="font-size: 15px; line-height: 1.6; margin: 0 0 20px; color: #334155;">
        A new partner application has been submitted from the public landing page. Below are the submission details:
      </p>

      <div style="background: #f1f5f9; border-radius: 8px; padding: 20px; margin-bottom: 24px;">
        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
          <tr>
            <td style="padding: 6px 0; color: #64748b; font-weight: 600; width: 38%;">Company Name:</td>
            <td style="padding: 6px 0; color: #0f172a; font-weight: 700;">{{ $companyName }}</td>
          </tr>
          <tr>
            <td style="padding: 6px 0; color: #64748b; font-weight: 600;">Contact Email:</td>
            <td style="padding: 6px 0; color: #0f172a;">
              <a href="mailto:{{ $contactEmail }}" style="color: #2563eb; text-decoration: none;">{{ $contactEmail }}</a>
            </td>
          </tr>
          @if (!empty($website))
          <tr>
            <td style="padding: 6px 0; color: #64748b; font-weight: 600;">Website:</td>
            <td style="padding: 6px 0; color: #0f172a;">
              <a href="{{ $website }}" target="_blank" style="color: #2563eb; text-decoration: none;">{{ $website }}</a>
            </td>
          </tr>
          @endif
          @if (!empty($desiredPrefix))
          <tr>
            <td style="padding: 6px 0; color: #64748b; font-weight: 600;">Desired Prefix:</td>
            <td style="padding: 6px 0; color: #0f172a; font-family: monospace; font-weight: 700;">{{ $desiredPrefix }}</td>
          </tr>
          @endif
          <tr>
            <td style="padding: 6px 0; color: #64748b; font-weight: 600;">Anticipated Volume:</td>
            <td style="padding: 6px 0; color: #0f172a;">{{ $expectedVolume }} licenses/yr</td>
          </tr>
        </table>
      </div>

      <!-- Action Button -->
      <div style="text-align: center; margin: 32px 0 24px;">
        <a href="{{ $reviewUrl }}"
           style="display: inline-block; padding: 12px 28px; background-color: #10b981; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);">
          Review & Approve in Admin Console &rarr;
        </a>
      </div>

      <p style="font-size: 12px; color: #94a3b8; line-height: 1.5; margin: 0; text-align: center;">
        Direct link: <a href="{{ $reviewUrl }}" style="color: #64748b;">{{ $reviewUrl }}</a>
      </p>
    </div>

    <!-- Footer -->
    <div style="background-color: #f8fafc; padding: 16px 32px; border-top: 1px solid #e2e8f0; font-size: 11px; color: #94a3b8; text-align: center;">
      Automated dispatch by Lara Licensing Engine • Application ID #{{ $applicationId }}
    </div>
  </div>
</body>
</html>
