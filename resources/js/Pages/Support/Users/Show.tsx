import { Head, Link, router } from '@inertiajs/react';
import { SupportLayout } from '@/layouts/support';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    ArrowLeft,
    User as UserIcon,
    Mail,
    Phone,
    Shield,
    Calendar,
    CheckCircle,
    XCircle,
    Trophy,
    Star,
    TrendingUp,
    Building,
    AlertTriangle,
} from 'lucide-react';
import type { User } from '@/types';

interface Props {
    user: User;
}

export default function UserShow({ user }: Props) {
    const getDisplayName = () => {
        if (user.player_profile) {
            return user.player_profile.nickname ||
                `${user.player_profile.first_name} ${user.player_profile.last_name}`;
        }
        return user.email;
    };

    const getInitials = () => {
        if (user.player_profile) {
            const first = user.player_profile.first_name?.[0] || '';
            const last = user.player_profile.last_name?.[0] || '';
            return (first + last).toUpperCase() || 'U';
        }
        return user.email?.[0]?.toUpperCase() || 'U';
    };

    const formatDate = (dateString: string | null | undefined) => {
        if (!dateString) return 'Not verified';
        return new Date(dateString).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const getRatingCategoryColor = (category: string | undefined) => {
        switch (category?.toLowerCase()) {
            case 'pro':
                return 'bg-purple-100 text-purple-800';
            case 'advanced':
                return 'bg-blue-100 text-blue-800';
            case 'intermediate':
                return 'bg-green-100 text-green-800';
            default:
                return 'bg-gray-100 text-gray-800';
        }
    };

    const getRoles = () => {
        const roles: string[] = [];
        if (user.roles.is_super_admin) roles.push('Super Admin');
        if (user.roles.is_support) roles.push('Support');
        if (user.roles.is_organizer) roles.push('Organizer');
        if (user.roles.is_player) roles.push('Player');
        return roles;
    };

    const handleReactivate = () => {
        router.post(`/support/users/${user.id}/reactivate`);
    };

    const handleDeactivate = () => {
        if (confirm('Are you sure you want to deactivate this user? They will be logged out of all devices.')) {
            router.post(`/support/users/${user.id}/deactivate`);
        }
    };

    const winRate = user.player_profile?.total_matches
        ? ((user.player_profile.wins || 0) / user.player_profile.total_matches * 100).toFixed(1)
        : 0;

    const tournamentWinRate = user.player_profile?.tournaments_played
        ? ((user.player_profile.tournaments_won || 0) / user.player_profile.tournaments_played * 100).toFixed(1)
        : 0;

    return (
        <SupportLayout>
            <Head title={`User: ${getDisplayName()}`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="icon" asChild>
                            <Link href="/support/users">
                                <ArrowLeft className="size-5" />
                            </Link>
                        </Button>
                        <Avatar className="size-14">
                            <AvatarImage
                                src={user.player_profile?.photo_url || undefined}
                                alt={getDisplayName()}
                            />
                            <AvatarFallback className="bg-teal-100 text-teal-700 text-lg">
                                {getInitials()}
                            </AvatarFallback>
                        </Avatar>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">
                                {getDisplayName()}
                            </h1>
                            <p className="text-muted-foreground">{user.email}</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        {user.is_active ? (
                            <Badge className="bg-green-100 text-green-700">
                                <CheckCircle className="size-3 mr-1" />
                                Active
                            </Badge>
                        ) : (
                            <Badge className="bg-red-100 text-red-700">
                                <XCircle className="size-3 mr-1" />
                                Inactive
                            </Badge>
                        )}
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Main Content */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Account Info */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <UserIcon className="size-5" />
                                    Account Information
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-1">
                                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                            <Mail className="size-4" />
                                            Email
                                        </div>
                                        <p className="font-medium">{user.email}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {user.email_verified_at ? (
                                                <span className="text-green-600">
                                                    Verified {formatDate(user.email_verified_at)}
                                                </span>
                                            ) : (
                                                <span className="text-orange-600">Not verified</span>
                                            )}
                                        </p>
                                    </div>
                                    <div className="space-y-1">
                                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                            <Phone className="size-4" />
                                            Phone
                                        </div>
                                        <p className="font-medium">{user.phone_number || 'Not provided'}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {user.phone_verified_at ? (
                                                <span className="text-green-600">
                                                    Verified {formatDate(user.phone_verified_at)}
                                                </span>
                                            ) : (
                                                <span className="text-orange-600">Not verified</span>
                                            )}
                                        </p>
                                    </div>
                                </div>

                                <Separator />

                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-1">
                                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                            <Shield className="size-4" />
                                            Roles
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            {getRoles().map((role) => (
                                                <Badge key={role} variant="outline">
                                                    {role}
                                                </Badge>
                                            ))}
                                        </div>
                                    </div>
                                    <div className="space-y-1">
                                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                            <Calendar className="size-4" />
                                            Member Since
                                        </div>
                                        <p className="font-medium">{formatDate(user.created_at)}</p>
                                    </div>
                                </div>

                                {user.country && (
                                    <>
                                        <Separator />
                                        <div className="space-y-1">
                                            <div className="text-sm text-muted-foreground">Country</div>
                                            <p className="font-medium">{user.country.name}</p>
                                        </div>
                                    </>
                                )}
                            </CardContent>
                        </Card>

                        {/* Player Stats */}
                        {user.roles.is_player && user.player_profile && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Trophy className="size-5" />
                                        Player Statistics
                                    </CardTitle>
                                    <CardDescription>
                                        {user.player_profile.first_name} {user.player_profile.last_name}
                                        {user.player_profile.nickname && (
                                            <span className="text-muted-foreground">
                                                {' '}({user.player_profile.nickname})
                                            </span>
                                        )}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-6">
                                    {/* Rating Section */}
                                    <div className="flex items-center justify-between p-4 rounded-lg bg-slate-50">
                                        <div className="flex items-center gap-4">
                                            <div className="flex size-12 items-center justify-center rounded-full bg-teal-100">
                                                <Star className="size-6 text-teal-600" />
                                            </div>
                                            <div>
                                                <p className="text-sm text-muted-foreground">Current Rating</p>
                                                <p className="text-3xl font-bold">{user.player_profile.rating}</p>
                                            </div>
                                        </div>
                                        <div className="text-right">
                                            <Badge className={getRatingCategoryColor(user.player_profile.rating_category)}>
                                                {user.player_profile.rating_category || 'Beginner'}
                                            </Badge>
                                            {user.player_profile.best_rating && (
                                                <p className="text-sm text-muted-foreground mt-1">
                                                    Best: {user.player_profile.best_rating}
                                                </p>
                                            )}
                                        </div>
                                    </div>

                                    {/* Match Stats */}
                                    <div>
                                        <h4 className="text-sm font-medium text-muted-foreground mb-3">Match Performance</h4>
                                        <div className="grid grid-cols-4 gap-4">
                                            <div className="text-center p-3 rounded-lg bg-slate-50">
                                                <p className="text-2xl font-bold">
                                                    {user.player_profile.total_matches || 0}
                                                </p>
                                                <p className="text-xs text-muted-foreground">Total Matches</p>
                                            </div>
                                            <div className="text-center p-3 rounded-lg bg-green-50">
                                                <p className="text-2xl font-bold text-green-700">
                                                    {user.player_profile.wins || 0}
                                                </p>
                                                <p className="text-xs text-muted-foreground">Wins</p>
                                            </div>
                                            <div className="text-center p-3 rounded-lg bg-red-50">
                                                <p className="text-2xl font-bold text-red-700">
                                                    {user.player_profile.losses || 0}
                                                </p>
                                                <p className="text-xs text-muted-foreground">Losses</p>
                                            </div>
                                            <div className="text-center p-3 rounded-lg bg-blue-50">
                                                <p className="text-2xl font-bold text-blue-700">
                                                    {winRate}%
                                                </p>
                                                <p className="text-xs text-muted-foreground">Win Rate</p>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Tournament Stats */}
                                    <div>
                                        <h4 className="text-sm font-medium text-muted-foreground mb-3">Tournament Performance</h4>
                                        <div className="grid grid-cols-3 gap-4">
                                            <div className="text-center p-3 rounded-lg bg-slate-50">
                                                <p className="text-2xl font-bold">
                                                    {user.player_profile.tournaments_played || 0}
                                                </p>
                                                <p className="text-xs text-muted-foreground">Tournaments Played</p>
                                            </div>
                                            <div className="text-center p-3 rounded-lg bg-yellow-50">
                                                <p className="text-2xl font-bold text-yellow-700">
                                                    {user.player_profile.tournaments_won || 0}
                                                </p>
                                                <p className="text-xs text-muted-foreground">Tournaments Won</p>
                                            </div>
                                            <div className="text-center p-3 rounded-lg bg-purple-50">
                                                <p className="text-2xl font-bold text-purple-700">
                                                    {tournamentWinRate}%
                                                </p>
                                                <p className="text-xs text-muted-foreground">Tournament Win Rate</p>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Organizer Info */}
                        {user.roles.is_organizer && user.organizer_profile && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Building className="size-5" />
                                        Organizer Profile
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="grid gap-4 md:grid-cols-3">
                                        <div className="space-y-1">
                                            <p className="text-sm text-muted-foreground">Organization</p>
                                            <p className="font-medium">
                                                {user.organizer_profile.organization_name || 'Not specified'}
                                            </p>
                                        </div>
                                        <div className="space-y-1">
                                            <p className="text-sm text-muted-foreground">Tournaments Hosted</p>
                                            <p className="font-medium">
                                                {user.organizer_profile.tournaments_hosted || 0}
                                            </p>
                                        </div>
                                        <div className="space-y-1">
                                            <p className="text-sm text-muted-foreground">Status</p>
                                            {user.organizer_profile.is_active ? (
                                                <Badge className="bg-green-100 text-green-700">Active</Badge>
                                            ) : (
                                                <Badge className="bg-red-100 text-red-700">Inactive</Badge>
                                            )}
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        {/* Quick Stats */}
                        {user.roles.is_player && user.player_profile && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <TrendingUp className="size-4" />
                                        Quick Stats
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3 text-sm">
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Rating</span>
                                        <span className="font-medium">{user.player_profile.rating}</span>
                                    </div>
                                    <Separator />
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Category</span>
                                        <Badge
                                            variant="secondary"
                                            className={getRatingCategoryColor(user.player_profile.rating_category)}
                                        >
                                            {user.player_profile.rating_category || 'Beginner'}
                                        </Badge>
                                    </div>
                                    <Separator />
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Win Rate</span>
                                        <span className="font-medium">{winRate}%</span>
                                    </div>
                                    <Separator />
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Matches</span>
                                        <span className="font-medium">{user.player_profile.total_matches || 0}</span>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Actions */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Shield className="size-4" />
                                    Account Actions
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {user.is_active ? (
                                    <>
                                        {user.roles.is_super_admin ? (
                                            <div className="p-3 rounded-lg bg-yellow-50 border border-yellow-200">
                                                <div className="flex items-center gap-2 text-yellow-700">
                                                    <AlertTriangle className="size-4" />
                                                    <span className="text-sm font-medium">Protected Account</span>
                                                </div>
                                                <p className="text-xs text-yellow-600 mt-1">
                                                    Super admin accounts cannot be deactivated.
                                                </p>
                                            </div>
                                        ) : (
                                            <Button
                                                variant="destructive"
                                                className="w-full"
                                                onClick={handleDeactivate}
                                            >
                                                <XCircle className="size-4 mr-2" />
                                                Deactivate Account
                                            </Button>
                                        )}
                                    </>
                                ) : (
                                    <Button
                                        className="w-full bg-green-600 hover:bg-green-700"
                                        onClick={handleReactivate}
                                    >
                                        <CheckCircle className="size-4 mr-2" />
                                        Reactivate Account
                                    </Button>
                                )}
                                <p className="text-xs text-muted-foreground text-center">
                                    {user.is_active
                                        ? 'Deactivating will log the user out of all devices.'
                                        : 'Reactivating will allow the user to log in again.'}
                                </p>
                            </CardContent>
                        </Card>

                        {/* Account Timeline */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Calendar className="size-4" />
                                    Account Timeline
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="text-sm space-y-3">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Created</span>
                                    <span>{formatDate(user.created_at)}</span>
                                </div>
                                <Separator />
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Last Updated</span>
                                    <span>{formatDate(user.updated_at)}</span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </SupportLayout>
    );
}
