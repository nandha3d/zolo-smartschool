<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * The signed-in user's own profile. Only these fields reach the User model, so the
 * form cannot be used to set password, status, school_id, email_verified_at or the
 * two-factor columns.
 */
class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required',
            'last_name' => 'required',
            'mobile' => 'nullable|numeric|digits_between:1,16',
            'gender' => 'required',
            'dob' => 'required',
            'email' => 'required|email|unique:users,email,' . Auth::user()->id,
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp',
            'current_address' => 'required',
            'permanent_address' => 'required',
            'two_factor_verification' => 'nullable|in:0,1',
        ];
    }

    /** Attributes to write to the users table. */
    public function userData(): array
    {
        return $this->safe()->only(['first_name', 'last_name', 'mobile', 'gender', 'dob', 'email', 'current_address', 'permanent_address']);
    }
}
