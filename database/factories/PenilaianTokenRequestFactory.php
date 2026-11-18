<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Hrms\Models\PenilaianTokenRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PenilaianTokenRequest>
 */
class PenilaianTokenRequestFactory extends Factory
{
    protected $model = PenilaianTokenRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'jumlah' => fake()->numberBetween(5, 50),
            'alasan' => fake()->sentence(),
            'status' => PenilaianTokenRequest::STATUS_PENDING,
        ];
    }
}
