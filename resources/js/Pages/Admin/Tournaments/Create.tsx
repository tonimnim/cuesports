import { useState, useMemo } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { AdminLayout } from '@/layouts/admin';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    ArrowLeft,
    Trophy,
    MapPin,
    Calendar,
    Target,
    DollarSign,
    Star,
    Building2,
    Info,
} from 'lucide-react';

interface GeographicUnit {
    id: number;
    name: string;
    level: number;
    level_label: string;
    full_path: string;
}

interface LevelSetting {
    geographic_level: number;
    level_label: string;
    race_to: number;
    finals_race_to: number;
    confirmation_hours: number;
}

interface Level {
    value: number;
    label: string;
}

interface Props {
    geographicUnits: GeographicUnit[];
    levelSettings: LevelSetting[];
    levels: Level[];
}

export default function CreateTournament({ geographicUnits, levelSettings, levels }: Props) {
    const [selectedLevel, setSelectedLevel] = useState<number | null>(null);

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        description: '',
        geographic_scope_id: '',
        venue_name: '',
        venue_address: '',
        registration_opens_at: '',
        registration_closes_at: '',
        starts_at: '',
        race_to: 3,
        finals_race_to: null as number | null,
        confirmation_hours: 24,
        entry_fee: 0,
        entry_fee_currency: 'NGN',
    });

    // Filter geographic units by selected level
    const filteredUnits = useMemo(() => {
        if (!selectedLevel) return [];
        return geographicUnits.filter(unit => unit.level === selectedLevel);
    }, [geographicUnits, selectedLevel]);

    // Get level setting for selected level
    const levelSetting = useMemo(() => {
        if (!selectedLevel) return null;
        return levelSettings.find(s => s.geographic_level === selectedLevel);
    }, [levelSettings, selectedLevel]);

    // Apply default race_to values when level changes
    const handleLevelChange = (level: string) => {
        const levelNum = parseInt(level);
        setSelectedLevel(levelNum);
        setData('geographic_scope_id', '');

        // Find the default settings for this level
        const setting = levelSettings.find(s => s.geographic_level === levelNum);
        if (setting) {
            setData(prev => ({
                ...prev,
                race_to: setting.race_to,
                finals_race_to: setting.finals_race_to,
                confirmation_hours: setting.confirmation_hours,
            }));
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/tournaments', {
            onSuccess: () => {
                // Redirect is handled by the controller
            },
        });
    };

    // Get minimum date for inputs (today)
    const today = new Date().toISOString().split('T')[0];

    return (
        <AdminLayout>
            <Head title="Create Special Tournament" />

            <div className="max-w-4xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild className="hover:bg-[#F1F5F9]">
                        <Link href="/admin/tournaments">
                            <ArrowLeft className="size-5" />
                        </Link>
                    </Button>
                    <div className="flex size-12 items-center justify-center rounded-lg bg-[#C9A227]/10">
                        <Star className="size-6 text-[#C9A227]" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-[#0A1628]">Create Special Tournament</h1>
                        <p className="text-[#64748B]">
                            Create a new Special tournament (official/ranked) at a specific geographic level
                        </p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Basic Info */}
                    <Card className="border-0 shadow-sm">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-[#0A1628]">
                                <Trophy className="size-5 text-[#004E86]" />
                                Basic Information
                            </CardTitle>
                            <CardDescription>
                                Tournament name and description
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">Tournament Name *</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="e.g., Kenya National Pool Championship 2026"
                                    className="border-[#E2E8F0] focus:border-[#004E86] focus:ring-[#004E86]"
                                />
                                {errors.name && (
                                    <p className="text-sm text-red-500">{errors.name}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description">Description</Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Describe the tournament, rules, prizes, etc."
                                    rows={4}
                                />
                                {errors.description && (
                                    <p className="text-sm text-red-500">{errors.description}</p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Geographic Scope */}
                    <Card className="border-0 shadow-sm">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-[#0A1628]">
                                <MapPin className="size-5 text-[#004E86]" />
                                Geographic Scope
                            </CardTitle>
                            <CardDescription>
                                Select the level and location for this tournament
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid md:grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label>Geographic Level *</Label>
                                    <Select
                                        value={selectedLevel?.toString() || ''}
                                        onValueChange={handleLevelChange}
                                    >
                                        <SelectTrigger className="border-[#E2E8F0]">
                                            <SelectValue placeholder="Select level" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {levels.map((level) => (
                                                <SelectItem key={level.value} value={level.value.toString()}>
                                                    {level.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label>Location *</Label>
                                    <Select
                                        value={data.geographic_scope_id}
                                        onValueChange={(value) => setData('geographic_scope_id', value)}
                                        disabled={!selectedLevel}
                                    >
                                        <SelectTrigger className="border-[#E2E8F0]">
                                            <SelectValue placeholder={selectedLevel ? "Select location" : "Select level first"} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {filteredUnits.map((unit) => (
                                                <SelectItem key={unit.id} value={unit.id.toString()}>
                                                    {unit.name}
                                                    <span className="text-[#64748B] ml-2 text-xs">
                                                        ({unit.full_path})
                                                    </span>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.geographic_scope_id && (
                                        <p className="text-sm text-red-500">{errors.geographic_scope_id}</p>
                                    )}
                                </div>
                            </div>

                            {levelSetting && (
                                <div className="p-4 rounded-lg bg-[#F8FAFC] border border-[#E2E8F0]">
                                    <div className="flex items-start gap-3">
                                        <Info className="size-5 text-[#004E86] shrink-0 mt-0.5" />
                                        <div>
                                            <p className="text-sm font-medium text-[#0A1628]">
                                                Default settings for {levelSetting.level_label} level:
                                            </p>
                                            <p className="text-sm text-[#64748B] mt-1">
                                                Race to {levelSetting.race_to} (Finals: Race to {levelSetting.finals_race_to}),
                                                {levelSetting.confirmation_hours}h confirmation window
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            )}

                            <Separator />

                            <div className="grid md:grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="venue_name">Venue Name</Label>
                                    <Input
                                        id="venue_name"
                                        value={data.venue_name}
                                        onChange={(e) => setData('venue_name', e.target.value)}
                                        placeholder="e.g., National Sports Center"
                                        className="border-[#E2E8F0]"
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="venue_address">Venue Address</Label>
                                    <Input
                                        id="venue_address"
                                        value={data.venue_address}
                                        onChange={(e) => setData('venue_address', e.target.value)}
                                        placeholder="Full address"
                                        className="border-[#E2E8F0]"
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Schedule */}
                    <Card className="border-0 shadow-sm">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-[#0A1628]">
                                <Calendar className="size-5 text-[#004E86]" />
                                Schedule
                            </CardTitle>
                            <CardDescription>
                                Set registration and tournament dates
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid md:grid-cols-3 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="registration_opens_at">Registration Opens</Label>
                                    <Input
                                        id="registration_opens_at"
                                        type="date"
                                        value={data.registration_opens_at}
                                        onChange={(e) => setData('registration_opens_at', e.target.value)}
                                        min={today}
                                        className="border-[#E2E8F0]"
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="registration_closes_at">Registration Closes *</Label>
                                    <Input
                                        id="registration_closes_at"
                                        type="date"
                                        value={data.registration_closes_at}
                                        onChange={(e) => setData('registration_closes_at', e.target.value)}
                                        min={data.registration_opens_at || today}
                                        className="border-[#E2E8F0]"
                                    />
                                    {errors.registration_closes_at && (
                                        <p className="text-sm text-red-500">{errors.registration_closes_at}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="starts_at">Tournament Starts *</Label>
                                    <Input
                                        id="starts_at"
                                        type="date"
                                        value={data.starts_at}
                                        onChange={(e) => setData('starts_at', e.target.value)}
                                        min={data.registration_closes_at || today}
                                        className="border-[#E2E8F0]"
                                    />
                                    {errors.starts_at && (
                                        <p className="text-sm text-red-500">{errors.starts_at}</p>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Match Settings */}
                    <Card className="border-0 shadow-sm">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-[#0A1628]">
                                <Target className="size-5 text-[#004E86]" />
                                Match Settings
                            </CardTitle>
                            <CardDescription>
                                Configure match rules (defaults based on geographic level)
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid md:grid-cols-3 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="race_to">Race To</Label>
                                    <Select
                                        value={data.race_to.toString()}
                                        onValueChange={(value) => setData('race_to', parseInt(value))}
                                    >
                                        <SelectTrigger className="border-[#E2E8F0]">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {[2, 3, 4, 5, 6, 7].map((n) => (
                                                <SelectItem key={n} value={n.toString()}>
                                                    Race to {n}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <p className="text-xs text-[#64748B]">First to X frames wins</p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="finals_race_to">Finals Race To</Label>
                                    <Select
                                        value={data.finals_race_to?.toString() || ''}
                                        onValueChange={(value) => setData('finals_race_to', value ? parseInt(value) : null)}
                                    >
                                        <SelectTrigger className="border-[#E2E8F0]">
                                            <SelectValue placeholder="Same as regular" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="">Same as regular</SelectItem>
                                            {[3, 4, 5, 6, 7, 8, 9].map((n) => (
                                                <SelectItem key={n} value={n.toString()}>
                                                    Race to {n}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <p className="text-xs text-[#64748B]">Optional: different for finals</p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="confirmation_hours">Confirmation Window</Label>
                                    <Select
                                        value={data.confirmation_hours.toString()}
                                        onValueChange={(value) => setData('confirmation_hours', parseInt(value))}
                                    >
                                        <SelectTrigger className="border-[#E2E8F0]">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {[12, 24, 48, 72].map((n) => (
                                                <SelectItem key={n} value={n.toString()}>
                                                    {n} hours
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <p className="text-xs text-[#64748B]">Time to confirm results</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Entry Fee */}
                    <Card className="border-0 shadow-sm">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-[#0A1628]">
                                <DollarSign className="size-5 text-[#004E86]" />
                                Entry Fee
                            </CardTitle>
                            <CardDescription>
                                Set an entry fee for participants (optional)
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid md:grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="entry_fee">Entry Fee Amount</Label>
                                    <div className="relative">
                                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-[#64748B]">
                                            {data.entry_fee_currency}
                                        </span>
                                        <Input
                                            id="entry_fee"
                                            type="number"
                                            value={data.entry_fee}
                                            onChange={(e) => setData('entry_fee', parseInt(e.target.value) || 0)}
                                            min={0}
                                            className="pl-14 border-[#E2E8F0]"
                                        />
                                    </div>
                                    <p className="text-xs text-[#64748B]">Set to 0 for free entry</p>
                                </div>

                                <div className="space-y-2">
                                    <Label>Currency</Label>
                                    <Select
                                        value={data.entry_fee_currency}
                                        onValueChange={(value) => setData('entry_fee_currency', value)}
                                    >
                                        <SelectTrigger className="border-[#E2E8F0]">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="NGN">NGN - Nigerian Naira</SelectItem>
                                            <SelectItem value="KES">KES - Kenyan Shilling</SelectItem>
                                            <SelectItem value="USD">USD - US Dollar</SelectItem>
                                            <SelectItem value="ZAR">ZAR - South African Rand</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Submit */}
                    <div className="flex items-center justify-end gap-4">
                        <Button variant="outline" asChild className="border-[#E2E8F0]">
                            <Link href="/admin/tournaments">Cancel</Link>
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="bg-[#004E86] hover:bg-[#003d6b]"
                        >
                            {processing ? 'Creating...' : 'Create Special Tournament'}
                        </Button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
