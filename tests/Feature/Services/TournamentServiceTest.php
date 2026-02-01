<?php

namespace Tests\Feature\Services;

use App\DTOs\CreateTournamentDTO;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Enums\TournamentType;
use App\Events\TournamentCreated;
use App\Events\TournamentStarted;
use App\Models\GeographicUnit;
use App\Models\Tournament;
use App\Models\User;
use App\Contracts\BracketResult;
use App\Services\Bracket\BracketService;
use App\Services\TournamentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Mockery;

beforeEach(function () {
    $this->bracketService = Mockery::mock(BracketService::class);
    $this->service = new TournamentService($this->bracketService);
    
    $this->geographicUnit = GeographicUnit::create([
        'name' => 'Test Unit',
        'code' => 'TU',
        'level' => 1,
        'country_code' => 'US',
        'is_active' => true,
    ]);
});

it('can create a tournament', function () {
    Event::fake([TournamentCreated::class]);
    
    $user = User::factory()->create();
    
    $dto = new CreateTournamentDTO(
        name: 'Test Tournament',
        type: TournamentType::REGULAR,
        format: TournamentFormat::KNOCKOUT,
        geographicScopeId: $this->geographicUnit->id,
        registrationClosesAt: Carbon::now()->addDays(7),
        startsAt: Carbon::now()->addDays(8),
    );
    
    $tournament = $this->service->create($dto, $user);
    
    expect($tournament)->toBeInstanceOf(Tournament::class)
        ->name->toBe('Test Tournament')
        ->status->toBe(TournamentStatus::DRAFT)
        ->created_by->toBe($user->id);
        
    Event::assertDispatched(TournamentCreated::class);
});

it('can open registration for a draft tournament', function () {
    $user = User::factory()->create();
    $tournament = Tournament::create([
        'name' => 'Draft Tournament',
        'type' => TournamentType::REGULAR,
        'format' => TournamentFormat::KNOCKOUT,
        'geographic_scope_id' => $this->geographicUnit->id,
        'registration_closes_at' => Carbon::now()->addDays(7),
        'starts_at' => Carbon::now()->addDays(8),
        'status' => TournamentStatus::DRAFT,
        'created_by' => $user->id,
    ]);
    
    $updatedTournament = $this->service->openRegistration($tournament);
    
    expect($updatedTournament->status)->toBe(TournamentStatus::REGISTRATION);
    expect($updatedTournament->registration_opens_at)->not->toBeNull();
});

it('cannot open registration for a non-draft tournament', function () {
    $user = User::factory()->create();
    $tournament = Tournament::create([
        'name' => 'Active Tournament',
        'type' => TournamentType::REGULAR,
        'format' => TournamentFormat::KNOCKOUT,
        'geographic_scope_id' => $this->geographicUnit->id,
        'registration_closes_at' => Carbon::now()->addDays(7),
        'starts_at' => Carbon::now()->addDays(8),
        'status' => TournamentStatus::ACTIVE,
        'created_by' => $user->id,
    ]);
    
    expect(fn () => $this->service->openRegistration($tournament))
        ->toThrow(\InvalidArgumentException::class, 'Can only open registration for draft tournaments.');
});

it('can start a tournament', function () {
    Event::fake([TournamentStarted::class]);
    
    $user = User::factory()->create();
    $tournament = Tournament::create([
        'name' => 'Registration Tournament',
        'type' => TournamentType::REGULAR,
        'format' => TournamentFormat::KNOCKOUT,
        'geographic_scope_id' => $this->geographicUnit->id,
        'registration_closes_at' => Carbon::now()->subDay(),
        'starts_at' => Carbon::now()->addDay(),
        'status' => TournamentStatus::REGISTRATION,
        'created_by' => $user->id,
        'participants_count' => 2,
    ]);
    
    $this->bracketService->shouldReceive('generate')
        ->once()
        ->with(Mockery::on(fn($t) => $t->id === $tournament->id))
        ->andReturn(new BracketResult(
            bracketSize: 2,
            totalRounds: 1,
            byeCount: 0,
            matchesCreated: 1,
            byeMatchesProcessed: 0,
        ));
        
    $startedTournament = $this->service->start($tournament);
    
    expect($startedTournament->status)->toBe(TournamentStatus::ACTIVE);
    expect($startedTournament->starts_at)->not->toBeNull();
    Event::assertDispatched(TournamentStarted::class);
});
