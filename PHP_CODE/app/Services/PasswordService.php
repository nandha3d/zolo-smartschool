<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordService
{
    /**
     * Every account type used to be created with a password derived from data the
     * school already publishes or holds: the school's support phone, a staff mobile
     * number, or a student's date of birth. Those are guessable, so initial passwords
     * are now generated here instead and the holder is forced to replace one on first
     * sign in (see the must_change_password flag and ForcePasswordChange middleware).
     */
    public static function generate(int $length = 14): string
    {
        $upper   = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower   = 'abcdefghijkmnopqrstuvwxyz';
        $digits  = '23456789';
        $symbols = '!@#$%^&*-_=+';
        $all     = $upper . $lower . $digits . $symbols;

        // Guarantee one of each class so the result always satisfies rules() below.
        $characters = [
            $upper[random_int(0, strlen($upper) - 1)],
            $lower[random_int(0, strlen($lower) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
            $symbols[random_int(0, strlen($symbols) - 1)],
        ];

        for ($i = count($characters); $i < $length; $i++) {
            $characters[] = $all[random_int(0, strlen($all) - 1)];
        }

        // Fisher-Yates with random_int, so the guaranteed characters are not pinned
        // to the first four positions.
        for ($i = count($characters) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$characters[$i], $characters[$j]] = [$characters[$j], $characters[$i]];
        }

        return implode('', $characters);
    }

    /**
     * A generated password plus its hash, for the common "create an account and tell
     * the administrator the secret once" case.
     *
     * @return array{plain: string, hash: string}
     */
    public static function generatePair(int $length = 14): array
    {
        $plain = self::generate($length);

        return ['plain' => $plain, 'hash' => Hash::make($plain)];
    }

    /**
     * The single password policy for the whole application. Previously the rules
     * ranged from 'required' with no minimum, through min:6, to min:8, depending on
     * which controller you happened to be in.
     */
    public static function rules(bool $required = true): array
    {
        // Confirmation is not included here: the application's forms post the second
        // field as confirm_password, so callers pair this with same:new_password.
        return [
            $required ? 'required' : 'nullable',
            'string',
            Password::min(12)->mixedCase()->numbers()->symbols(),
        ];
    }
}
