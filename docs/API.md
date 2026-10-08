# IRCUB API documentation

## OpenAPI / Swagger

| Resource | Location |
|---|---|
| OpenAPI 3.0 spec | [`openapi.yaml`](openapi.yaml) |
| Swagger UI (local) | http://localhost:8001/docs/api |
| Raw YAML (local) | http://localhost:8001/docs/openapi.yaml |
| Postman collection | [`postman/IRCUB.postman_collection.json`](postman/IRCUB.postman_collection.json) |

> Docs are gated by `IRCUB_DOCS_ENABLED` (on by default in `local` / `testing` only).

### Try it locally

1. Start the API: `cd backend && php artisan serve --host=localhost --port=8001`
2. Open http://localhost:8001/docs/api
3. Authenticate as below.

Demo login (local seeds): use emails from the README seed table. The initial
password is set in `RolePermissionSeeder` and **must be changed** outside the
`testing` environment (`must_change_password`).

### Auth model

IRCUB uses **Laravel Sanctum** with two client modes:

| Client | How auth is sent |
|---|---|
| React SPA | HttpOnly cookie `ircub_token` (`withCredentials: true`). Login JSON does **not** include the raw token. |
| API tools / Postman / tests | Send header `X-IRCUB-Return-Token: 1` on login to receive a Bearer token in the JSON body, then use `Authorization: Bearer <token>`. Query/body `return_token` is ignored. |

Also:

- Cookie default `SameSite=Lax` (`IRCUB_AUTH_COOKIE_SAMESITE`); value is Laravel-encrypted (not raw Sanctum token)
- Mutating browser requests with `Origin`/`Referer` must match `FRONTEND_URL` / CORS allowlist
- Permission middleware enforces role-based access; role/permission changes revoke tokens
- `must_change_password` blocks business routes until `PUT /api/auth/password`
- TOTP 2FA: `POST /api/auth/2fa/setup` → `confirm` (stub OTP only when `IRCUB_2FA_ALLOW_STUB=true`)
- Channel payment list/show/retry responses omit `initiate_payload`, `callback_payload`, and `status_history`
- Channel audit writes strip provider blobs; list/read paths also redact secrets
- Payer **list** never returns `national_id` / `notes` (use show with `payers.view`)
- Unlinked payments capped by `IRCUB_MAX_UNLINKED_PAYMENT` (USD after FX for channel initiate)
- Manual/CSV cash capture is **USD only** (use channel payments for SOS/FX)
- Taxpayer `payments.pay_own`: initiate channel payment for own linked assessment/bill only
- SUCCESS channel callbacks must include `amount` matching the initiated USD amount
- Mock channel/FMIS adapters require `CHANNEL_ALLOW_MOCK` / `FMIS_ALLOW_MOCK` (default on only in local/testing)
- Admin clearing another user’s 2FA requires `admin_password` (actor password)
- Re-running `POST /api/auth/2fa/setup` while 2FA is active requires password (+ OTP if confirmed)
- Channel payment list/show respect `payments.view` / `payments.view_own` (OwnsPayerScope)
- Audit log rows form a SHA-256 hash chain (`prev_hash` / `entry_hash`) and redact secrets on write
- TIN is normalized to uppercase before unique validation
- API error bodies use `SafeHttpError` (domain `InvalidArgumentException` only when `APP_DEBUG=false`)
- Daily FMIS schedule posts the **previous** calendar day at 01:15; reverse keeps journal lines (`reversed_at` + `source_payment_id`, payment link retained)

### Import into Postman

1. Postman → **Import** → select `docs/postman/IRCUB.postman_collection.json`
2. Or import `docs/openapi.yaml` directly.
3. Run **Auth / Login** with header `X-IRCUB-Return-Token: 1` so the test script can store `token`.
4. Run **Auth / Change password** before business calls when `must_change_password` is set (seeded users outside `testing`).
