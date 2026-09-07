<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>MakanApa?</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])
</head>

<body>

<main class="page">

    <header class="header">

        <div class="logo">
            🍜
        </div>

        <h1 class="title">
            MakanApa?
        </h1>

        <p class="subtitle">
            Too many choices?
            Tell us what you're in the mood for and
            we'll make the difficult decision for you.
        </p>

    </header>


    @if (session('success'))

        <div class="message message-success">
            {{ session('success') }}
        </div>

    @endif


    @if ($errors->any())

        <div class="message message-error">

            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach

        </div>

    @endif


    <section class="card">

        @if ($rememberedPersonName)

            <div class="field">
                <label class="label">Your name</label>
                <div class="remembered-name">
                    {{ $rememberedPersonName }}
                </div>
            </div>

        @endif

        <form
            method="POST"
            action="{{ route('makan.pick') }}#recommendation"
        >

            @csrf

            @if (! $rememberedPersonName)

                <div class="field">

                    <label
                        class="label"
                        for="person_name"
                    >
                        Your name
                    </label>

                    <input
                        class="input"
                        id="person_name"
                        name="person_name"
                        type="text"
                        required
                        autocomplete="name"
                        value="{{ old('person_name', '') }}"
                        placeholder="e.g. Azlan"
                    >

                </div>

            @else

                <input
                    type="hidden"
                    name="person_name"
                    value="{{ $rememberedPersonName }}"
                >

            @endif


            <div class="field">

                <label
                    class="label"
                    for="category_id"
                >
                    What are you in the mood for?
                </label>

                <select
                    class="select"
                    id="category_id"
                    name="category_id"
                >

                    <option value="">
                        Anything — surprise me
                    </option>

                    @foreach ($categories as $category)

                        <option
                            value="{{ $category->id }}"
                            @selected(
                                old(
                                    'category_id',
                                    $selectedCategoryId ?? null
                                ) == $category->id
                            )
                        >
                            {{ $category->name }}
                        </option>

                    @endforeach

                </select>

            </div>


            <button
                class="primary-button"
                type="submit"
            >
                🎲 Choose for me
            </button>

        </form>

        <a class="secondary-link" href="{{ route('menu-submissions.create') }}">
            Suggest a menu
        </a>

        @if ($rememberedPersonName)

            <form
                method="POST"
                action="{{ route('makan.switch') }}"
                class="switch-user-form"
            >

                @csrf

                <button
                    class="secondary-button"
                    type="submit"
                >
                    Not you? Switch user
                </button>

            </form>

        @endif

    </section>


    @isset($menuItem)

        @if ($menuItem)

            <section class="result-card" id="recommendation">

                <p class="result-label">
                    Today's recommendation
                </p>
                @if (($rejectedCount ?? 0) > 0)

                    <div class="rejection-count">
                        {{ $rejectedCount }}
                        {{ Str::plural('option', $rejectedCount) }}
                        skipped
                    </div>

                @endif

                <h2 class="result-name">
                    {{ $menuItem->name }}
                </h2>

                <div class="result-category">
                    {{ $menuItem->category->name }}
                </div>


                @if ($menuItem->description)

                    <p class="result-category">
                        {{ $menuItem->description }}
                    </p>

                @endif


                @if ($menuItem->price !== null)

                    <div class="result-price">
                        RM {{ number_format((float) $menuItem->price, 2) }}
                    </div>

                @endif


                <div class="result-actions">

                    <form
                        method="POST"
                        action="{{ route('makan.accept') }}"
                    >

                        @csrf

                        <input
                            type="hidden"
                            name="person_name"
                            value="{{ $personName }}"
                        >

                        <input
                            type="hidden"
                            name="menu_item_id"
                            value="{{ $menuItem->id }}"
                        >

                        <button
                            class="accept-button"
                            type="submit"
                        >
                            ✓ I'll have this
                        </button>

                    </form>


                    <form
                        method="POST"
                        action="{{ route('makan.pick') }}#recommendation"
                    >

                        @csrf

                        <input
                            type="hidden"
                            name="person_name"
                            value="{{ $personName }}"
                        >

                        <input
                            type="hidden"
                            name="category_id"
                            value="{{ $selectedCategoryId ?? '' }}"
                        >

                        <input
                            type="hidden"
                            name="rejected_item_id"
                            value="{{ $menuItem->id }}"
                        >

                        <button
                            class="reroll-button"
                            type="submit"
                        >
                            🎲 Pick something else
                        </button>

                    </form>

                </div>

            </section>


        @else

            <div class="message message-error">
                No available menu items were found
                for the selected category.
            </div>

        @endif

    @endisset


    <section class="history">

        <div class="history-heading">

            <h2 class="history-title">
                Recent choices
            </h2>

            <span class="history-caption">
                Latest 10
            </span>

        </div>


        @if ($recentChoices->isEmpty())

            <div class="empty">
                No decisions yet.
                Someone has to be brave enough to go first. 😄
            </div>

        @else

            <div class="history-list">

                @foreach ($recentChoices as $choice)

                    <article class="history-item">

                        <div>

                            <div class="person">
                                {{ $choice->person_name }}
                            </div>

                            <div class="time">
                                {{ $choice->chosen_at->diffForHumans() }}
                            </div>

                        </div>


                        <div>

                            <div class="meal">
                                {{ $choice->menuItem->name }}
                            </div>

                            <div class="category">
                                {{ $choice->menuItem->category->name }}
                            </div>

                        </div>

                    </article>

                @endforeach

            </div>

        @endif

    </section>


    <footer class="footer">
        MakanApa? — solving the most difficult decision of the workday.
    </footer>

</main>

</body>
</html>