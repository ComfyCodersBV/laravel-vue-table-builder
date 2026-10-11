<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use TranquilTools\TableBuilder\Components\DateRangePreset;
use TranquilTools\TableBuilder\QueryBuilder;
use TranquilTools\TableBuilder\Tests\Fixtures\Product;

function scheduledTable(string $uri = '/'): QueryBuilder
{
    $request = Request::create($uri);

    app()->instance('request', $request);

    return (new QueryBuilder(Product::query()->orderBy('id'), $request))->column('name');
}

function scheduledNames(QueryBuilder $table): array
{
    return array_column($table->toArray()['data'], 'name');
}

function schedule(string $name, ?string $startsAt, ?string $endsAt = null): void
{
    Product::create([
        'name' => $name,
        'sku' => $name,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
    ]);
}

describe('display timezone', function () {
    beforeEach(function () {
        schedule('Just after midnight in Amsterdam', '2026-10-10 22:30:00');
        schedule('Late evening in Amsterdam', '2026-10-11 21:59:00');
        schedule('Next day in Amsterdam', '2026-10-11 22:00:00');
    });

    it('interprets the dates of a range in the display timezone', function () {
        $table = scheduledTable('/?filter[starts]=2026-10-11..2026-10-11')
            ->dateRangeFilter('starts', column: 'starts_at', timezone: 'Europe/Amsterdam');

        expect(scheduledNames($table))->toBe(['Just after midnight in Amsterdam', 'Late evening in Amsterdam']);
    });

    it('interprets a single date in the display timezone', function () {
        $table = scheduledTable('/?filter[starts_at]=2026-10-12')
            ->dateFilter('starts_at', timezone: 'Europe/Amsterdam');

        expect(scheduledNames($table))->toBe(['Next day in Amsterdam']);
    });

    it('takes the display timezone from the config', function () {
        config()->set('vue-table-builder.display_timezone', 'Europe/Amsterdam');

        $table = scheduledTable('/?filter[starts]=..2026-10-11')
            ->dateRangeFilter('starts', column: 'starts_at');

        expect(scheduledNames($table))->toBe(['Just after midnight in Amsterdam', 'Late evening in Amsterdam']);
    });

    it('keeps comparing stored dates without a timezone', function () {
        $table = scheduledTable('/?filter[starts]=2026-10-11..2026-10-11')
            ->dateRangeFilter('starts', column: 'starts_at');

        expect(scheduledNames($table))->toBe(['Late evening in Amsterdam', 'Next day in Amsterdam']);
    });
});

describe('overlap', function () {
    beforeEach(function () {
        schedule('Ends inside', '2026-10-01 10:00:00', '2026-10-03 10:00:00');
        schedule('Starts inside', '2026-10-05 20:00:00', '2026-10-08 10:00:00');
        schedule('Ends at the start', '2026-09-28 10:00:00', '2026-10-02 00:00:00');
        schedule('Starts after', '2026-10-06 00:00:00', '2026-10-07 00:00:00');
        schedule('Open ended', '2026-09-01 10:00:00');
        schedule('Spans the range', '2026-09-30 10:00:00', '2026-10-09 10:00:00');
    });

    it('matches records whose start and end overlap the range', function () {
        $table = scheduledTable('/?filter[period]=2026-10-02..2026-10-05')
            ->dateRangeFilter('period', column: 'starts_at', endColumn: 'ends_at');

        expect(scheduledNames($table))->toBe(['Ends inside', 'Starts inside', 'Open ended', 'Spans the range']);
    });

    it('matches records that have not ended before an open-ended range', function () {
        $table = scheduledTable('/?filter[period]=2026-10-06..')
            ->dateRangeFilter('period', column: 'starts_at', endColumn: 'ends_at');

        expect(scheduledNames($table))->toBe(['Starts inside', 'Starts after', 'Open ended', 'Spans the range']);
    });

    it('applies the overlap in the display timezone', function () {
        $table = scheduledTable('/?filter[period]=2026-10-06..2026-10-06')
            ->dateRangeFilter('period', column: 'starts_at', endColumn: 'ends_at', timezone: 'Europe/Amsterdam');

        expect(scheduledNames($table))->toBe(['Starts inside', 'Starts after', 'Open ended', 'Spans the range']);
    });
});

describe('presets', function () {
    beforeEach(function () {
        $this->travelTo(CarbonImmutable::parse('2026-10-14 12:00:00'));

        schedule('Last week', '2026-10-06 09:00:00', '2026-10-06 17:00:00');
        schedule('Running now', '2026-10-14 09:00:00', '2026-10-14 17:00:00');
        schedule('Friday', '2026-10-16 09:00:00', '2026-10-16 17:00:00');
        schedule('Next month', '2026-11-03 09:00:00', '2026-11-03 17:00:00');
    });

    it('filters on a built-in preset', function (string $preset, array $expected) {
        $table = scheduledTable("/?filter[period]={$preset}")
            ->dateRangeFilter('period', column: 'starts_at', endColumn: 'ends_at', presets: [
                'today',
                'upcoming',
                'past',
                'this_week',
                'last_week',
                'next_month',
            ]);

        expect(scheduledNames($table))->toBe($expected);
    })->with([
        'today' => ['today', ['Running now']],
        'upcoming' => ['upcoming', ['Running now', 'Friday', 'Next month']],
        'past' => ['past', ['Last week', 'Running now']],
        'this week' => ['this_week', ['Running now', 'Friday']],
        'last week' => ['last_week', ['Last week']],
        'next month' => ['next_month', ['Next month']],
    ]);

    it('filters a single column on a preset', function () {
        $table = scheduledTable('/?filter[starts]=upcoming')
            ->dateRangeFilter('starts', column: 'starts_at', presets: ['upcoming']);

        expect(scheduledNames($table))->toBe(['Friday', 'Next month']);
    });

    it('filters on a custom preset callback', function () {
        $table = scheduledTable('/?filter[period]=fortnight')
            ->dateRangeFilter('period', column: 'starts_at', endColumn: 'ends_at', presets: [
                'fortnight' => DateRangePreset::make('fortnight', 'Next two weeks', fn (CarbonImmutable $now) => [$now->startOfDay(), $now->startOfDay()->addWeeks(2)]),
                'last_ten_days' => fn (CarbonImmutable $now) => [$now->subDays(10), $now],
            ]);

        expect(scheduledNames($table))->toBe(['Running now', 'Friday']);
    });

    it('applies the default preset without a query value', function () {
        $table = scheduledTable()
            ->dateRangeFilter('period', column: 'starts_at', endColumn: 'ends_at', presets: ['upcoming', 'past'], defaultPreset: 'upcoming');

        expect(scheduledNames($table))->toBe(['Running now', 'Friday', 'Next month']);
    });

    it('applies no constraint for an empty range that clears the default preset', function () {
        $table = scheduledTable('/?filter[period]=..')
            ->dateRangeFilter('period', column: 'starts_at', endColumn: 'ends_at', presets: ['upcoming'], defaultPreset: 'upcoming');

        expect(scheduledNames($table))->toBe(['Last week', 'Running now', 'Friday', 'Next month']);
    });

    it('still accepts a custom range next to presets', function () {
        $table = scheduledTable('/?filter[period]=2026-10-16..2026-10-16')
            ->dateRangeFilter('period', column: 'starts_at', endColumn: 'ends_at', presets: ['upcoming']);

        expect(scheduledNames($table))->toBe(['Friday']);
    });

    it('sends the presets with their labels to the component', function () {
        $table = scheduledTable()
            ->dateRangeFilter('period', presets: [
                'upcoming',
                'past' => 'Before today',
            ])
            ->dateRangeFilter('other');

        $filters = $table->filters()->map->toArray()->values()->all();

        expect($filters[0]['presets'])->toBe([
            ['key' => 'upcoming', 'label' => 'Upcoming'],
            ['key' => 'past', 'label' => 'Before today'],
        ])->and($filters[1])->not->toHaveKey('presets');
    });

    it('refuses a default preset that is not offered', function () {
        scheduledTable()->dateRangeFilter('period', presets: ['upcoming'], defaultPreset: 'past');
    })->throws(InvalidArgumentException::class);

    it('refuses an unknown preset without a callback', function () {
        scheduledTable()->dateRangeFilter('period', presets: ['someday']);
    })->throws(InvalidArgumentException::class);
});
