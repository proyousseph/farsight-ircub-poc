<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>IRCUB API — Swagger UI</title>
  <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui.css">
  <style>
    body { margin: 0; background: #fafafa; }
    .topbar { display: none; }
    .ircub-banner {
      background: #0b3d2e;
      color: #fff;
      padding: 12px 24px;
      font-family: system-ui, sans-serif;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
    }
    .ircub-banner a { color: #b8f0d4; }
  </style>
</head>
<body>
  <div class="ircub-banner">
    <div>
      <strong>IRCUB API docs</strong>
      <span> — OpenAPI 3 + Swagger UI</span>
    </div>
    <div>
      Spec: <a href="{{ url('/docs/openapi.yaml') }}">openapi.yaml</a>
      · Demo users use password <code>Password@123</code>
    </div>
  </div>
  <div id="swagger-ui"></div>
  <script src="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui-bundle.js"></script>
  <script>
    window.ui = SwaggerUIBundle({
      url: @json(url('/docs/openapi.yaml')),
      dom_id: '#swagger-ui',
      deepLinking: true,
      presets: [SwaggerUIBundle.presets.apis],
      layout: 'BaseLayout',
      persistAuthorization: true,
    });
  </script>
</body>
</html>
