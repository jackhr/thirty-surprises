<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error</title>
    <link rel="stylesheet" href="/stylesheets/output.css" />
</head>
<body class="bg-gray-100 text-gray-900 min-h-screen flex items-center justify-center p-6">
    <main class="bg-white border border-gray-200 rounded-lg p-6 max-w-2xl w-full">
        <h1 class="text-2xl font-bold mb-2"><?= e($message ?? 'Unknown error') ?></h1>
        <h2 class="text-sm text-gray-500 mb-4">Status: <?= e((string) ($status ?? 500)) ?></h2>
        <a href="/" class="text-blue-600 hover:text-blue-700 underline">Back to home</a>
    </main>
</body>
</html>
