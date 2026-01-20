import { usePage } from '@inertiajs/react';
import type { Auth, Flash } from '@/types';

interface PageProps {
    auth: Auth;
    flash: Flash;
}

export function useAuth() {
    const { auth, flash } = usePage<PageProps>().props;

    return {
        user: auth.user,
        isAdmin: auth.user?.roles.is_super_admin ?? false,
        isSupport: auth.user?.roles.is_support ?? false,
        isPlayer: auth.user?.roles.is_player ?? false,
        isOrganizer: auth.user?.roles.is_organizer ?? false,
        canAccessSupport: (auth.user?.roles.is_support || auth.user?.roles.is_super_admin) ?? false,
        canAccessAdmin: auth.user?.roles.is_super_admin ?? false,
        flash,
    };
}
