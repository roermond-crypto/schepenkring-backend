# 360dialog Sandbox Testing

Use this flow to connect the 360dialog sandbox before moving a real WhatsApp number into production.

## What the sandbox supports

- Send text messages to the phone number that requested the sandbox API key
- Send supported sandbox templates
- Receive inbound webhook events
- Receive outbound status updates such as `sent`, `delivered`, `read`, and `failed`

## Configure the sandbox channel

1. Get the sandbox API key from 360dialog by messaging `START` to the sandbox number.
2. Expose your backend webhook publicly, for example with an HTTPS tunnel.
3. Register the sandbox in NauticSecure:

```bash
php artisan whatsapp:sandbox:connect LOCATION_ID \
  --api-key=YOUR_SANDBOX_API_KEY \
  --webhook-url=https://YOUR_PUBLIC_DOMAIN/api/webhooks/whatsapp/360dialog \
  --from-number=551146733492 \
  --phone-number-id=OPTIONAL_PHONE_NUMBER_ID \
  --token=YOUR_WEBHOOK_TOKEN
```

The command stores an active `harbor_channels` record and registers the webhook URL with 360dialog.

## Test checklist

1. Send an outbound WhatsApp message from an existing conversation with a contact that has `whatsapp_user_id`.
2. Confirm the message is queued, sent to 360dialog, and receives `sent`, `delivered`, or `read` webhook updates.
3. Reply from the sandbox phone and confirm the inbound webhook lands in the same conversation.
4. Verify the message/contact mapping:
   - same conversation
   - correct contact `whatsapp_user_id`
   - correct location channel
5. Test a template message by sending a chat message with `metadata.whatsapp.template`.
6. Inspect `messages.status`, `messages.delivery_state`, `delivered_at`, `read_at`, and the stored `metadata.whatsapp` payloads.

## Sandbox-specific notes

- The sandbox uses `https://waba-sandbox.360dialog.io`.
- Sandbox sends only to the phone number tied to the sandbox API key.
- Some sandbox sends may not return a message id immediately. The webhook processor now backfills the external id from the first matching status callback.
