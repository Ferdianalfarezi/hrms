<?php

namespace Database\Factories;

use App\Modules\Iso\Models\IsoDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IsoDocument>
 */
class IsoDocumentFactory extends Factory
{
    protected $model = IsoDocument::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nama = fake()->words(3, true);

        return [
            'kategori' => 'procedures',
            'nama' => ucfirst($nama).'.pdf',
            'path' => 'iso/procedures/'.fake()->uuid().'.pdf',
            'extension' => 'pdf',
            'mime' => 'application/pdf',
            'size' => fake()->numberBetween(10_000, 2_000_000),
            'is_active' => true,
            'department_id' => null,
        ];
    }
}
