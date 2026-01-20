import type { PlayerProfile, UserRoles } from './auth';

export interface User {
    id: number;
    email: string;
    phone_number: string;
    country_id: number | null;
    is_active: boolean;
    email_verified_at: string | null;
    phone_verified_at: string | null;
    created_at: string;
    updated_at: string;
    roles: UserRoles;
    player_profile: PlayerProfile | null;
    country?: {
        id: number;
        name: string;
        country_code: string;
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
