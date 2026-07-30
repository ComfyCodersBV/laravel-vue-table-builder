<?php

declare(strict_types=1);

use TranquilTools\TableBuilder\Components\Filter;

function filter(array $overrides = []): Filter
{
    return new Filter(...array_merge([
        'key' => 'is_active',
        'label' => 'Is Active',
        'options' => ['1' => 'Active', '0' => 'Inactive'],
        'value' => null,
        'noFilterOption' => true,
        'noFilterOptionLabel' => '-',
        'type' => 'select',
    ], $overrides));
}

it('knows whether it has a value', function () {
    expect(filter()->hasValue())->toBeFalse()
        ->and(filter(['value' => '0'])->hasValue())->toBeTrue();
});

it('treats an empty string as a value', function () {
    expect(filter(['value' => ''])->hasValue())->toBeTrue();
});

it('prepends the no filter option', function () {
    expect(filter()->options())->toBe([
        '' => '-',
        '1' => 'Active',
        '0' => 'Inactive',
    ]);
});

it('leaves the options alone without a no filter option', function () {
    expect(filter(['noFilterOption' => false])->options())->toBe([
        '1' => 'Active',
        '0' => 'Inactive',
    ]);
});

it('exposes the prepended options in the payload', function () {
    expect(filter()->toArray()['options'])->toHaveKey('');
});

it('clones every property', function () {
    $original = filter(['value' => '1']);
    $clone = $original->clone();

    expect($clone)->not->toBe($original)
        ->and($clone->value)->toBe('1')
        ->and($clone->options)->toBe($original->options);
});
