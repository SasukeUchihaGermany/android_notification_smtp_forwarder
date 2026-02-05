Android SMTP Notification App

Lightweight Android application for sending email notifications via SMTP directly from an Android device.
Designed to run headless (no UI) and trigger emails programmatically from app logic in background services.

Features

📧 Send emails via SMTP (TLS)

🤖 No UI – runs silently in background

⚡ Trigger-based (emails send only when code calls it)

🧵 Suitable for Services / Workers / event hooks

🔧 Simple integration into existing Android projects

How It Works

Uses SMTP over TLS (smtp.gmail.com:587)

Email send logic is invoked explicitly from code

Credentials are injected via Gradle properties

Runs inside the app process (no external dependencies)

Configuration
1. gradle.properties
SMTP_USER=your_email@gmail.com
SMTP_PASS=your_app_password
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
MAIL_TO=recipient@email.com


⚠️ Security warning
These values are embedded in the APK at build time.
Do NOT use real or long-term credentials in production builds.
This is done to deter malicious use of the app, this is intended for personal use only and at your own discretion.

2. Email Sending Logic

Emails are sent only when explicitly triggered by your code.

Example (conceptual):

SmtpMailer.send(
    subject,
    body
);


There is no automatic scheduling unless you add:

WorkManager

AlarmManager

Foreground/background services

Send Frequency

❌ Not periodic by default

✅ Sends once per trigger

✅ Fully controlled by app logic

If you want periodic or rate-limited emails, implement:

WorkManager with constraints

Internal debounce / cooldown logic

Common Failure Causes

Invalid SMTP credentials

Gmail account not using App Passwords

Network unavailable

TLS blocked by device / network

Check logs under:

SmtpMailer

Security Notes

This project is not production-secure by default.

Avoid in production:

Hardcoded credentials

Personal Gmail accounts

Shipping SMTP secrets inside APKs

Safer alternatives:

Backend relay (API → SMTP)

Token-based notification service

Server-side mail dispatch

Intended Use Cases

Internal tools

Device alerts

Prototypes / PoCs

Controlled environments

Developer notifications

License

Internal / private use.
Do not redistribute with embedded credentials.
