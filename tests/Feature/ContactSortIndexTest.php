<?php

use Illuminate\Support\Facades\Schema;

/**
 * The contact list confines every query to one campaign and serves its ORDER BY
 * from a composite index. These assert the indexes backing the name and
 * created_at sorts exist; the (campaign_id, email) unique already serves email.
 */
function contactIndexColumns(): array
{
    return collect(Schema::getIndexes('contacts'))
        ->pluck('columns')
        ->all();
}

test('a composite index backs the campaign-scoped name sort', function () {
    expect(contactIndexColumns())->toContain(['campaign_id', 'name']);
});

test('a composite index backs the campaign-scoped created_at sort', function () {
    expect(contactIndexColumns())->toContain(['campaign_id', 'created_at']);
});
