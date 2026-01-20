<?php

namespace App\Models;

use App\Enums\MatchStatus;
use App\Enums\MatchType;
use App\Enums\ParticipantStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Match extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'stage_id',
        'round_number',
        'round_name',
        'match_type',
        'bracket_position',
        'player1_id',
        'player2_id',
        'player1_score',
        'player2_score',
        'winner_id',
        'loser_id',
        'status',
        'submitted_by',
        'submitted_at',
        'confirmed_by',
        'confirmed_at',
        'disputed_by',
        'disputed_at',
        'dispute_reason',
        'resolved_by',
        'resolved_at',
        'resolution_notes',
        'scheduled_play_date',
        'played_at',
        'expires_at',
        'next_match_id',
        'next_match_slot',
        'group_number',
        'geographic_unit_id',
    ];

    protected $casts = [
        'match_type' => MatchType::class,
        'status' => MatchStatus::class,
        'round_number' => 'integer',
        'bracket_position' => 'integer',
        'player1_score' => 'integer',
        'player2_score' => 'integer',
        'group_number' => 'integer',
        'submitted_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'disputed_at' => 'datetime',
        'resolved_at' => 'datetime',
        'scheduled_play_date' => 'datetime',
        'played_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // Relationships

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(TournamentStage::class, 'stage_id');
    }

    public function player1(): BelongsTo
    {
        return $this->belongsTo(TournamentParticipant::class, 'player1_id');
    }

    public function player2(): BelongsTo
    {
        return $this->belongsTo(TournamentParticipant::class, 'player2_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(TournamentParticipant::class, 'winner_id');
    }

    public function loser(): BelongsTo
    {
        return $this->belongsTo(TournamentParticipant::class, 'loser_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(TournamentParticipant::class, 'submitted_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(TournamentParticipant::class, 'confirmed_by');
    }

    public function disputedBy(): BelongsTo
    {
        return $this->belongsTo(TournamentParticipant::class, 'disputed_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function nextMatch(): BelongsTo
    {
        return $this->belongsTo(Match::class, 'next_match_id');
    }

    public function previousMatches(): HasOne
    {
        return $this->hasOne(Match::class, 'next_match_id');
    }

    public function geographicUnit(): BelongsTo
    {
        return $this->belongsTo(GeographicUnit::class);
    }

    public function matchHistory(): HasMany
    {
        return $this->hasMany(PlayerMatchHistory::class);
    }

    public function matchHistoryForParticipant(TournamentParticipant $participant): ?PlayerMatchHistory
    {
        return $this->matchHistory()
            ->where('player_profile_id', $participant->playerProfile->id)
            ->first();
    }

    // Scopes

    public function scopeScheduled($query)
    {
        return $query->where('status', MatchStatus::SCHEDULED);
    }

    public function scopePendingConfirmation($query)
    {
        return $query->where('status', MatchStatus::PENDING_CONFIRMATION);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', MatchStatus::COMPLETED);
    }

    public function scopeDisputed($query)
    {
        return $query->where('status', MatchStatus::DISPUTED);
    }

    public function scopeExpired($query)
    {
        return $query->where('status', MatchStatus::EXPIRED);
    }

    public function scopeExpiring($query)
    {
        return $query->where('status', MatchStatus::SCHEDULED)
            ->where('expires_at', '<=', now());
    }

    public function scopePendingConfirmationExpiring($query)
    {
        return $query->where('status', MatchStatus::PENDING_CONFIRMATION)
            ->where('expires_at', '<=', now()->subHours(24)); // 24h confirmation window
    }

    public function scopeForPlayer($query, int $participantId)
    {
        return $query->where(function ($q) use ($participantId) {
            $q->where('player1_id', $participantId)
                ->orWhere('player2_id', $participantId);
        });
    }

    public function scopeInRound($query, int $roundNumber)
    {
        return $query->where('round_number', $roundNumber);
    }

    public function scopeOfType($query, MatchType $type)
    {
        return $query->where('match_type', $type);
    }

    // Status Checks

    public function isScheduled(): bool
    {
        return $this->status === MatchStatus::SCHEDULED;
    }

    public function isPendingConfirmation(): bool
    {
        return $this->status === MatchStatus::PENDING_CONFIRMATION;
    }

    public function isCompleted(): bool
    {
        return $this->status === MatchStatus::COMPLETED;
    }

    public function isDisputed(): bool
    {
        return $this->status === MatchStatus::DISPUTED;
    }

    public function isExpired(): bool
    {
        return $this->status === MatchStatus::EXPIRED;
    }

    public function isBye(): bool
    {
        return $this->match_type === MatchType::BYE;
    }

    public function isThirdPlace(): bool
    {
        return $this->match_type === MatchType::THIRD_PLACE;
    }

    public function isFinal(): bool
    {
        return $this->match_type === MatchType::FINAL;
    }

    public function hasExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConfirmationExpired(): bool
    {
        if (!$this->isPendingConfirmation() || !$this->submitted_at) {
            return false;
        }

        $confirmationDeadline = $this->submitted_at->addHours(
            $this->tournament->confirmation_hours
        );

        return $confirmationDeadline->isPast();
    }

    // Player Checks

    public function hasPlayer(TournamentParticipant $participant): bool
    {
        return $this->player1_id === $participant->id || $this->player2_id === $participant->id;
    }

    public function getOpponent(TournamentParticipant $participant): ?TournamentParticipant
    {
        if ($this->player1_id === $participant->id) {
            return $this->player2;
        }

        if ($this->player2_id === $participant->id) {
            return $this->player1;
        }

        return null;
    }

    public function isPlayer1(TournamentParticipant $participant): bool
    {
        return $this->player1_id === $participant->id;
    }

    public function isPlayer2(TournamentParticipant $participant): bool
    {
        return $this->player2_id === $participant->id;
    }

    // Score Methods

    public function getScoreDisplay(): string
    {
        return "{$this->player1_score}:{$this->player2_score}";
    }

    public function getScoreForPlayer(TournamentParticipant $participant): array
    {
        if ($this->isPlayer1($participant)) {
            return ['own' => $this->player1_score, 'opponent' => $this->player2_score];
        }

        if ($this->isPlayer2($participant)) {
            return ['own' => $this->player2_score, 'opponent' => $this->player1_score];
        }

        return ['own' => 0, 'opponent' => 0];
    }

    public function isValidScore(int $score1, int $score2): bool
    {
        $bestOf = $this->tournament->best_of;
        $winScore = (int) ceil($bestOf / 2);

        // One player must have exactly the winning score
        if ($score1 !== $winScore && $score2 !== $winScore) {
            return false;
        }

        // The other player must have less than the winning score
        if ($score1 === $winScore && $score2 >= $winScore) {
            return false;
        }

        if ($score2 === $winScore && $score1 >= $winScore) {
            return false;
        }

        // Scores must be non-negative
        if ($score1 < 0 || $score2 < 0) {
            return false;
        }

        return true;
    }

    // Result Submission

    public function canSubmitResult(TournamentParticipant $participant): bool
    {
        if (!$this->isScheduled()) {
            return false;
        }

        if ($this->hasExpired()) {
            return false;
        }

        if (!$this->hasPlayer($participant)) {
            return false;
        }

        return true;
    }

    public function submitResult(
        TournamentParticipant $submitter,
        int $submitterScore,
        int $opponentScore
    ): void {
        // Determine which player is submitting
        if ($this->isPlayer1($submitter)) {
            $this->player1_score = $submitterScore;
            $this->player2_score = $opponentScore;
        } else {
            $this->player1_score = $opponentScore;
            $this->player2_score = $submitterScore;
        }

        $this->submitted_by = $submitter->id;
        $this->submitted_at = now();
        $this->status = MatchStatus::PENDING_CONFIRMATION;

        // Set expiry for confirmation (24 hours from submission)
        $this->expires_at = now()->addHours($this->tournament->confirmation_hours);

        $this->save();
    }

    public function canConfirm(TournamentParticipant $participant): bool
    {
        if (!$this->isPendingConfirmation()) {
            return false;
        }

        if (!$this->hasPlayer($participant)) {
            return false;
        }

        // Can't confirm your own submission
        if ($this->submitted_by === $participant->id) {
            return false;
        }

        return true;
    }

    public function confirm(TournamentParticipant $confirmer): void
    {
        $this->confirmed_by = $confirmer->id;
        $this->confirmed_at = now();
        $this->played_at = now();
        $this->status = MatchStatus::COMPLETED;

        // Determine winner and loser
        $this->determineResult();

        $this->save();

        // Update participant stats
        $this->updateParticipantStats();

        // Advance winner to next match
        $this->advanceWinner();
    }

    public function canDispute(TournamentParticipant $participant): bool
    {
        if (!$this->isPendingConfirmation()) {
            return false;
        }

        if (!$this->hasPlayer($participant)) {
            return false;
        }

        // Can't dispute your own submission
        if ($this->submitted_by === $participant->id) {
            return false;
        }

        return true;
    }

    public function dispute(TournamentParticipant $disputer, string $reason): void
    {
        $this->disputed_by = $disputer->id;
        $this->disputed_at = now();
        $this->dispute_reason = $reason;
        $this->status = MatchStatus::DISPUTED;

        $this->save();
    }

    public function resolve(User $resolver, int $player1Score, int $player2Score, string $notes = null): void
    {
        $this->player1_score = $player1Score;
        $this->player2_score = $player2Score;
        $this->resolved_by = $resolver->id;
        $this->resolved_at = now();
        $this->resolution_notes = $notes;
        $this->played_at = now();
        $this->status = MatchStatus::COMPLETED;

        // Determine winner and loser
        $this->determineResult();

        $this->save();

        // Update participant stats
        $this->updateParticipantStats();

        // Advance winner to next match
        $this->advanceWinner();
    }

    // Internal Methods

    protected function determineResult(): void
    {
        if ($this->player1_score > $this->player2_score) {
            $this->winner_id = $this->player1_id;
            $this->loser_id = $this->player2_id;
        } else {
            $this->winner_id = $this->player2_id;
            $this->loser_id = $this->player1_id;
        }
    }

    protected function updateParticipantStats(): void
    {
        // Update player 1 tournament participant stats
        $this->player1->recordMatchResult(
            $this->player1_score,
            $this->player2_score,
            $this->winner_id === $this->player1_id
        );

        // Update player 2 tournament participant stats (if not a bye)
        if ($this->player2) {
            $this->player2->recordMatchResult(
                $this->player2_score,
                $this->player1_score,
                $this->winner_id === $this->player2_id
            );
        }

        // Update player profile lifetime stats
        $this->updatePlayerProfileStats();

        // Create match history records
        $this->createMatchHistoryRecords();
    }

    protected function updatePlayerProfileStats(): void
    {
        // Update player 1's lifetime stats
        $player1Profile = $this->player1->playerProfile;
        $player1Profile->recordMatch(
            $this->winner_id === $this->player1_id,
            $this->player1_score,
            $this->player2_score
        );

        // Update player 2's lifetime stats (if not a bye)
        if ($this->player2) {
            $player2Profile = $this->player2->playerProfile;
            $player2Profile->recordMatch(
                $this->winner_id === $this->player2_id,
                $this->player2_score,
                $this->player1_score
            );
        }
    }

    protected function createMatchHistoryRecords(): void
    {
        // Create match history for player 1
        $player1Profile = $this->player1->playerProfile;
        PlayerMatchHistory::createFromMatch(
            $this,
            $this->player1,
            $player1Profile->rating,  // Rating before (will be updated by RatingService)
            $player1Profile->rating   // Rating after (will be updated by RatingService)
        );

        // Create match history for player 2 (if not a bye)
        if ($this->player2) {
            $player2Profile = $this->player2->playerProfile;
            PlayerMatchHistory::createFromMatch(
                $this,
                $this->player2,
                $player2Profile->rating,
                $player2Profile->rating
            );
        }
    }

    protected function advanceWinner(): void
    {
        if (!$this->next_match_id || !$this->winner_id) {
            return;
        }

        $nextMatch = $this->nextMatch;

        if ($this->next_match_slot === 'player1') {
            $nextMatch->player1_id = $this->winner_id;
        } else {
            $nextMatch->player2_id = $this->winner_id;
        }

        $nextMatch->save();
    }

    // Expiry Handling

    public function expire(): void
    {
        $this->status = MatchStatus::EXPIRED;
        $this->save();

        // Handle based on match type
        if ($this->isThirdPlace()) {
            // For third-place match, use FD to determine positions
            $this->determineThirdPlaceByStats();
        } else {
            // For regular matches, both players are eliminated
            $this->eliminateBothPlayers();
        }
    }

    protected function eliminateBothPlayers(): void
    {
        if ($this->player1) {
            $this->player1->disqualify();
        }

        if ($this->player2) {
            $this->player2->disqualify();
        }
    }

    protected function determineThirdPlaceByStats(): void
    {
        if (!$this->player1 || !$this->player2) {
            return;
        }

        // Compare by FD, then by Points, then by rating
        $player1FD = $this->player1->frame_difference;
        $player2FD = $this->player2->frame_difference;

        if ($player1FD !== $player2FD) {
            if ($player1FD > $player2FD) {
                $this->player1->markAsWinner(3);
                $this->player2->markAsWinner(4);
            } else {
                $this->player2->markAsWinner(3);
                $this->player1->markAsWinner(4);
            }
            return;
        }

        // FD is tied, compare by Points
        $player1Pts = $this->player1->points;
        $player2Pts = $this->player2->points;

        if ($player1Pts !== $player2Pts) {
            if ($player1Pts > $player2Pts) {
                $this->player1->markAsWinner(3);
                $this->player2->markAsWinner(4);
            } else {
                $this->player2->markAsWinner(3);
                $this->player1->markAsWinner(4);
            }
            return;
        }

        // Points tied, compare by global rating
        $player1Rating = $this->player1->playerProfile->rating;
        $player2Rating = $this->player2->playerProfile->rating;

        if ($player1Rating >= $player2Rating) {
            $this->player1->markAsWinner(3);
            $this->player2->markAsWinner(4);
        } else {
            $this->player2->markAsWinner(3);
            $this->player1->markAsWinner(4);
        }
    }

    // Bye Match Handling

    public static function createByeMatch(
        Tournament $tournament,
        TournamentParticipant $player,
        int $roundNumber,
        string $roundName,
        ?int $nextMatchId = null,
        ?string $nextMatchSlot = null
    ): self {
        $match = new self([
            'tournament_id' => $tournament->id,
            'round_number' => $roundNumber,
            'round_name' => $roundName,
            'match_type' => MatchType::BYE,
            'player1_id' => $player->id,
            'player2_id' => null,
            'player1_score' => 2,  // Auto-win with best possible score
            'player2_score' => 0,
            'winner_id' => $player->id,
            'loser_id' => null,
            'status' => MatchStatus::COMPLETED,
            'played_at' => now(),
            'expires_at' => now(),
            'next_match_id' => $nextMatchId,
            'next_match_slot' => $nextMatchSlot,
        ]);

        $match->save();

        // Advance to next match if exists
        $match->advanceWinner();

        return $match;
    }

    // Helper Methods

    public function getTimeRemaining(): ?string
    {
        if ($this->isCompleted() || $this->isExpired()) {
            return null;
        }

        if ($this->expires_at->isPast()) {
            return 'Expired';
        }

        return $this->expires_at->diffForHumans();
    }

    public function getConfirmationTimeRemaining(): ?string
    {
        if (!$this->isPendingConfirmation() || !$this->submitted_at) {
            return null;
        }

        $deadline = $this->submitted_at->addHours($this->tournament->confirmation_hours);

        if ($deadline->isPast()) {
            return 'Expired';
        }

        return $deadline->diffForHumans();
    }
}
