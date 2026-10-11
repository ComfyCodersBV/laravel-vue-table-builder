# Empty State

Without configuration an empty table shows "No results found.". Give it texts of your own in the
Table class:

```php
$table->emptyState(
    title: 'No rooms yet',
    description: 'Add the first room of this venue.',
    filteredTitle: 'No rooms match',
    filteredDescription: 'Try another search or filter.',
);
```

The filtered texts are shown while a search or a filter has a value, which includes a filter with a
default value. Without them the table falls back to `title` and `description`.

## From Vue

The `emptyState` prop takes the same keys and overrides the server texts one by one:

```vue
<TableBuilder :table="table" :empty-state="{title: $t('rooms.empty.title')}"/>
```

## The `empty` Slot

Render anything you like in the empty row. The slot receives:

| Scope         | Description                                                      |
|---------------|------------------------------------------------------------------|
| `filtered`    | Whether a search or filter is active                             |
| `title`       | The title that would be shown                                    |
| `description` | The description that would be shown, or `null`                   |
| `reset`       | Removes the search and every filter from the query and reloads   |

```vue
<TableBuilder :table="table">
    <template #empty="{filtered, reset}">
        <div v-if="filtered" class="py-6">
            <p>No rooms match.</p>
            <Button variant="link" @click="reset">Clear search and filters</Button>
        </div>
        <div v-else class="py-6">
            <p>No rooms yet.</p>
            <Button as-child><Link :href="create()">Add room</Link></Button>
        </div>
    </template>
</TableBuilder>
```

`reset` removes the query parameters; a filter with a default value is applied again afterwards.
