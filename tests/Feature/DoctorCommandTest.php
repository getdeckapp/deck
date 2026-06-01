<?php

use function Pest\Laravel\artisan;

it('reports healthy when deck can write to the database', function () {
    artisan('deck:doctor')->assertSuccessful();
});
