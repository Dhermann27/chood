<?php

namespace Database\Factories;

use App\Models\Cabin;
use App\Models\Dog;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dog>
 */
class DogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pet_id' => $this->faker->unique()->randomNumber(7),
            'firstname' => $this->faker->url(),
            'lastname' => $this->faker->url(),
            'gender' => $this->faker->randomElement(['M', 'F']),
            'weight' => $this->faker->numberBetween(5, 120),
            'photoUri' => $this->faker->imageUrl(640, 480, 'animals', true, 'dogs'),
            'cabin_id' => Cabin::where('id', '>=', 2000)->where('id', '<', 3000)->inRandomOrder()->first()->id,
            'checkout' => $this->faker->optional()->dateTimeBetween('+1 day', '+2 weeks'),
        ];
    }

    public function configure(): self
    {
        return $this->afterCreating(function (Dog $dog) {
            // Attach random services to the dog
            $serviceIds = [$this->faker->randomElement(['1000', '2000', '2001', '2002'])];
            $serviceIds = array_merge($serviceIds,
                Service::where('id', '>=', 3000)->inRandomOrder()->take(rand(0, 2))->pluck('id')->toArray());
            $dog->services()->attach($serviceIds);
        });
    }
}
