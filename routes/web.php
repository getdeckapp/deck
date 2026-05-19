<?php

use Deck\Deck\Http\Controllers\HorizonPreferenceController;
use Deck\Deck\Http\Middleware\AuthorizeDeck;
use Deck\Deck\Livewire\Dashboard;
use Deck\Deck\Livewire\JobClassIndex;
use Deck\Deck\Livewire\JobClassShow;
use Deck\Deck\Livewire\JobExecutionIndex;
use Deck\Deck\Livewire\JobExecutionShow;
use Deck\Deck\Livewire\WorkersIndex;
use Deck\Deck\Support\DeckAssets;
use Illuminate\Support\Facades\Route;

$prefix = trim(config('deck.route_prefix', 'deck'), '/');
$middleware = array_merge(config('deck.middleware', ['web']), [AuthorizeDeck::class]);

Route::middleware(config('deck.middleware', ['web']))
    ->get("/{$prefix}/assets/{file}", function (string $file) {
        $file = basename($file);

        abort_unless(preg_match('/^deck\.css$/', $file), 404);

        $path = DeckAssets::resolveAssetPath($file);

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
    Route::get('/activity/{uuid}/{attempt}', JobExecutionShow::class)
        ->whereUuid('uuid')
        ->whereNumber('attempt')
        ->name('activity.show');
    Route::get('/workers', WorkersIndex::class)->name('workers.index');
    Route::get('/classes/{jobClass}', JobClassShow::class)
        ->where('jobClass', '.*')
        ->name('classes.show');

    Route::post('/horizon-preference', [HorizonPreferenceController::class, 'store'])
        ->name('horizon-preference')
        ->middleware(config('deck.middleware', ['web']));
});
