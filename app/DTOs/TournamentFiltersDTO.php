<?php

namespace App\DTOs;

use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Enums\TournamentType;
use Illuminate\Http\Request;

readonly class TournamentFiltersDTO
{
    public function __construct(
        public ?TournamentType $type = null,
        public ?TournamentStatus $status = null,
        public ?TournamentFormat $format = null,
        public ?int $geographicScopeId = null,
        public ?int $createdBy = null,
        public ?string $search = null,
        public bool $registrationOpen = false,
        public string $sortBy = 'created_at',
        public string $sortDirection = 'desc',
        public int $perPage = 15,
        public int $page = 1,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            type: $request->filled('type') ? TournamentType::tryFrom($request->input('type')) : null,
            status: $request->filled('status') ? TournamentStatus::tryFrom($request->input('status')) : null,
            format: $request->filled('format') ? TournamentFormat::tryFrom($request->input('format')) : null,
            geographicScopeId: $request->filled('geographic_scope_id') ? (int) $request->input('geographic_scope_id') : null,
            createdBy: $request->filled('created_by') ? (int) $request->input('created_by') : null,
            search: $request->input('search'),
            registrationOpen: $request->boolean('registration_open'),
            sortBy: $request->input('sort_by', 'created_at'),
            sortDirection: $request->input('sort_direction', 'desc'),
            perPage: min((int) $request->input('per_page', 15), 100),
            page: (int) $request->input('page', 1),
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type?->value,
            'status' => $this->status?->value,
            'format' => $this->format?->value,
            'geographic_scope_id' => $this->geographicScopeId,
            'created_by' => $this->createdBy,
            'search' => $this->search,
            'registration_open' => $this->registrationOpen,
            'sort_by' => $this->sortBy,
            'sort_direction' => $this->sortDirection,
            'per_page' => $this->perPage,
            'page' => $this->page,
        ], fn($value) => $value !== null && $value !== false);
    }
}
