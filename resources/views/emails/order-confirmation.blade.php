<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order Confirmation</title>
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
            <div style="font-size:36px;margin-bottom:12px;">✅</div>
            <h1 style="margin:0 0 8px;font-size:22px;color:#111827;font-weight:700;">Order Received!</h1>
            <p style="margin:0;font-size:15px;color:#6b7280;">Hi <strong>{{ $sale->customer_name }}</strong>, thank you for your order. We've got it and will be in touch shortly.</p>
          </td>
        </tr>

        {{-- Order summary --}}
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
                      <td style="font-size:13px;color:#6b7280;">Date</td>
                      <td style="font-size:13px;color:#111827;text-align:right;">{{ \Carbon\Carbon::parse($sale->sale_date)->format('d M Y') }}</td>
                    </tr>
                    <tr>
                      <td style="font-size:13px;color:#6b7280;">Payment Status</td>
                      <td style="text-align:right;">
                        <span style="font-size:12px;font-weight:600;color:#b45309;background:#fef3c7;padding:2px 10px;border-radius:20px;">{{ $sale->payment_status }}</span>
                      </td>
                    </tr>
                    <tr>
                      <td style="font-size:13px;color:#6b7280;">Delivery Status</td>
                      <td style="text-align:right;">
                        <span style="font-size:12px;font-weight:600;color:#1d4ed8;background:#dbeafe;padding:2px 10px;border-radius:20px;">{{ $sale->delivery_status }}</span>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        {{-- Items table --}}
        <tr>
          <td style="padding:28px 40px 0;">
            <h3 style="margin:0 0 12px;font-size:14px;text-transform:uppercase;letter-spacing:.05em;color:#374151;">Your Items</h3>
            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
              <thead>
                <tr style="background:#0d6a3a;">
                  <th style="padding:10px 14px;text-align:left;font-size:12px;color:#ffffff;font-weight:600;border-radius-top-left:4px;">Product</th>
                  <th style="padding:10px 14px;text-align:center;font-size:12px;color:#ffffff;font-weight:600;">Qty</th>
                  <th style="padding:10px 14px;text-align:right;font-size:12px;color:#ffffff;font-weight:600;">Unit Price</th>
                  <th style="padding:10px 14px;text-align:right;font-size:12px;color:#ffffff;font-weight:600;">Total</th>
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
                  <td style="padding:10px 14px;font-size:13px;color:#374151;font-weight:600;text-align:right;border-bottom:1px solid #e5e7eb;">
                    RWF {{ number_format((float) $row['line_total'], 0) }}
                  </td>
                </tr>
                @endforeach
              </tbody>
              <tfoot>
                <tr style="background:#f0fdf4;">
                  <td colspan="3" style="padding:12px 14px;font-size:14px;font-weight:700;color:#0d6a3a;text-align:right;">Order Total</td>
                  <td style="padding:12px 14px;font-size:14px;font-weight:700;color:#0d6a3a;text-align:right;">
                    RWF {{ number_format((float) $sale->total_revenue, 0) }}
                  </td>
                </tr>
              </tfoot>
            </table>
          </td>
        </tr>

        {{-- Delivery address --}}
        @if ($sale->delivery_address)
        <tr>
          <td style="padding:24px 40px 0;">
            <h3 style="margin:0 0 8px;font-size:14px;text-transform:uppercase;letter-spacing:.05em;color:#374151;">Delivery Address</h3>
            <p style="margin:0;font-size:13px;color:#4b5563;background:#f9fafb;border-left:3px solid #0d6a3a;padding:12px 16px;border-radius:0 4px 4px 0;">
              {{ $sale->delivery_address }}
            </p>
          </td>
        </tr>
        @endif

        {{-- What happens next --}}
        <tr>
          <td style="padding:28px 40px 0;">
            <h3 style="margin:0 0 12px;font-size:14px;text-transform:uppercase;letter-spacing:.05em;color:#374151;">What Happens Next?</h3>
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td style="vertical-align:top;width:32px;padding-top:2px;">
                  <div style="width:24px;height:24px;background:#0d6a3a;border-radius:50%;text-align:center;line-height:24px;font-size:12px;color:#fff;font-weight:700;">1</div>
                </td>
                <td style="padding:0 0 12px 10px;font-size:13px;color:#374151;">
                  <strong>Order Review</strong> — Our team reviews your order and confirms stock availability.
                </td>
              </tr>
              <tr>
                <td style="vertical-align:top;width:32px;padding-top:2px;">
                  <div style="width:24px;height:24px;background:#0d6a3a;border-radius:50%;text-align:center;line-height:24px;font-size:12px;color:#fff;font-weight:700;">2</div>
                </td>
                <td style="padding:0 0 12px 10px;font-size:13px;color:#374151;">
                  <strong>Confirmation Call / Message</strong> — We will contact you to confirm delivery details and payment.
                </td>
              </tr>
              <tr>
                <td style="vertical-align:top;width:32px;padding-top:2px;">
                  <div style="width:24px;height:24px;background:#0d6a3a;border-radius:50%;text-align:center;line-height:24px;font-size:12px;color:#fff;font-weight:700;">3</div>
                </td>
                <td style="padding:0 0 0 10px;font-size:13px;color:#374151;">
                  <strong>Delivery</strong> — Your order is packed and dispatched to your address.
                </td>
              </tr>
            </table>
          </td>
        </tr>

        {{-- Contact strip --}}
        <tr>
          <td style="padding:28px 40px;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0fdf4;border-radius:6px;padding:0;">
              <tr>
                <td style="padding:16px 20px;">
                  <p style="margin:0 0 10px;font-size:13px;font-weight:700;color:#0d6a3a;">Need help? Reach us anytime:</p>
                  <table width="100%" cellpadding="0" cellspacing="0">
                    @php
                      $phones     = \App\Models\ContactChannel::active()->ofType('phone')->orderBy('is_primary','desc')->orderBy('sort_order')->get();
                      $wpNumbers  = \App\Models\ContactChannel::active()->ofType('whatsapp')->orderBy('is_primary','desc')->orderBy('sort_order')->get();
                      $emails     = \App\Models\ContactChannel::active()->ofType('email')->orderBy('is_primary','desc')->orderBy('sort_order')->get();
                    @endphp
                    @foreach ($phones->take(1) as $ph)
                    <tr>
                      <td style="font-size:13px;color:#374151;padding:3px 0;">
                        📞 <a href="tel:{{ preg_replace('/\D/','',$ph->value) }}" style="color:#0d6a3a;text-decoration:none;">{{ $ph->value }}</a>
                      </td>
                    </tr>
                    @endforeach
                    @foreach ($wpNumbers->take(1) as $wa)
                    <tr>
                      <td style="font-size:13px;color:#374151;padding:3px 0;">
                        💬 <a href="https://wa.me/{{ preg_replace('/\D/','',$wa->value) }}" style="color:#0d6a3a;text-decoration:none;">WhatsApp us</a>
                      </td>
                    </tr>
                    @endforeach
                    @foreach ($emails->take(1) as $em)
                    <tr>
                      <td style="font-size:13px;color:#374151;padding:3px 0;">
                        ✉️ <a href="mailto:{{ $em->value }}" style="color:#0d6a3a;text-decoration:none;">{{ $em->value }}</a>
                      </td>
                    </tr>
                    @endforeach
                  </table>
                </td>
              </tr>
            </table>
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
