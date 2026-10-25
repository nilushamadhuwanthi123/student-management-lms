# WhatsApp messages

## What exists now
The LMS does **not** send WhatsApp messages by itself. No WhatsApp provider is connected.

It **prepares** messages, for example absence alerts from the class register and fee reminders built from templates with the child's name, class and amount, and opens each one in WhatsApp through a `wa.me` click-to-chat link. A staff member presses Send in WhatsApp on their own phone or WhatsApp Web.

| Status in the log | Meaning |
|---|---|
| `queued` | Message prepared; nothing has left the LMS |
| `sent` | A staff member **marked it as sent** after opening it in WhatsApp. This is the staff member's word, not a delivery receipt from WhatsApp |
| `skipped` | A staff member chose not to send it (with a note) |

No status means "delivered" or "read". The LMS cannot know that without a provider.

## To send automatically (future work)
This needs a **WhatsApp Business Platform (Cloud API)** account from Meta, or an approved provider:
- a Meta Business account, verified
- a dedicated phone number registered to the WhatsApp Business Platform
- approved message templates (fee reminder, class change, absence alert); Meta must approve every template before use
- per-message charges set by Meta for Sri Lanka

The settings would go in `.env` (never in code): `WHATSAPP_PROVIDER`, `WHATSAPP_TOKEN`, `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_WEBHOOK_SECRET`. Delivery status (`delivered`, `read`, `failed`) would then come from the provider's webhook. This is **not built**; it is an integration task once the client has the account.
