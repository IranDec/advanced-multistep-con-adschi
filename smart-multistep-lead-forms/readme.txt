=== Smart MultiStep Lead Forms ===
Contributors: mohammadbabaei
Tags: multistep form, lead form, conditional logic, crm, form builder
Requires at least: 6.0
Requires PHP: 7.4
Tested up to: 6.9
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Visual multi-step lead forms with conditional logic, a lead CRM, partial-lead recovery, a conversion funnel, signed webhooks and abuse-safe e-mails.

== Description ==

Build multi-step request forms in a visual editor and manage the requests in a small CRM inside WordPress. Embed a form anywhere with `[smlf_form id="123"]`.

= Builder =
* Three-column editor with live canvas, drag and drop between steps, duplicate, Ctrl+S and a desktop/mobile preview.
* Conditional fields ("show only if", all/any, 8 operators) and step jumps; dead-end steps with a "start over" button.
* 15 field types: text, long text, e-mail, phone, number, website, date, choice (cards with icon or image, buttons, list, dropdown), slider, rating, address, file upload, consent, text block, hidden (UTM, URL parameter, page URL, referrer, fixed value).
* Ready-made templates: HVAC consultation, contact, feedback, blank.

= Frontend =
* Lightweight, no jQuery, keyboard and screen-reader friendly, RTL aware.
* Progress bar or step dots, auto-advance, review page, "continue where you left off".
* Themes: light, dark, glass, minimal - with your own colours, radius, width and font.

= Leads =
* Dashboard with KPIs, conversion funnel and daily chart.
* Leads list with search, filters, bulk actions and CSV export.
* Lead detail with answers per step, editable status pipeline, follow-up dates, notes and history.
* Unfinished requests are saved once an e-mail address or phone number was entered, and completed in place later.

= Security and privacy =
* Signed form stamp with minimum fill time, honeypot, rate limits, optional Turnstile / reCAPTCHA v2 / v3.
* Every answer is validated on the server against the form definition, including the path through the steps.
* Uploads are checked by real file type and stored privately (admin-only download).
* Visitor confirmations use a fixed text and strict limits, so the form cannot be misused to send spam.
* Shortened IP addresses, automatic clean-up, signed webhooks.

== Installation ==

1. Upload the `smart-multistep-lead-forms` folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Go to Lead Forms > Forms, start from a template and adjust it in the builder.
4. Add the shortcode `[smlf_form id="123"]` to a page.

== Frequently Asked Questions ==

= Does the update from 1.x keep my forms and leads? =
Yes. Forms are converted automatically (old step logic becomes jumps) and leads keep their answers, files and notes. Shortcodes stay the same. Please take a backup first.

= Where are uploaded files stored? =
In `wp-content/uploads/smlf-private/` with random names, protected against direct access. Administrators download them from the lead view.

= Can visitors abuse the confirmation e-mail? =
No. The confirmation never contains what the visitor typed, is skipped for spam-like input and is limited per address, per hour and per day.

= How do I verify webhooks? =
Compare `hash_hmac( 'sha256', $raw_body, $secret )` with the value after `sha256=` in the `X-SMLF-Signature` header.

== Changelog ==

= 2.0.0 =
* Complete rewrite: new builder, conditional logic engine, frontend, lead CRM, funnel analytics, signed webhooks, private uploads and abuse-safe e-mails. Automatic migration from 1.x.


= 1.3.4 =
* Added colored Prospects overview cards for total requests, completed requests, auto-saved requests, and new requests today.

= 1.3.3 =
* Added bulk request deletion on the Prospects page, including selected-request deletion and delete-all controls.
* Added confirmation prompts, selection counts, and cleanup of related email logs when requests are deleted.

= 1.3.2 =
* Unified admin, builder, and template text so translations are resolved through WordPress translation files.
* Removed hard-coded manual locale branches for builder labels and the default consultation template.

= 1.3.1 =
* Expanded default admin email content to include lead metadata and submitted form details.
* Added customer name placeholder support for customer notification emails.

= 1.3.0 =
* Added a delete action for forms on the Forms admin page while keeping existing leads.

= 1.2.9 =
* Added configurable admin and customer email templates.
* Added branded HTML email layout with automatic site logo/site icon support.
* Added form-language-aware notification emails.
* Enhanced Interessenten with full request details, files, source page, search, filters, sorting, and inline lead status updates.
* Added source page tracking for submissions.

= 1.2.8 =
* Added multi-rule conditional logic controls in the form builder.
* Added template language selection for ready-made templates.
* Added admin lead pipeline status with inline updates.
* Added file upload preview with per-file removal before submit.
* Added HVAC 3D success summary after submission.

= 1.2.7 =
* Version bump after the multilingual form and HVAC branching refinements.

= 1.2.6 =
* Improved HVAC 3D select and upload-field contrast for dark layouts.
* Upload file formats are now always read from global module settings.
* Replaced the upload extension text field with grouped selectable format buttons in Settings.
* Added per-form language selection for multilingual sites.
* Updated the HVAC 3D template so each service category opens its own second-step questions.

= 1.2.5 =
* Fixed required-field validation when category cards auto-advance to the next step.
* Improved frontend input styling and equalized clickable card heights.
* Added configurable upload limits for allowed extensions, file count, and file size.
* Added server-side upload validation based on each form's settings.
* Improved the form builder interface and added admin overview statistic cards.
* Added field width, display mode, font, color, and theme controls.
* Added a modern animated HVAC 3D sample template.
* Added consent checkbox block with linked text, default state, new-tab links, WordPress page popups, and custom popup text.
* Added an uninstall data policy setting to keep or delete all plugin data when uninstalling.
* Changed templates so they are no longer installed automatically; users load the templates they want from the builder.
* Moved upload limits into global plugin settings.
* Reworked validation to show animated inline field errors instead of browser alerts.
* Added required stars beside required field labels.
* Reduced builder clutter with collapsible field setting panels.

= 1.2.4 =
* Fixed frontend template safety for older/cached form schemas.
* Reduced Divi layout conflicts by keeping frontend styling more tightly contained.

= 1.2.3 =
* Added server-side initial display states so Divi/deferred scripts do not leave the form blank.

= 1.2.2 =
* Improved embedded form layout and captcha gate rendering on frontend pages.
* Reduced layout conflicts with WordPress themes and page builders.

= 1.2.1 =
* Fixed builder block click/drag handling and live preview rendering.
* Added per-form captcha method and display timing settings.
* Improved frontend layout isolation, form height, and captcha overlay interaction.

= 1.2.0 =
* Added animated consultation-ready frontend theme.
* Added default localized free consultation form template.
* Added textarea, message, and drag-and-drop file upload fields.
* Added automatic partial save only after valid email input.
* Added lead detail/file preview in the admin leads list.

= 1.1.0 =
* Hardened AJAX form save, partial lead save, final submit, captcha verification, and CSV export.
* Fixed editing existing forms in the builder.
* Added stable field IDs and required-field metadata while preserving existing form data.
* Added frontend required/email validation and safer AJAX error handling.
* Added settings sanitization and nonce-protected CSV export.
* Documented shortcode usage, captcha settings, webhook payloads, and compatibility targets.

= 1.0.0 =
* Initial release.
