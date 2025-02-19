<?php

namespace Database\Factories;

use App\Models\CleaningStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CleaningStatus>
 */
class CleaningStatusFactory extends Factory
{
    protected $model = CleaningStatus::class;

    public function definition(): array
    {
        return [
            'cleaning_type' => $this->faker->randomElement([CleaningStatus::STATUS_DAILY, CleaningStatus::STATUS_DEEP])
        ];
    }

    // Require cabin_id to be passed when calling the factory
    public function withCabin(int $cabin_id): self
    {
        return $this->state(function (array $attributes) use ($cabin_id) {
            return [
                'cabin_id' => $cabin_id,
            ];
        });
    }
}

