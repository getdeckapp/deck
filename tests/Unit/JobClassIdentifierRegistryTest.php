<?php

use Deck\Deck\Blocking\JobClassBlock;
use Deck\Deck\Blocking\JobClassIdentifierRegistry;
use Deck\Deck\Tests\Fixtures\SuccessfulTestJob;

it('blocks linked display names and fqcn together', function () {
    JobClassIdentifierRegistry::link('display-only-name', SuccessfulTestJob::class);

    JobClassBlock::block('display-only-name');

    expect(JobClassBlock::isBlocked(SuccessfulTestJob::class))->toBeTrue()
        ->and(JobClassBlock::isBlocked('display-only-name'))->toBeTrue();
});
