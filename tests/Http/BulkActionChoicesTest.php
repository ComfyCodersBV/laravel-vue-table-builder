<?php

declare(strict_types=1);

use Illuminate\Support\Facades\URL;
use TranquilTools\TableBuilder\AbstractTable;
use TranquilTools\TableBuilder\TableBuilder;
use TranquilTools\TableBuilder\Tests\Fixtures\Category;
use TranquilTools\TableBuilder\Tests\Fixtures\Product;

class ChoiceProductsTable extends AbstractTable
{
    public static array $calls = [];

    public function for()
    {
        return Product::query();
    }

    public function configure(TableBuilder $table): void
    {
        $table->column('name')
            ->bulkAction(
                label: 'Move to',
                each: function (Product $product, ?string $choice) {
                    static::$calls[] = ['each', $product->name, $choice];

                    $product->update([
                        'category_id' => (int) $choice,
                    ]);
                },
                before: fn (array $ids, ?string $choice) => static::$calls[] = ['before', $ids, $choice],
                after: fn (array $ids, ?string $choice) => static::$calls[] = ['after', $ids, $choice],
                choices: Category::query()
                    ->pluck('name', 'id')
                    ->all(),
                choicesLabel: 'Category',
            )
            ->bulkAction(
                label: 'Deactivate',
                each: function (...$arguments) {
                    static::$calls[] = ['each', count($arguments)];
                },
                before: function (...$arguments) {
                    static::$calls[] = ['before', $arguments];
                },
            );
    }
}

function choiceActionUrl(int $key): string
{
    return URL::signedRoute('table.bulk-action', [
        'table' => base64_encode(ChoiceProductsTable::class),
        'action' => base64_encode((string) $key),
        'slug' => 'move-to',
    ]);
}

beforeEach(function () {
    ChoiceProductsTable::$calls = [];

    Category::create(['name' => 'Inbox']);
    Category::create(['name' => 'Archive']);

    Product::create(['name' => 'Chair', 'sku' => 'A']);
    Product::create(['name' => 'Table', 'sku' => 'B']);
});

it('sends the choices of a bulk action as an ordered list with their label', function () {
    $payload = json_decode(json_encode((new ChoiceProductsTable)->make()->toArray()['bulkActions'][0]), true);

    expect($payload['choices'])->toBe([
        [
            'value' => '1',
            'label' => 'Inbox',
        ],
        [
            'value' => '2',
            'label' => 'Archive',
        ],
    ])->and($payload['choicesLabel'])->toBe('Category');
});

it('sends a bulk action without choices without choice keys and without its callbacks', function () {
    $payload = json_decode(json_encode((new ChoiceProductsTable)->make()->toArray()['bulkActions'][1]), true);

    expect(array_keys($payload))->toEqualCanonicalizing([
        'requirePassword',
        'url',
        'key',
        'label',
        'tableClass',
        'confirm',
        'confirmText',
        'confirmButton',
        'cancelButton',
        'tableParameters',
    ]);
});

it('passes the posted choice to the before, each and after callbacks', function () {
    $this->postJson(choiceActionUrl(0), [
        'ids' => [1],
        'choice' => '2',
    ])->assertOk();

    expect(ChoiceProductsTable::$calls)->toBe([
        ['before', [1], '2'],
        ['each', 'Chair', '2'],
        ['after', [1], '2'],
    ])->and(Product::query()->find(1)->category_id)->toBe(2);
});

it('accepts a numeric choice posted as json', function () {
    $this->postJson(choiceActionUrl(0), [
        'ids' => [1],
        'choice' => 2,
    ])->assertOk();

    expect(Product::query()->find(1)->category_id)->toBe(2);
});

it('rejects a choice the table does not offer', function () {
    $this->postJson(choiceActionUrl(0), [
        'ids' => [1],
        'choice' => '99',
    ])->assertUnprocessable()->assertJsonValidationErrors('choice');

    expect(ChoiceProductsTable::$calls)->toBe([])
        ->and(Product::query()->find(1)->category_id)->toBeNull();
});

it('rejects a missing choice', function () {
    $this->postJson(choiceActionUrl(0), [
        'ids' => [1],
    ])->assertUnprocessable()->assertJsonValidationErrors('choice');

    expect(ChoiceProductsTable::$calls)->toBe([]);
});

it('rejects a choice posted as an array', function () {
    $this->postJson(choiceActionUrl(0), [
        'ids' => [1],
        'choice' => ['1'],
    ])->assertUnprocessable()->assertJsonValidationErrors('choice');
});

it('re-resolves the choices on the server for every request', function () {
    Category::create(['name' => 'Later']);

    $this->postJson(choiceActionUrl(0), [
        'ids' => [1],
        'choice' => '3',
    ])->assertOk();

    expect(Product::query()->find(1)->category_id)->toBe(3);
});

it('calls the callbacks of a bulk action without choices exactly as before', function () {
    $this->postJson(choiceActionUrl(1), [
        'ids' => [1],
        'choice' => 'ignored',
    ])->assertOk();

    expect(ChoiceProductsTable::$calls)->toBe([
        ['before', [[1]]],
        ['each', 2],
    ]);
});
