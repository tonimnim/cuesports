import {
    LayoutDashboard,
    Users,
    UserCog,
    Trophy,
    Building2,
    AlertTriangle,
    Star,
    BarChart3,
    Settings,
    Globe,
    Wallet,
    type LucideIcon,
} from 'lucide-react';

export interface NavSubItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href?: string;
    icon: LucideIcon;
    badge?: number;
    children?: NavSubItem[];
}

export interface NavSection {
    title?: string;
    items: NavItem[];
}

export const adminNavSections: NavSection[] = [
    {
        items: [
            {
                title: 'Dashboard',
                href: '/admin',
                icon: LayoutDashboard,
            },
        ],
    },
    {
        title: 'Users',
        items: [
            {
                title: 'Players',
                href: '/admin/users/players',
                icon: Users,
            },
            {
                title: 'Organizers',
                href: '/admin/users/organizers',
                icon: Building2,
            },
            {
                title: 'Support Staff',
                href: '/admin/users/support',
                icon: UserCog,
            },
        ],
    },
    {
        title: 'Geography',
        items: [
            {
                title: 'Manage Locations',
                href: '/admin/geography',
                icon: Globe,
            },
        ],
    },
    {
        title: 'Tournaments',
        items: [
            {
                title: 'All Tournaments',
                href: '/admin/tournaments',
                icon: Trophy,
            },
            {
                title: 'Level Settings',
                href: '/admin/tournaments/level-settings',
                icon: Settings,
            },
            {
                title: 'Disputes',
                href: '/admin/disputes',
                icon: AlertTriangle,
            },
        ],
    },
    {
        title: 'Finance',
        items: [
            {
                title: 'Payouts',
                href: '/admin/payouts',
                icon: Wallet,
            },
        ],
    },
    {
        title: 'System',
        items: [
            {
                title: 'Ratings',
                href: '/admin/ratings',
                icon: Star,
            },
            {
                title: 'Analytics',
                href: '/admin/analytics',
                icon: BarChart3,
            },
            {
                title: 'Settings',
                href: '/admin/settings',
                icon: Settings,
            },
        ],
    },
];
