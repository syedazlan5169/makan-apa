<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin login | MakanApa?</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="page page-narrow">
    <header class="header">
        <a class="back-link" href="{{ route('makan.index') }}">Back to MakanApa?</a>
        <h1 class="section-title">Admin login</h1>
    </header>

    @if ($errors->any())
        <div class="message message-error">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <section class="card">
        <form method="POST" action="{{ route('admin.login.store') }}">
            @csrf

            <div class="field">
                <label class="label" for="email">Email</label>
                <input class="input" id="email" name="email" type="email" required autocomplete="email" value="{{ old('email') }}">
            </div>

            <div class="field">
                <label class="label" for="password">Password</label>
                <input class="input" id="password" name="password" type="password" required autocomplete="current-password">
            </div>

            <button class="primary-button" type="submit">Log in</button>
        </form>
    </section>
</main>
</body>
</html>