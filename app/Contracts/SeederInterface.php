<?php

namespace App\Contracts;

use App\Models\Tournament;
use Illuminate\Support\Collection;

/**
 * Contract for participant seeding strategies.
 *
 * Different seeding algorithms (rating-based, random, manual)
 * implement this interface to determine tournament seeding.
 */
interface SeederInterface
{
    /**
     * Seed participants and return them in seed order.
     *
     * @param Tournament $tournament
     * @return Collection<int, SeedAssignment> Collection of seed assignments
     */
    public function seed(Tournament $tournament): Collection;

    /**
     * Get the name of this seeding strategy.
     *
     * @return string
     */
    public function getName(): string;
}
