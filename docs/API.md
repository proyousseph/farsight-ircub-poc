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

Demo login (local seeds):

```json
{ "email": "admin@ircub.test", "password": "Password@123" }
```

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
- Audit log `before_values` / `after_values` are redacted for secrets and provider blobs
- Unlinked payments capped by `IRCUB_MAX_UNLINKED_PAYMENT` (USD after FX for channel initiate)

### Import into Postman

1. Postman → **Import** → select `docs/postman/IRCUB.postman_collection.json`
2. Or import `docs/openapi.yaml` directly.
3. Run **Auth / Login** with header `X-IRCUB-Return-Token: 1` so the test script can store `token`.
