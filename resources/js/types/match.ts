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
    seed?: number | null;
    player_profile: {
        id: number;
        first_name: string;
        last_name: string;
        nickname: string | null;
        photo_url: string | null;
        rating: number;
        rating_category?: string;
        total_matches?: number;
        wins?: number;
        losses?: number;
        best_rating?: number;
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

// Evidence types for dispute resolution
export interface MatchEvidence {
    id: number;
    file_url: string;
    file_type: 'image' | 'video';
    thumbnail_url: string | null;
    description: string | null;
    evidence_type: 'score_proof' | 'dispute_evidence' | 'other';
    uploaded_at: string;
    uploader: {
        id: number;
        name: string;
    } | null;
}

// Player dispute statistics
export interface PlayerDisputeStats {
    disputes_filed: number;
    disputes_against: number;
    disputes_won: number;
    disputes_lost: number;
}

// Head-to-head record
export interface HeadToHead {
    total: number;
    player1_wins: number;
    player2_wins: number;
}

// Match history entry
export interface MatchHistoryEntry {
    id: number;
    opponent_name: string;
    won: boolean;
    score: string;
    rating_before?: number;
    rating_after?: number;
    rating_change: number;
    tournament_name: string;
    match_type?: string;
    round_name: string;
    played_at: string | null;
}

// Dispute history entry (from user perspective)
export interface DisputeHistoryEntry {
    id: number;
    tournament_name: string;
    opponent_name: string;
    was_disputer: boolean;
    status: MatchStatus;
    dispute_reason: string | null;
    resolution_notes: string | null;
    disputed_at: string | null;
    resolved_at: string | null;
}

// Rating history entry
export interface RatingHistoryEntry {
    id: number;
    old_rating: number;
    new_rating: number;
    change: number;
    reason: string;
    created_at: string;
}
