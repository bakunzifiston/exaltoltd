<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Form Submission</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8fafc; margin: 0; padding: 24px; color: #1e293b; }
        .card { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; }
        .header { background: #011a30; color: #fff; padding: 24px 28px; }
        .header h1 { margin: 0; font-size: 18px; font-weight: 600; }
        .header p { margin: 6px 0 0; font-size: 13px; color: #94a3b8; }
        .body { padding: 24px 28px; }
        .field { margin-bottom: 18px; }
        .label { font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #64748b; margin-bottom: 4px; }
        .value { font-size: 14px; color: #1e293b; }
        .message-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; font-size: 14px; line-height: 1.6; white-space: pre-wrap; }
        .footer { padding: 14px 28px; border-top: 1px solid #f1f5f9; background: #f8fafc; font-size: 12px; color: #94a3b8; }
        a { color: #012b51; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1>New Contact Form Submission</h1>
            <p>{{ config('app.name', 'Exalto Green Ltd') }} · {{ now()->format('D, d M Y H:i') }}</p>
        </div>
        <div class="body">
            <div class="field">
                <div class="label">Name</div>
                <div class="value">{{ $submission->name }}</div>
            </div>
            <div class="field">
                <div class="label">Email</div>
                <div class="value"><a href="mailto:{{ $submission->email }}">{{ $submission->email }}</a></div>
            </div>
            @if($submission->phone)
                <div class="field">
                    <div class="label">Phone</div>
                    <div class="value"><a href="tel:{{ $submission->phone }}">{{ $submission->phone }}</a></div>
                </div>
            @endif
            @if($submission->subject)
                <div class="field">
                    <div class="label">Subject</div>
                    <div class="value">{{ $submission->subject }}</div>
                </div>
            @endif
            <div class="field">
                <div class="label">Message</div>
                <div class="message-box">{{ $submission->message }}</div>
            </div>
        </div>
        <div class="footer">
            This email was sent automatically when a visitor submitted the contact form on the website.
            Reply directly to this email to respond to {{ $submission->name }}.
        </div>
    </div>
</body>
</html>
