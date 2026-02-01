import type { PlayerProfile, UserRoles } from './auth';

export interface OrganizerProfile {
    id: number;
    organization_name: string | null;
    tournaments_hosted: number;
    is_active: boolean;
}

export interface DetailedPlayerProfile extends PlayerProfile {
    total_matches?: number;
    wins?: number;
    losses?: number;
    best_rating?: number;
    tournaments_played?: number;
    tournaments_won?: number;
}

export interface User {
    id: number;
    email: string;
    phone_number: string;
    country_id: number | null;
    is_active: boolean;
    email_verified_at: string | null;
    phone_verified_at: string | null;
    created_at: string;
    updated_at?: string;
    roles: UserRoles;
    player_profile: DetailedPlayerProfile | null;
    organizer_profile?: OrganizerProfile | null;
    country?: {
        id: number;
        name: string;
        country_code?: string;
    };
}

export interface UserListItem {
    id: number;
    email: string;
    phone_number: string;
    is_active: boolean;
    created_at: string;
    player_profile: {
        id: number;
        first_name: string;
        last_name: string;
        nickname: string | null;
        photo_url: string | null;
        rating: number;
        rating_category: string;
    } | null;
    country: {
        id: number;
        name: string;
    } | null;
}

export interface PaginatedUsers {
    data: UserListItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

// User note types
export interface UserNote {
    id: number;
    content: string;
    type: 'general' | 'warning' | 'ban_reason' | 'verification';
    type_label: string;
    is_pinned: boolean;
    created_by: {
        id: number;
        email: string;
    } | null;
    created_at: string;
}

// Activity log entry
export interface ActivityLogEntry {
    id: number;
    action: string;
    action_label: string;
    description: string;
    performed_by: {
        id: number;
        email: string;
    } | null;
    created_at: string;
}

// Organizer types for support
export interface OrganizerListItem {
    id: number;
    organization_name: string;
    logo_url: string | null;
    is_active: boolean;
    tournaments_hosted: number;
    created_at: string;
    user: {
        id: number;
        email: string;
        phone_number: string;
        is_active: boolean;
        country: {
            id: number;
            name: string;
        } | null;
    };
}

export interface OrganizerDetails extends OrganizerListItem {
    description: string | null;
    api_key: string | null;
    api_key_last_used_at: string | null;
    updated_at: string;
}

export interface PaginatedOrganizers {
    data: OrganizerListItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

// Tournament summary for organizer/user pages
export interface TournamentSummary {
    id: number;
    name: string;
    status: string;
    type: string;
    participants_count: number;
    starts_at: string | null;
    ends_at: string | null;
    created_at: string;
}
