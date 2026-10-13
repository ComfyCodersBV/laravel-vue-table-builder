<?php

declare(strict_types=1);

use TranquilTools\TableBuilder\TableBuilder;

it('sends the poll interval in seconds when it is set', function () {
    $table = TableBuilder::for([])->poll(30);

    expect($table->toArray()['poll'])->toBe(30);
});

it('sends no poll key for a table that does not poll', function () {
    expect(TableBuilder::for([])->toArray())->not->toHaveKey('poll');
});

it('refuses a poll interval below one second', function () {
    TableBuilder::for([])->poll(0);
})->throws(InvalidArgumentException::class);
