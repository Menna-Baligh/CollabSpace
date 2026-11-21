<?php

return [
    'required' => 'The :attribute field is required.',
    'email' => 'The :attribute field must be a valid email address.',
    'unique' => 'The :attribute has already been taken.',
    'confirmed' => 'The :attribute confirmation does not match.',
    'string' => 'The :attribute field must be a string.',
    'boolean' => 'The :attribute field must be true or false.',
    'max' => [
        'string' => 'The :attribute field must not be greater than :max characters.',
    ],
    'min' => [
        'string' => 'The :attribute field must be at least :min characters.',
    ],
    'attributes' => [
        'name' => 'name',
        'email' => 'email',
        'password' => 'password',
        'password_confirmation' => 'password confirmation',
        'token' => 'token',
        'code' => 'code',
        'remember' => 'remember me',
    ],
];

