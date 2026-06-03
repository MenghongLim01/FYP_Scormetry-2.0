<?php

use App\Actions\Fortify\CreateNewUser;
use App\Models\AppSetting;
use App\Models\User;

it('allows admin to view user management page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/users')
        ->assertOk();
});

it('forbids non-admin from accessing user management', function () {
    $teacher = User::factory()->teacher()->create();

    $this->actingAs($teacher)
        ->get('/admin/users')
        ->assertForbidden();
});

it('allows admin to approve a pending user', function () {
    $admin = User::factory()->admin()->create();
    $pending = User::factory()->student()->pending()->create();

    $this->actingAs($admin)
        ->post("/admin/users/{$pending->id}/approve")
        ->assertRedirect();

    expect($pending->fresh()->status)->toBe('approved');
});

it('allows admin to delete a user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->student()->create();

    $this->actingAs($admin)
        ->delete("/admin/users/{$user->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

it('allows admin to view settings page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/settings')
        ->assertOk();
});

it('allows admin to update school email domain', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->patch('/admin/settings', ['school_email_domain' => 'school.edu'])
        ->assertRedirect();

    expect(AppSetting::get('school_email_domain'))->toBe('school.edu');
});

it('rejects invalid email domain format', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->patch('/admin/settings', ['school_email_domain' => 'not a domain!'])
        ->assertSessionHasErrors('school_email_domain');
});

it('auto-approves user with matching school email domain', function () {
    AppSetting::set('school_email_domain', 'school.edu');

    $action = new CreateNewUser;
    $user = $action->create([
        'name' => 'Test Student',
        'email' => 'test@school.edu',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'student',
    ]);

    expect($user->status)->toBe('approved');
});

it('sets pending status for user with non-school email when domain is configured', function () {
    AppSetting::set('school_email_domain', 'school.edu');

    $action = new CreateNewUser;
    $user = $action->create([
        'name' => 'External User',
        'email' => 'external@gmail.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'student',
    ]);

    expect($user->status)->toBe('pending');
});

it('auto-approves all users when no school email domain is configured', function () {
    AppSetting::where('key', 'school_email_domain')->delete();

    $action = new CreateNewUser;
    $user = $action->create([
        'name' => 'Anyone',
        'email' => 'anyone@gmail.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'teacher',
    ]);

    expect($user->status)->toBe('approved');
});

it('redirects pending user to pending-approval page', function () {
    $pending = User::factory()->student()->pending()->create();

    $this->actingAs($pending)
        ->get('/dashboard')
        ->assertRedirect(route('pending-approval'));
});

it('allows approved user to access dashboard', function () {
    $approved = User::factory()->student()->approved()->create();

    $this->actingAs($approved)
        ->get('/dashboard')
        ->assertOk();
});

it('allows admin to change a user role to teacher', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->student()->create();

    $this->actingAs($admin)
        ->patch("/admin/users/{$student->id}/role", ['role' => 'teacher'])
        ->assertRedirect();

    expect($student->fresh()->role)->toBe('teacher');
});

it('allows admin to change a user role to student', function () {
    $admin = User::factory()->admin()->create();
    $teacher = User::factory()->teacher()->create();

    $this->actingAs($admin)
        ->patch("/admin/users/{$teacher->id}/role", ['role' => 'student'])
        ->assertRedirect();

    expect($teacher->fresh()->role)->toBe('student');
});

it('rejects invalid role values when updating', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->student()->create();

    $this->actingAs($admin)
        ->patch("/admin/users/{$user->id}/role", ['role' => 'admin'])
        ->assertSessionHasErrors('role');
});

it('forbids non-admin from changing a user role', function () {
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();

    $this->actingAs($teacher)
        ->patch("/admin/users/{$student->id}/role", ['role' => 'teacher'])
        ->assertForbidden();
});
