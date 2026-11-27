<?php

namespace App\Actions\Fortify;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  mixed  $user
     * @param  array  $input
     * @return void
     */
    public function update($user, array $input)
    {
        $canUpdatePassport = $user->isCliente() && array_key_exists('passport', $input);
        if ($canUpdatePassport && is_string($input['passport'])) {
            $input['passport'] = trim($input['passport']);
        }
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:40960'],
        ];
        if ($canUpdatePassport) {
            $rules['passport'] = [filled($user->passport) ? 'required' : 'nullable', 'string', 'max:20', Rule::unique('users', 'passport')->ignore($user->id)];
        }
        Validator::make($input, $rules)->validateWithBag('updateProfileInformation');

        $profile = ['name' => $input['name'], 'email' => $input['email']];
        if ($canUpdatePassport) {
            $profile['passport'] = $input['passport'];
        }

        if (isset($input['photo'])) {
            $user->updateProfilePhoto($input['photo']);
        }

        if ($input['email'] !== $user->email &&
            $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser($user, $profile);
        } else {
            $user->forceFill($profile)->save();
        }
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  mixed  $user
     * @param  array  $input
     * @return void
     */
    protected function updateVerifiedUser($user, array $input)
    {
        $user->forceFill($input + ['email_verified_at' => null])->save();

        $user->sendEmailVerificationNotification();
    }
}
