# EventNest (IT-Project)

Career-event platform: attendees discover, book and pay for events; verified organisers publish events and posts.

## Hosting (Render + Aiven MySQL, Docker)

1. Push this folder to the GitHub repository that Render deploys from (or upload it to a new repo and create a
   Render **Web Service → Docker**).
2. Set these environment variables on the Render service:

| Variable | Value |
| --- | --- |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | Aiven MySQL connection details |
| `EVENTNEST_BASE_URL` | The public **https** address of the site, e.g. `https://eventnest.onrender.com` (no trailing slash) |
| `PAYFAST_MODE` | `sandbox` |
| `EVENTNEST_SMTP_*`, `EVENTNEST_FROM_EMAIL`, `EVENTNEST_PASSWORD_RESET_SECRET` | As before (email) |

3. Deploy. On start-up the container applies the presentation data, `database/presentation_polish.php`
   (post and reaction clean-up) and `database/apply_images.php` (event covers), then serves the site.

### PayFast sandbox

In `sandbox` mode the site uses PayFast's public sandbox merchant (ID `10000100`) automatically, so no PayFast
account access is needed. The old setup failed with **400 Bad Request** because the configured merchant
ID/key/passphrase did not belong together. To use your own sandbox account instead, set
`PAYFAST_USE_OWN_SANDBOX=1` with `PAYFAST_MERCHANT_ID`, `PAYFAST_MERCHANT_KEY` and `PAYFAST_PASSPHRASE`.
`EVENTNEST_BASE_URL` must be the real https address, or PayFast cannot send the payment notification back.

On the PayFast sandbox page, pay with the test wallet; the booking turns *Confirmed* once PayFast's
notification arrives (usually within a few seconds).

## Demo logins (password `EventNestDemo!2026`)

- Attendee: `amina.finance@example.com`
- Organiser: `lerato.finbridge@example.com`
- Low balance attendee (shows the insufficient-funds path): `neo.lowbalance@example.com`
