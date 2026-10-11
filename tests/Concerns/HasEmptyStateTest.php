<?php

declare(strict_types=1);

use TranquilTools\TableBuilder\TableBuilder;

it('sends the configured empty state', function () {
    $table = TableBuilder::for([]);

    $table->emptyState(
        title: 'No rooms yet',
        description: 'Add the first room.',
        filteredTitle: 'No rooms match',
    );

    expect($table->toArray()['emptyState'])->toBe([
        'title' => 'No rooms yet',
        'description' => 'Add the first room.',
        'filteredTitle' => 'No rooms match',
        'filteredDescription' => null,
    ]);
});

it('sends an empty state without texts by default', function () {
    expect(TableBuilder::for([])->toArray()['emptyState'])->toBe([
        'title' => null,
        'description' => null,
        'filteredTitle' => null,
        'filteredDescription' => null,
    ]);
});
