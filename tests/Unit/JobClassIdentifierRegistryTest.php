<?php

use Deck\Deck\Support\JobClassBlock;
use Deck\Deck\Support\JobClassIdentifierRegistry;
use Deck\Deck\Tests\Fixtures\SuccessfulTestJob;

it('blocks linked display names and fqcn together', function () {
    JobClassIdentifierRegistry::link('display-only-name', SuccessfulTestJob::class);

    JobClassBlock::block('display-only-name');

    expect(JobClassBlock::isBlocked(SuccessfulTestJob::class))->toBeTrue()
        ->and(JobClassBlock::isBlocked('display-only-name'))->toBeTrue();
});
