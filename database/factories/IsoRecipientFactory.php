<?php

namespace Database\Factories;

use App\Modules\Iso\Models\IsoRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IsoRecipient>
 */
class IsoRecipientFactory extends Factory
{
    protected $model = IsoRecipient::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
