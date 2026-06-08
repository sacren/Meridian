<?php

use App\Enums\Capability;
use App\Enums\Role;

test('owner can do everything', function () {
    expect(Role::Owner->can(Capability::ManageCampaign))->toBeTrue();
    expect(Role::Owner->can(Capability::ManageMembers))->toBeTrue();
    expect(Role::Owner->can(Capability::ManageContent))->toBeTrue();
    expect(Role::Owner->can(Capability::ViewContent))->toBeTrue();
});

test('staffer manages content but not members or the campaign', function () {
    expect(Role::Staffer->can(Capability::ManageMembers))->toBeFalse();
    expect(Role::Staffer->can(Capability::ManageCampaign))->toBeFalse();
    expect(Role::Staffer->can(Capability::ManageContent))->toBeTrue();
    expect(Role::Staffer->can(Capability::ViewContent))->toBeTrue();
});

test('viewer can only view content', function () {
    expect(Role::Viewer->can(Capability::ViewContent))->toBeTrue();
    expect(Role::Viewer->can(Capability::ManageContent))->toBeFalse();
    expect(Role::Viewer->can(Capability::ManageMembers))->toBeFalse();
    expect(Role::Viewer->can(Capability::ManageCampaign))->toBeFalse();
});

test('role is string-backed for the pivot and inertia props', function () {
    expect(Role::Owner->value)->toBe('owner');
    expect(Role::Staffer->value)->toBe('staffer');
    expect(Role::Viewer->value)->toBe('viewer');
});
