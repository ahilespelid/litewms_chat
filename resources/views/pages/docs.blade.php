<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LiteWMS Chat — API</title>
    <link rel="stylesheet" href="/vendor/swagger-ui/swagger-ui.css">
    <style>body{margin:0}.swagger-ui .topbar{display:none}</style>
</head>
<body>
<div id="swagger-ui"></div>
<script src="/vendor/swagger-ui/swagger-ui-bundle.js"></script>
<script>
    window.ui = SwaggerUIBundle({
        url: '/docs/openapi.json',
        dom_id: '#swagger-ui',
        deepLinking: true,
        persistAuthorization: true,
        tryItOutEnabled: true,
        docExpansion: 'list',
        defaultModelsExpandDepth: 0,
        tagsSorter: 'alpha',
    });
</script>
</body>
</html>
