<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>NautiSecure API Docs</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css" />
    <style>
        body { margin: 0; background: #f7f7f7; }
        #swagger-ui { margin: 0; }
    </style>
</head>
<body>
<div id="swagger-ui"></div>
<script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
<script>
    window.onload = function () {
        SwaggerUIBundle({
            url: '/docs/openapi.yaml',
            dom_id: '#swagger-ui',
            docExpansion: 'none',
            displayRequestDuration: true,
            filter: true,
            supportedSubmitMethods: [],
        });
    };
</script>
</body>
</html>
