<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Translation Conversion Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Translation Conversion Test</h1>
        <p class="lead">This page demonstrates converting hard-coded strings to translations.</p>

        <hr>

        <h2>Hard-Coded Strings (Ready for Conversion)</h2>

        <div class="alert alert-info">
            <h4>Welcome Message</h4>
            <p>Welcome to our website! We're glad you're here.</p>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                Features
            </div>
            <div class="card-body">
                <h5 class="card-title">Amazing Features</h5>
                <p class="card-text">Our platform offers incredible functionality that you'll love.</p>
                <ul>
                    <li>Easy to use interface</li>
                    <li>Powerful editing tools</li>
                    <li>Multi-language support</li>
                </ul>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h5>Call to Action</h5>
                <p>Ready to get started? Click the button below!</p>
                <a href="#" class="btn btn-primary">Get Started Now</a>
            </div>
        </div>

        <hr>

        <h2>Already Translated (Using @lang directive)</h2>

        <div class="alert alert-success">
            <p>@lang('messages.example.already_translated')</p>
        </div>

        <hr>

        <h2>How to Convert Strings to Translations</h2>
        <ol>
            <li>Enable the CMS toolbar and enter edit mode</li>
            <li>Click on any hard-coded text (like "Welcome to our website!")</li>
            <li>In the inline editor toolbar, look for a "Convert to Translation" button</li>
            <li>Enter a translation key (e.g., "welcome_message")</li>
            <li>Select target locales (e.g., "en", "es", "fr")</li>
            <li>Click "Convert"</li>
            <li>The system will:
                <ul>
                    <li>Replace the hard-coded string with <code>@lang('messages.welcome_message')</code></li>
                    <li>Create translation files for each selected locale</li>
                    <li>Seed them with the original content</li>
                    <li>Make the translation immediately editable</li>
                </ul>
            </li>
        </ol>

        <div class="alert alert-warning mt-4">
            <strong>Note:</strong> After conversion, the text becomes a translation that can be edited per locale.
            Refresh the page to see the <code>@lang()</code> directive in action.
        </div>

        <hr>

        <h2>Testing Checklist</h2>
        <ul class="list-unstyled">
            <li>✓ Hard-coded strings are editable</li>
            <li>✓ "Convert to Translation" button appears in editor</li>
            <li>✓ Modal opens with translation key input</li>
            <li>✓ Locale selection checkboxes work</li>
            <li>✓ Conversion creates translation files</li>
            <li>✓ Source file updated with @lang() directive</li>
            <li>✓ Translation immediately editable after conversion</li>
            <li>✓ Multi-locale support works correctly</li>
        </ul>
    </div>
</body>
</html>
