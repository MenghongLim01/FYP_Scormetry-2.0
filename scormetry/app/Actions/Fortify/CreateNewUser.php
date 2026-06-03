<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\AppSetting;
use App\Models\SubjectInvitation;
use App\Models\SubjectMember;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /** @param  array<string, string>  $input */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'role' => ['required', 'in:student,teacher'],
        ])->validate();

        $status = $this->resolveStatus($input['email']);

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'role' => $input['role'],
            'status' => $status,
        ]);

        $this->applyPendingInvitations($user);

        return $user;
    }

    private function resolveStatus(string $email): string
    {
        $domain = AppSetting::get('school_email_domain');

        if ($domain && str_ends_with(strtolower($email), '@'.strtolower(trim($domain, '@')))) {
            return 'approved';
        }

        return $domain ? 'pending' : 'approved';
    }

    private function applyPendingInvitations(User $user): void
    {
        $pendingInvitations = SubjectInvitation::where('email', $user->email)
            ->whereNull('accepted_at')
            ->get();

        foreach ($pendingInvitations as $invitation) {
            SubjectMember::updateOrCreate(
                ['subject_id' => $invitation->subject_id, 'user_id' => $user->id],
                [
                    'role' => $invitation->committee_role,
                    'status' => 'approved',
                    'role_label' => $invitation->role_label,
                ],
            );
            $invitation->update(['accepted_at' => now()]);
        }
    }
}
