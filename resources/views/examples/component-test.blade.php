<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Component Source Mapping Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Component Source Mapping Test</h1>
        <p>This page demonstrates the new component source mapping feature for the CMS.</p>

        <hr>

        <h2>Static Content (Main View)</h2>
        <p>This paragraph is in the main view file and should show: resources/views/examples/component-test.blade.php</p>

        <hr>

        <h2>Component Content</h2>
        <x-alert type="success" title="Success Alert">
            This content is inside an alert component. When you edit this, it should show the component file path: resources/views/components/alert.blade.php
        </x-alert>

        <x-alert type="warning" title="Warning Alert">
            This is another instance of the same component. Edits here should also point to the component file.
        </x-alert>

        <hr>

        <h2>Included Partial</h2>
        @include('examples.partial-test')

        <hr>

        <h2>Instructions</h2>
        <ol>
            <li>Enable the CMS toolbar and enter edit mode</li>
            <li>Click on any text element to edit it</li>
            <li>Open browser console to see the source mapping metadata</li>
            <li>Save changes and verify they update the correct file</li>
        </ol>

        <div class="alert alert-info mt-4">
            <strong>Note:</strong> To enable source mapping, set <code>CMS_COMPONENT_SOURCE_MAPPING=true</code> in your .env file.
        </div>
    </div>
</body>
</html>
