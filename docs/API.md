# IRCUB API documentation

## OpenAPI / Swagger

| Resource | Location |
|---|---|
| OpenAPI 3.0 spec | [`openapi.yaml`](openapi.yaml) |
| Swagger UI (local) | http://127.0.0.1:8001/docs/api |
| Raw YAML (local) | http://127.0.0.1:8001/docs/openapi.yaml |
| Postman collection | [`postman/IRCUB.postman_collection.json`](postman/IRCUB.postman_collection.json) |

### Try it locally

1. Start the API: `cd backend && php artisan serve --host=127.0.0.1 --port=8001`
2. Open http://127.0.0.1:8001/docs/api
3. Click **Authorize** and paste a Bearer token from login, or use the **Auth → Login** request in Swagger / Postman first.

Demo login:

```json
{ "email": "admin@ircub.test", "password": "Password@123" }
```

### Import into Postman

1. Postman → **Import** → select `docs/postman/IRCUB.postman_collection.json`
2. Or import `docs/openapi.yaml` directly (Postman supports OpenAPI).
3. Run **Auth / Login** — the test script stores `token` on the collection.

### Auth model

- Laravel Sanctum personal access tokens
- Header: `Authorization: Bearer <token>`
- Permission middleware enforces role-based access (see seeders)
