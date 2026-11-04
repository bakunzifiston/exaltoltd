<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>New Order Notification</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:30px 0;">
  <tr>
    <td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

        {{-- Header --}}
        <tr>
          <td style="background:#1e293b;padding:24px 40px;text-align:center;">
            <div style="font-size:13px;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8;margin-bottom:4px;">Exalto Green Ltd — Internal Alert</div>
            <div style="font-size:20px;font-weight:700;color:#cb8d20;">🛒 New Order Received</div>
          </td>
        </tr>

        {{-- Order ref banner --}}
        <tr>
          <td style="background:#fef3c7;padding:14px 40px;border-bottom:1px solid #fde68a;">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td style="font-size:14px;color:#78350f;font-weight:700;">Reference: {{ $sale->sales_id }}</td>
                <td style="font-size:13px;color:#92400e;text-align:right;">{{ \Carbon\Carbon::parse($sale->sale_date)->format('d M Y, H:i') }}</td>
              </tr>
            </table>
          </td>
        </tr>

        {{-- Customer info --}}
        <tr>
          <td style="padding:28px 40px 0;">
            <h3 style="margin:0 0 12px;font-size:14px;text-transform:uppercase;letter-spacing:.05em;color:#374151;">Customer</h3>
            <table width="100%" cellpadding="4" cellspacing="0" style="background:#f9fafb;border-radius:6px;">
              <tr>
                <td style="font-size:13px;color:#6b7280;padding:8px 16px;width:40%;">Name</td>
                <td style="font-size:13px;color:#111827;font-weight:600;padding:8px 16px;">{{ $sale->customer_name }}</td>
              </tr>
              <tr style="background:#f3f4f6;">
                <td style="font-size:13px;color:#6b7280;padding:8px 16px;">Email</td>
                <td style="font-size:13px;color:#111827;padding:8px 16px;">
                  <a href="mailto:{{ $sale->customer_email }}" style="color:#0d6a3a;text-decoration:none;">{{ $sale->customer_email }}</a>
                </td>
              </tr>
              <tr>
                <td style="font-size:13px;color:#6b7280;padding:8px 16px;">Phone</td>
                <td style="font-size:13px;color:#111827;padding:8px 16px;">
                  <a href="tel:{{ preg_replace('/\D/','',$sale->customer_Phone ?? '') }}" style="color:#0d6a3a;text-decoration:none;">{{ $sale->customer_Phone }}</a>
                </td>
              </tr>
              @if ($sale->delivery_address)
              <tr style="background:#f3f4f6;">
                <td style="font-size:13px;color:#6b7280;padding:8px 16px;vertical-align:top;">Delivery Address</td>
                <td style="font-size:13px;color:#111827;padding:8px 16px;">{{ $sale->delivery_address }}</td>
              </tr>
              @endif
            </table>
          </td>
        </tr>

        {{-- Items table --}}
        <tr>
          <td style="padding:28px 40px 0;">
            <h3 style="margin:0 0 12px;font-size:14px;text-transform:uppercase;letter-spacing:.05em;color:#374151;">Order Items</h3>
            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
              <thead>
                <tr style="background:#374151;">
                  <th style="padding:10px 14px;text-align:left;font-size:12px;color:#ffffff;font-weight:600;">Product</th>
                  <th style="padding:10px 14px;text-align:center;font-size:12px;color:#ffffff;font-weight:600;">Qty</th>
                  <th style="padding:10px 14px;text-align:right;font-size:12px;color:#ffffff;font-weight:600;">Unit Price</th>
                  <th style="padding:10px 14px;text-align:right;font-size:12px;color:#ffffff;font-weight:600;">Line Total</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($rows as $index => $row)
                <tr style="background:{{ $index % 2 === 0 ? '#ffffff' : '#f9fafb' }};">
                  <td style="padding:10px 14px;font-size:13px;color:#111827;border-bottom:1px solid #e5e7eb;">
                    {{ $row['product']->name ?? 'Product' }}
                  </td>
                  <td style="padding:10px 14px;font-size:13px;color:#374151;text-align:center;border-bottom:1px solid #e5e7eb;">
                    {{ (int) $row['quantity'] }}
                  </td>
                  <td style="padding:10px 14px;font-size:13px;color:#374151;text-align:right;border-bottom:1px solid #e5e7eb;">
                    RWF {{ number_format((float) $row['unit_price'], 0) }}
                  </td>
                  <td style="padding:10px 14px;font-size:13px;font-weight:600;color:#374151;text-align:right;border-bottom:1px solid #e5e7eb;">
                    RWF {{ number_format((float) $row['line_total'], 0) }}
                  </td>
                </tr>
                @endforeach
              </tbody>
              <tfoot>
                <tr style="background:#1e293b;">
                  <td colspan="3" style="padding:12px 14px;font-size:14px;font-weight:700;color:#cb8d20;text-align:right;">ORDER TOTAL</td>
                  <td style="padding:12px 14px;font-size:14px;font-weight:700;color:#cb8d20;text-align:right;">
                    RWF {{ number_format((float) $sale->total_revenue, 0) }}
                  </td>
                </tr>
              </tfoot>
            </table>
          </td>
        </tr>

        {{-- Action prompt --}}
        <tr>
          <td style="padding:28px 40px;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#fef3c7;border-radius:6px;border-left:4px solid #cb8d20;">
              <tr>
                <td style="padding:16px 20px;font-size:13px;color:#78350f;">
                  <strong>Action required:</strong> Contact the customer to confirm delivery details and collect payment.
                  The customer is expecting your call or message.
                </td>
              </tr>
            </table>
          </td>
        </tr>

        {{-- Footer --}}
        <tr>
          <td style="background:#1e293b;padding:16px 40px;text-align:center;">
            <p style="margin:0;font-size:11px;color:#64748b;">Exalto Green Ltd Internal Notification · Do not reply to this email</p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>

</body>
</html>
