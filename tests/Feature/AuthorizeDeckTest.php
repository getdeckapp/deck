<?php

it('allows access when the auth callback returns true', function () {
    config()->set('deck.auth', fn () => true);

    $this->get(route('deck.index'))->assertOk();
});

it('denies access when the auth callback returns false', function () {
    config()->set('deck.auth', fn () => false);

    $this->get(route('deck.index'))->assertForbidden();
});

it('denies access to job class routes when unauthorized', function () {
    config()->set('deck.auth', fn () => false);

    $this->get(route('deck.classes.index'))->assertForbidden();
    $this->get(route('deck.activity.index'))->assertForbidden();
    $this->get(route('deck.workers.index'))->assertForbidden();

    $execution = createDeckExecution();
    $this->get(route('deck.activity.show', $execution))->assertForbidden();
});
