<?php

use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Enums\TournamentType;
use App\Models\GeographicUnit;
use App\Models\Tournament;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->geographicUnit = GeographicUnit::create([
        'name' => 'Test County',
        'code' => 'TC',
        'level' => 3,
        'country_code' => 'KE',
        'is_active' => true,
    ]);

    $this->organizer = User::factory()->create([
        'is_organizer' => true,
    ]);
});

it('can update a tournament in registration status', function () {
    $tournament = Tournament::create([
        'name' => 'Original Name',
        'type' => TournamentType::REGULAR,
        'format' => TournamentFormat::KNOCKOUT,
        'geographic_scope_id' => $this->geographicUnit->id,
        'registration_closes_at' => Carbon::now()->addDays(7),
        'starts_at' => Carbon::now()->addDays(8),
        'status' => TournamentStatus::REGISTRATION,
        'created_by' => $this->organizer->id,
        'race_to' => 3,
    ]);

    $response = $this->actingAs($this->organizer, 'api')
        ->putJson("/api/tournaments/{$tournament->id}", [
            'name' => 'Updated Name',
            'starts_at' => Carbon::now()->addDays(10)->toISOString(),
        ]);

    $response->dump(); // This will show the response for debugging

    $response->assertOk()
        ->assertJsonPath('tournament.name', 'Updated Name');

    // Verify in database
    $tournament->refresh();
    expect($tournament->name)->toBe('Updated Name');
});

it('can update a tournament in draft status', function () {
    $tournament = Tournament::create([
        'name' => 'Draft Tournament',
        'type' => TournamentType::REGULAR,
        'format' => TournamentFormat::KNOCKOUT,
        'geographic_scope_id' => $this->geographicUnit->id,
        'registration_closes_at' => Carbon::now()->addDays(7),
        'starts_at' => Carbon::now()->addDays(8),
        'status' => TournamentStatus::DRAFT,
        'created_by' => $this->organizer->id,
        'race_to' => 3,
    ]);

    $response = $this->actingAs($this->organizer, 'api')
        ->putJson("/api/tournaments/{$tournament->id}", [
            'name' => 'Updated Draft',
            'race_to' => 5,
        ]);

    $response->dump();

    $response->assertOk();

    $tournament->refresh();
    expect($tournament->name)->toBe('Updated Draft');
    expect($tournament->race_to)->toBe(5);
});

it('can update a tournament in pending_review status', function () {
    $tournament = Tournament::create([
        'name' => 'Pending Tournament',
        'type' => TournamentType::REGULAR,
        'format' => TournamentFormat::KNOCKOUT,
        'geographic_scope_id' => $this->geographicUnit->id,
        'registration_closes_at' => Carbon::now()->addDays(7),
        'starts_at' => Carbon::now()->addDays(8),
        'status' => TournamentStatus::PENDING_REVIEW,
        'created_by' => $this->organizer->id,
        'race_to' => 3,
    ]);

    $response = $this->actingAs($this->organizer, 'api')
        ->putJson("/api/tournaments/{$tournament->id}", [
            'name' => 'Updated Pending',
        ]);

    $response->dump();

    $response->assertOk();

    $tournament->refresh();
    expect($tournament->name)->toBe('Updated Pending');
});

it('cannot update a tournament in active status', function () {
    $tournament = Tournament::create([
        'name' => 'Active Tournament',
        'type' => TournamentType::REGULAR,
        'format' => TournamentFormat::KNOCKOUT,
        'geographic_scope_id' => $this->geographicUnit->id,
        'registration_closes_at' => Carbon::now()->subDays(2),
        'starts_at' => Carbon::now()->subDay(),
        'status' => TournamentStatus::ACTIVE,
        'created_by' => $this->organizer->id,
        'race_to' => 3,
    ]);

    $response = $this->actingAs($this->organizer, 'api')
        ->putJson("/api/tournaments/{$tournament->id}", [
            'name' => 'Should Not Update',
        ]);

    $response->dump();

    // Should still return OK but not update (current behavior)
    // or should return 422/403

    $tournament->refresh();
    expect($tournament->name)->toBe('Active Tournament'); // Should NOT have changed
});

it('returns validation errors for invalid data', function () {
    $tournament = Tournament::create([
        'name' => 'Test Tournament',
        'type' => TournamentType::REGULAR,
        'format' => TournamentFormat::KNOCKOUT,
        'geographic_scope_id' => $this->geographicUnit->id,
        'registration_closes_at' => Carbon::now()->addDays(7),
        'starts_at' => Carbon::now()->addDays(8),
        'status' => TournamentStatus::REGISTRATION,
        'created_by' => $this->organizer->id,
        'race_to' => 3,
    ]);

    $response = $this->actingAs($this->organizer, 'api')
        ->putJson("/api/tournaments/{$tournament->id}", [
            'name' => 'AB', // Too short (min 3)
        ]);

    $response->dump();

    $response->assertStatus(422);
});

it('prevents non-owner from updating tournament', function () {
    $otherUser = User::factory()->create(['is_organizer' => true]);

    $tournament = Tournament::create([
        'name' => 'Someone Elses Tournament',
        'type' => TournamentType::REGULAR,
        'format' => TournamentFormat::KNOCKOUT,
        'geographic_scope_id' => $this->geographicUnit->id,
        'registration_closes_at' => Carbon::now()->addDays(7),
        'starts_at' => Carbon::now()->addDays(8),
        'status' => TournamentStatus::REGISTRATION,
        'created_by' => $this->organizer->id,
        'race_to' => 3,
    ]);

    $response = $this->actingAs($otherUser, 'api')
        ->putJson("/api/tournaments/{$tournament->id}", [
            'name' => 'Trying to Hijack',
        ]);

    $response->dump();

    $response->assertStatus(403);
});
