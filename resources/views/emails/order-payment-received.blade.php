<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payment Received</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:30px 0;">
  <tr>
    <td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

        {{-- Header --}}
        <tr>
          <td style="background:#ffffff;padding:28px 40px 22px;text-align:center;border-top:6px solid #012b51;">
            <img src="{{ asset('images/brand/exalto-green-logo.png') }}" alt="Exalto Green Ltd" width="220" style="display:inline-block;width:220px;max-width:100%;height:auto;border:0;">
          </td>
        </tr>
        <tr>
          <td style="background:#012b51;padding:10px 40px;text-align:center;border-bottom:3px solid #0d6a3a;">
            <div style="font-size:13px;font-weight:700;color:#cb8d20;letter-spacing:.04em;">Building Tomorrow, Cultivating Today</div>
          </td>
        </tr>

        {{-- Hero message --}}
        <tr>
          <td style="padding:36px 40px 20px;text-align:center;border-bottom:1px solid #e5e7eb;">
            <div style="font-size:36px;margin-bottom:12px;">💳</div>
            <h1 style="margin:0 0 8px;font-size:22px;color:#111827;font-weight:700;">Payment Received</h1>
            <p style="margin:0;font-size:15px;color:#6b7280;">
              Hi <strong>{{ $sale->customer_name }}</strong>, we've received your payment for order
              <strong>{{ $sale->sales_id }}</strong>.
              @if ($sale->balance_due > 0)
                Your order will be processed once the remaining balance is settled.
              @else
                Your order is now being processed.
              @endif
            </p>
          </td>
        </tr>

        {{-- Payment summary --}}
        <tr>
          <td style="padding:28px 40px 0;">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td style="background:#f9fafb;border-radius:6px;padding:16px 20px;">
                  <table width="100%" cellpadding="4" cellspacing="0">
                    <tr>
                      <td style="font-size:13px;color:#6b7280;width:50%;">Order Reference</td>
                      <td style="font-size:13px;color:#111827;font-weight:700;text-align:right;">{{ $sale->sales_id }}</td>
                    </tr>
                    <tr>
                      <td style="font-size:13px;color:#6b7280;">Amount Received</td>
                      <td style="font-size:13px;color:#111827;font-weight:700;text-align:right;">RWF {{ number_format((float) $submission->amount, 0) }}</td>
                    </tr>
                    <tr>
                      <td style="font-size:13px;color:#6b7280;">Payment Method</td>
                      <td style="font-size:13px;color:#111827;text-align:right;">{{ $submission->method }}</td>
                    </tr>
                    @if ($submission->reference)
                    <tr>
                      <td style="font-size:13px;color:#6b7280;">Transaction Reference</td>
                      <td style="font-size:13px;color:#111827;text-align:right;">{{ $submission->reference }}</td>
                    </tr>
                    @endif
                    <tr>
                      <td style="font-size:13px;color:#6b7280;">Order Total</td>
                      <td style="font-size:13px;color:#111827;text-align:right;">RWF {{ number_format((float) $sale->total_revenue, 0) }}</td>
                    </tr>
                    <tr>
                      <td style="font-size:13px;color:#6b7280;">Balance Due</td>
                      <td style="text-align:right;">
                        @if ($sale->balance_due > 0)
                          <span style="font-size:12px;font-weight:600;color:#b45309;background:#fef3c7;padding:2px 10px;border-radius:20px;">RWF {{ number_format($sale->balance_due, 0) }}</span>
                        @else
                          <span style="font-size:12px;font-weight:600;color:#047857;background:#d1fae5;padding:2px 10px;border-radius:20px;">Fully paid</span>
                        @endif
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <tr>
          <td style="padding:24px 40px 32px;">
            <p style="margin:0;font-size:13px;color:#4b5563;">
              We'll be in touch about delivery. Please keep your order reference <strong>{{ $sale->sales_id }}</strong> for any questions.
            </p>
          </td>
        </tr>

        {{-- Footer --}}
        <tr>
          <td style="background:#012b51;padding:20px 40px;text-align:center;">
            <p style="margin:0;font-size:12px;color:#cb8d20;">© {{ date('Y') }} Exalto Green Ltd · Building Tomorrow, Cultivating Today · Rwanda</p>
            <p style="margin:6px 0 0;font-size:11px;color:#b9cbdd;">This email was sent because you placed an order on our website.</p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>

</body>
</html>
