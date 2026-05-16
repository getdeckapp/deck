<?php

use Illuminate\Support\Facades\Route;
use TorMorten\Deck\Http\Controllers\HorizonPreferenceController;
use TorMorten\Deck\Http\Middleware\AuthorizeDeck;
use TorMorten\Deck\Livewire\Dashboard;
use TorMorten\Deck\Livewire\JobClassIndex;
use TorMorten\Deck\Livewire\JobClassShow;
use TorMorten\Deck\Livewire\JobExecutionIndex;
use TorMorten\Deck\Livewire\WorkersIndex;
use TorMorten\Deck\Support\DeckAssets;

$prefix = trim(config('deck.route_prefix', 'deck'), '/');
$middleware = array_merge(config('deck.middleware', ['web']), [AuthorizeDeck::class]);

Route::middleware(config('deck.middleware', ['web']))
    ->get("/{$prefix}/assets/{file}", function (string $file) {
        $file = basename($file);

        abort_unless(preg_match('/^deck\.css$/', $file), 404);

        $path = DeckAssets::packageDistPath($file);

        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => 'text/css',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    })
    ->name('deck.assets');

Route::middleware($middleware)->prefix($prefix)->name('deck.')->group(function () {
    Route::get('/', Dashboard::class)->name('index');
    Route::get('/classes', JobClassIndex::class)->name('classes.index');
    Route::get('/activity', JobExecutionIndex::class)->name('activity.index');
    Route::get('/workers', WorkersIndex::class)->name('workers.index');
    Route::get('/classes/{jobClass}', JobClassShow::class)
        ->where('jobClass', '.*')
        ->name('classes.show');

    Route::post('/horizon-preference', [HorizonPreferenceController::class, 'store'])
        ->name('horizon-preference')
        ->middleware(config('deck.middleware', ['web']));
});
