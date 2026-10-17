<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Create / edit a guardian. The update path writes through a query builder, which
 * ignores $fillable, so only the fields listed in userData() may ever reach it.
 */
class GuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->can($this->isUpdate() ? 'guardian-edit' : 'guardian-create');
    }

    public function rules(): array
    {
        $uniqueEmail = 'unique:users,email' . ($this->isUpdate() ? ',' . (int) $this->input('edit_id') : '');

        return [
            'edit_id' => $this->isUpdate() ? 'required|integer' : 'nullable',
            'first_name' => 'required',
            'email' => 'required|email|max:255|regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/|' . $uniqueEmail,
            'last_name' => 'required',
            'gender' => 'required',
            'mobile' => 'required|digits_between:6,15',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp',
            'reset_password' => 'nullable',
        ];
    }

    /** Attributes to write to the users table. */
    public function userData(): array
    {
        return $this->safe()->only(['first_name', 'last_name', 'email', 'gender', 'mobile']);
    }

    private function isUpdate(): bool
    {
        return $this->isMethod('PUT') || $this->isMethod('PATCH');
    }
}
