export type MatchStatus =
    | 'scheduled'
    | 'pending_confirmation'
    | 'completed'
    | 'disputed'
    | 'expired'
    | 'cancelled';

export type MatchType =
    | 'regular'
    | 'quarter_final'
    | 'semi_final'
    | 'final'
    | 'third_place'
    | 'bye'
    | 'group';

export interface MatchPlayer {
    id: number;
    seed: number | null;
    player_profile: {
        id: number;
        first_name: string;
        last_name: string;
        nickname: string | null;
        photo_url: string | null;
        rating: number;
    };
}

export interface Dispute {
    id: number;
    tournament: {
        id: number;
        name: string;
        slug: string;
    };
    player1: MatchPlayer;
    player2: MatchPlayer;
    player1_score: number | null;
    player2_score: number | null;
    status: MatchStatus;
    match_type: MatchType;
    round_number: number;
    round_name: string;
    submitted_by: MatchPlayer | null;
    submitted_at: string | null;
    disputed_by: MatchPlayer | null;
    disputed_at: string | null;
    dispute_reason: string | null;
    resolved_by: number | null;
    resolved_at: string | null;
    resolution_notes: string | null;
    created_at: string;
    updated_at: string;
}

export interface PaginatedDisputes {
    data: Dispute[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}
