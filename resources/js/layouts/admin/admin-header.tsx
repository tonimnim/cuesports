import { Link, router } from '@inertiajs/react';
import { useAuth } from '@/hooks';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { MenuIcon, LogOutIcon, UserIcon } from 'lucide-react';

interface AdminHeaderProps {
    onMenuClick: () => void;
}

export function AdminHeader({ onMenuClick }: AdminHeaderProps) {
    const { user } = useAuth();

    const getInitials = () => {
        if (user?.player_profile) {
            const first = user.player_profile.first_name?.[0] || '';
            const last = user.player_profile.last_name?.[0] || '';
            return (first + last).toUpperCase() || 'A';
        }
        return user?.email?.[0]?.toUpperCase() || 'A';
    };

    const getDisplayName = () => {
        if (user?.player_profile) {
            return user.player_profile.nickname ||
                `${user.player_profile.first_name} ${user.player_profile.last_name}`;
        }
        return user?.email || 'Admin';
    };

    const handleLogout = () => {
        router.post('/dashboard/logout');
    };

    return (
        <header className="sticky top-0 z-30 flex h-16 items-center justify-between border-b bg-white px-4 lg:px-6">
            {/* Left side */}
            <div className="flex items-center gap-4">
                <Button
                    variant="ghost"
                    size="icon"
                    className="lg:hidden"
                    onClick={onMenuClick}
                >
                    <MenuIcon className="size-5" />
                </Button>
                <div className="hidden lg:block">
                    <h1 className="text-lg font-semibold text-slate-900">Admin Dashboard</h1>
                </div>
            </div>

            {/* Right side */}
            <div className="flex items-center gap-4">
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" className="relative h-9 w-9 rounded-full">
                            <Avatar className="size-9">
                                <AvatarImage
                                    src={user?.player_profile?.photo_url || undefined}
                                    alt={getDisplayName()}
                                />
                                <AvatarFallback className="bg-indigo-100 text-indigo-700">
                                    {getInitials()}
                                </AvatarFallback>
                            </Avatar>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent className="w-56" align="end" forceMount>
                        <DropdownMenuLabel className="font-normal">
                            <div className="flex flex-col gap-1">
                                <p className="text-sm font-medium leading-none">
                                    {getDisplayName()}
                                </p>
                                <p className="text-xs leading-none text-muted-foreground">
                                    {user?.email}
                                </p>
                            </div>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem asChild>
                            <Link href="/profile" className="cursor-pointer">
                                <UserIcon className="mr-2 size-4" />
                                Profile
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            className="cursor-pointer text-red-600 focus:text-red-600"
                            onClick={handleLogout}
                        >
                            <LogOutIcon className="mr-2 size-4" />
                            Log out
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </header>
    );
}
