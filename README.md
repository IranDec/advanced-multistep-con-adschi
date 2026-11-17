# Smart MultiStep Lead Forms 2

Visual multi-step lead forms for WordPress: a drag-and-drop builder with real conditional logic, a fast and accessible frontend, a small lead CRM, partial-lead recovery, a conversion funnel, signed webhooks and confirmation e-mails that cannot be abused for spam.

**Version:** 2.0.0 · **Requires:** WordPress 6.0+, PHP 7.4+ (tested up to PHP 8.4) · **License:** GPLv2 or later
**Author:** [Mohammad Babaei](https://adschi.com)

---

## Highlights

| Area | What you get |
| --- | --- |
| **Builder** | Three-column editor: steps on the left, a live canvas in the middle and an inspector on the right. Drag fields between steps, duplicate, reorder, Ctrl+S to save, live preview (desktop and mobile). |
| **Conditional logic** | "Show this field only if…" (all/any of: is, is not, contains, does not contain, is empty, is filled, greater than, less than) and per-step **jumps** ("if service is *repair* go to *What is wrong?*"). Dead-end steps show a message and a "start over" button. The same engine runs in PHP and JavaScript, so the server never trusts the browser's path. |
| **15 field types** | Text, long text, e-mail, phone, number, website, date, choice (cards with icon/image, buttons, list, dropdown, single or multiple), slider, star rating, address, file upload, consent, text block and hidden (UTM / URL parameter / page URL / referrer / fixed value). |
| **Frontend** | No jQuery. Progress bar or step dots, auto-advance for single-choice steps, optional review page, "continue where you left off", keyboard and screen-reader friendly, four themes (light, dark, glass, minimal) with your own colours, radius, width and font. RTL aware. |
| **Lead CRM** | Dashboard with KPIs and funnel, leads list with search, filters, bulk actions and formula-safe CSV export, lead detail with answers grouped by step, status pipeline (editable), follow-up dates, notes and a full history. |
| **Partial leads** | When a visitor typed an e-mail address or phone number and then stopped, the request is saved as *unfinished* so you can call back. It is completed in place when the visitor sends the form later. |
| **Funnel analytics** | Seen → started → each step → submitted, per form and per day. Only counters, no personal data. |
| **Webhooks** | JSON to Zapier, Make, n8n…, signed with `X-SMLF-Signature: sha256=HMAC(body, secret)`, for completed and optionally unfinished leads. |
| **E-mails** | HTML e-mail to the team with Reply-To set to the visitor. The confirmation to the visitor uses a fixed text without anything the visitor typed and is limited (1 per address per day, 10 per hour, 40 per day). If the *KAS Contact Confirmation* plugin is active it is used instead, so all forms share one set of limits. Every e-mail is logged. |
| **Security** | Signed render stamp with minimum fill time, invisible honeypot, rate limits per IP, optional Cloudflare Turnstile / reCAPTCHA v2 / v3 (fail closed), server-side validation of every answer against the form definition, uploads checked by real file type and stored in a private folder that only administrators can download from, nonces and capability checks on every admin action, SSRF-safe webhook requests. |
| **Privacy** | IP addresses stored shortened, automatic deletion of unfinished leads and (optionally) of all leads after a set time, uninstall keeps or deletes data as you choose. |
| **Translations** | English and German included, `.pot` file for other languages. |

## Installation

1. Copy the `smart-multistep-lead-forms` folder to `wp-content/plugins/` (or upload it as a zip).
2. Activate **Smart MultiStep Lead Forms** in *Plugins*.
3. Open **Lead Forms → Forms**, start from a template (HVAC consultation, contact, feedback or blank) and edit it in the builder.
4. Put the shortcode on any page: `[smlf_form id="123"]`.

## Updating from 1.x

The update is automatic and keeps all data:

- Forms are converted to the new format: cards/radio fields become choice fields, the old step logic rules become jumps, `next step` settings are kept, the *HVAC 3D* theme becomes the dark theme.
- Leads keep their answers, files and admin notes (notes move into the new history).
- Shortcodes stay the same.

Take a database backup before updating a live site, as always.

## Webhook payload

```json
{
  "event": "lead.completed",
  "site": "https://example.com/",
  "form": { "id": 3, "title": "HVAC consultation" },
  "lead": {
    "id": 42, "status": "new", "name": "Erika Test", "email": "erika@example.org", "phone": "",
    "page_url": "https://example.com/beratung/",
    "utm": { "source": "google", "medium": "cpc", "campaign": "" },
    "created_at": "2026-11-17 10:06:00",
    "admin_url": "https://example.com/wp-admin/admin.php?page=smlf-leads&lead=42"
  },
  "answers": {
    "service": { "label": "Service", "value": "new", "text": "New installation" }
  },
  "path": ["service", "new", "contact"]
}
```

The `X-SMLF-Event` header carries the event name as well.

Verify it on your side with `hash_hmac('sha256', $raw_body, $secret)` and compare it to the value after `sha256=` in the `X-SMLF-Signature` header.

## Development

```
smart-multistep-lead-forms/
├── smart-multistep-lead-forms.php   bootstrap, constants
├── includes/                        install/migration, forms, logic, security, uploads,
│                                    leads, stats, mailer, webhooks, submit, public, admin
├── admin/views/                     dashboard, leads, lead, forms, builder, settings, e-mails
├── assets/admin/                    builder.js/.css, admin.js/.css
├── assets/public/                   form.js/.css (frontend renderer)
└── languages/                       .pot, de_DE, fa_IR
```

## Changelog

See [smart-multistep-lead-forms/CHANGELOG.md](smart-multistep-lead-forms/CHANGELOG.md).

---

Developed by [Mohammad Babaei](https://adschi.com) · adschi.com
