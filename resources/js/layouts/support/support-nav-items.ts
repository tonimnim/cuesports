import { LayoutDashboard, AlertTriangle, Users, type LucideIcon } from 'lucide-react';

export interface NavItem {
    title: string;
    href: string;
    icon: LucideIcon;
    badge?: number;
}

export const supportNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/support',
        icon: LayoutDashboard,
    },
    {
        title: 'Disputes',
        href: '/support/disputes',
        icon: AlertTriangle,
    },
    {
        title: 'Users',
        href: '/support/users',
        icon: Users,
    },
];
