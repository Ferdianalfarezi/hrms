<?php

namespace Database\Factories;

use App\Modules\Iso\Models\IsoFolder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IsoFolder>
 */
class IsoFolderFactory extends Factory
{
    protected $model = IsoFolder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kategori' => 'procedures',
            'parent_id' => null,
            'nama' => ucfirst(fake()->words(2, true)),
            'department_id' => null,
        ];
    }
}
