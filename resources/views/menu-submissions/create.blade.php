<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suggest a menu | MakanApa?</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="page page-narrow">
    <header class="header">
        <a class="back-link" href="{{ route('makan.index') }}">Back to MakanApa?</a>
        <h1 class="section-title">Suggest a menu</h1>
        <p class="subtitle">Suggest an item from an existing category. It will be added after admin approval.</p>
    </header>

    @if (session('success'))
        <div class="message message-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="message message-error">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <section class="card">
        <form method="POST" action="{{ route('menu-submissions.store') }}">
            @csrf

            <div class="field">
                <label class="label" for="menu_category_id">Category</label>
                <select class="select" id="menu_category_id" name="menu_category_id" required>
                    <option value="">Choose a category</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('menu_category_id') == $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label class="label" for="name">Menu name</label>
                <input class="input" id="name" name="name" type="text" required maxlength="255" value="{{ old('name') }}" placeholder="e.g. Nasi Goreng Cili Padi">
            </div>

            <button class="primary-button" type="submit">Submit suggestion</button>
        </form>
    </section>
</main>
</body>
</html>