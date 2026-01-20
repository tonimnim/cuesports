export interface UserRoles {
    is_super_admin: boolean;
    is_support: boolean;
    is_player: boolean;
    is_organizer: boolean;
}

export interface PlayerProfile {
    id: number;
    first_name: string;
    last_name: string;
    nickname: string | null;
    photo_url: string | null;
}

export interface AuthUser {
    id: number;
    email: string;
    phone_number: string;
    roles: UserRoles;
    player_profile: PlayerProfile | null;
}

export interface Auth {
    user: AuthUser | null;
}

export interface Flash {
    success: string | null;
    error: string | null;
}
