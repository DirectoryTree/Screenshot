<?php

use DirectoryTree\Screenshot\Tests\TestCase;

uses(TestCase::class)->in('Feature');

afterEach(function () {
    Mockery::close();
});

function imageFixture(): string
{
    return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1sAAAAASUVORK5CYII=';
}
