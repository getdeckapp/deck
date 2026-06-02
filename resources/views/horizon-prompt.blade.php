<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-zinc-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Horizon or Deck?</title>
    @include('deck::partials.favicon')
    @include('deck::partials.assets')
</head>
<body class="flex min-h-full flex-col justify-center py-12 sm:px-6 lg:px-8">
    <div class="flex flex-col items-center sm:mx-auto sm:w-full sm:max-w-md">
        <x-deck::logo size="lg" class="justify-center" />
        <p class="mt-3 text-center text-sm text-zinc-600">Choose where to continue</p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="rounded-xl border border-zinc-200/80 bg-white px-6 py-8 shadow-lg ring-zinc-900/5">
            <p class="text-sm text-zinc-600">
                <span class="font-medium text-zinc-900">Deck</span> shows job history, search, and cancellation.
                <span class="font-medium text-zinc-900">Horizon</span> shows workers, throughput, and supervisors.
            </p>

            <div class="mt-6 space-y-3">
                <form method="post" action="{{ route('deck.horizon-preference') }}">
                    @csrf
                    <input type="hidden" name="choice" value="deck">
                    @if ($rememberChoice)<input type="hidden" name="remember" value="1">@endif
                    <button
                        type="submit"
                        class="flex w-full justify-center rounded-md bg-amber-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-amber-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600"
                    >
                        Open Deck
                    </button>
                </form>
                <form method="post" action="{{ route('deck.horizon-preference') }}">
                    @csrf
                    <input type="hidden" name="choice" value="horizon">
                    @if ($rememberChoice)<input type="hidden" name="remember" value="1">@endif
                    <button
                        type="submit"
                        class="flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-zinc-900 shadow-xs ring-1 ring-inset ring-zinc-300 hover:bg-zinc-50"
                    >
                        Continue to Horizon
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
