<?php

use TorMorten\Deck\Support\JobClassBlock;
use TorMorten\Deck\Support\JobClassIdentifierRegistry;
use TorMorten\Deck\Tests\Fixtures\SuccessfulTestJob;

it('blocks linked display names and fqcn together', function () {
    JobClassIdentifierRegistry::link('display-only-name', SuccessfulTestJob::class);

    JobClassBlock::block('display-only-name');

    expect(JobClassBlock::isBlocked(SuccessfulTestJob::class))->toBeTrue()
        ->and(JobClassBlock::isBlocked('display-only-name'))->toBeTrue();
});
