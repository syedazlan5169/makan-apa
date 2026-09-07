<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu moderation | MakanApa?</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="page page-wide">
    <header class="admin-header">
        <div>
            <a class="back-link" href="{{ route('makan.index') }}">MakanApa?</a>
            <h1 class="section-title">Pending menu suggestions</h1>
        </div>

        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button class="secondary-button compact-button" type="submit">Log out</button>
        </form>
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

    @forelse ($submissions as $submission)
        <article class="submission-item">
            <div class="submission-details">
                <h2 class="submission-name">{{ $submission->name }}</h2>
                <p class="submission-meta">
                    {{ $submission->category->name }}
                    · {{ $submission->submitter?->name ?? 'Anonymous visitor' }}
                    · {{ $submission->created_at->diffForHumans() }}
                </p>
            </div>

            <div class="moderation-actions">
                <form method="POST" action="{{ route('admin.menu-submissions.approve', $submission) }}">
                    @csrf
                    @method('PATCH')
                    <label class="sr-only" for="approve-notes-{{ $submission->id }}">Approval notes</label>
                    <input class="input review-notes" id="approve-notes-{{ $submission->id }}" name="review_notes" type="text" maxlength="1000" placeholder="Optional notes">
                    <button class="accept-button" type="submit">Approve</button>
                </form>

                <form method="POST" action="{{ route('admin.menu-submissions.reject', $submission) }}">
                    @csrf
                    @method('PATCH')
                    <label class="sr-only" for="reject-notes-{{ $submission->id }}">Rejection notes</label>
                    <input class="input review-notes" id="reject-notes-{{ $submission->id }}" name="review_notes" type="text" maxlength="1000" placeholder="Optional notes">
                    <button class="reject-button" type="submit">Reject</button>
                </form>
            </div>
        </article>
    @empty
        <div class="empty">There are no pending menu suggestions.</div>
    @endforelse

    @if ($submissions->hasPages())
        <div class="pagination">{{ $submissions->links() }}</div>
    @endif
</main>
</body>
</html>