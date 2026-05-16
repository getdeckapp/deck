<?php

use TorMorten\Deck\Support\DeckAssets;

it('ships a built stylesheet that includes utilities from deck views', function () {
    $css = file_get_contents(DeckAssets::packageDistPath('deck.css'));

    expect($css)
        ->toContain('.fixed')
        ->toContain('z-')
        ->toContain('.backdrop-blur');
});
