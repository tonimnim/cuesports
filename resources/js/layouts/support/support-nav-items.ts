import { LayoutDashboard, AlertTriangle, UserCircle, Building2, History, Trophy, Users2, Wallet, Newspaper, type LucideIcon } from 'lucide-react';

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
        title: 'Tournaments',
        href: '/support/tournaments',
        icon: Trophy,
    },
    {
        title: 'Disputes',
        href: '/support/disputes',
        icon: AlertTriangle,
    },
    {
        title: 'Payouts',
        href: '/support/payouts',
        icon: Wallet,
    },
    {
        title: 'Players',
        href: '/support/players',
        icon: UserCircle,
    },
    {
        title: 'Organizers',
        href: '/support/organizers',
        icon: Building2,
    },
    {
        title: 'Communities',
        href: '/support/communities',
        icon: Users2,
    },
    {
        title: 'Activity Log',
        href: '/support/activity',
        icon: History,
    },
    {
        title: 'Articles',
        href: '/support/articles',
        icon: Newspaper,
    },
];
