export type TournamentStatus =
    | 'draft'
    | 'registration'
    | 'active'
    | 'completed'
    | 'cancelled';

export type TournamentType = 'regular' | 'special';

export type TournamentFormat = 'knockout' | 'groups_knockout';

export interface Tournament {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    type: TournamentType;
    status: TournamentStatus;
    format: TournamentFormat;
    geographic_scope_id: number | null;
    registration_opens_at: string | null;
    registration_closes_at: string | null;
    starts_at: string | null;
    ends_at: string | null;
    best_of: number;
    participants_count: number;
    matches_count: number;
    created_by: number;
    created_at: string;
    updated_at: string;
}

export interface PaginatedTournaments {
    data: Tournament[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}
