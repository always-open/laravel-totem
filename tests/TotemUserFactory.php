<?php

namespace Studio\Totem\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TotemUserFactory extends Factory
{
    protected $model = TestUser::class;

    public function definition(): array
    {
        static $password;

        return [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'password' => $password ?: $password = bcrypt('secret'),
            'remember_token' => Str::random(10),
        ];
    }
}
