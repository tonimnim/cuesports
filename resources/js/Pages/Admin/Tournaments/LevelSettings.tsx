import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { AdminLayout } from '@/layouts/admin';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    ArrowLeft,
    Settings,
    Target,
    Save,
    Info,
} from 'lucide-react';

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
    settings: LevelSetting[];
    levels: Level[];
}

export default function LevelSettings({ settings: initialSettings, levels }: Props) {
    const [settings, setSettings] = useState<LevelSetting[]>(initialSettings);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [hasChanges, setHasChanges] = useState(false);

    const updateSetting = (levelValue: number, field: keyof LevelSetting, value: number) => {
        setSettings(prev =>
            prev.map(s =>
                s.geographic_level === levelValue
                    ? { ...s, [field]: value }
                    : s
            )
        );
        setHasChanges(true);
    };

    const handleSubmit = () => {
        setIsSubmitting(true);
        router.put('/admin/tournaments/level-settings', {
            settings: settings.map(s => ({
                geographic_level: s.geographic_level,
                race_to: s.race_to,
                finals_race_to: s.finals_race_to,
                confirmation_hours: s.confirmation_hours,
            })),
        }, {
            preserveScroll: true,
            onFinish: () => {
                setIsSubmitting(false);
                setHasChanges(false);
            },
        });
    };

    // Sort settings by level (higher levels first for hierarchy)
    const sortedSettings = [...settings].sort((a, b) => a.geographic_level - b.geographic_level);

    return (
        <AdminLayout>
            <Head title="Tournament Level Settings" />

            <div className="max-w-4xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="icon" asChild className="hover:bg-[#F1F5F9]">
                            <Link href="/admin/tournaments">
                                <ArrowLeft className="size-5" />
                            </Link>
                        </Button>
                        <div className="flex size-12 items-center justify-center rounded-lg bg-[#004E86]/10">
                            <Settings className="size-6 text-[#004E86]" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight text-[#0A1628]">
                                Tournament Level Settings
                            </h1>
                            <p className="text-[#64748B]">
                                Configure default match settings for each geographic level
                            </p>
                        </div>
                    </div>
                    <Button
                        onClick={handleSubmit}
                        disabled={isSubmitting || !hasChanges}
                        className="bg-[#004E86] hover:bg-[#003d6b]"
                    >
                        <Save className="size-4 mr-2" />
                        {isSubmitting ? 'Saving...' : 'Save Changes'}
                    </Button>
                </div>

                {/* Info Card */}
                <Card className="border-[#004E86]/20 bg-[#004E86]/5">
                    <CardContent className="pt-6">
                        <div className="flex items-start gap-4">
                            <div className="size-10 rounded-lg bg-[#004E86]/10 flex items-center justify-center shrink-0">
                                <Info className="size-5 text-[#004E86]" />
                            </div>
                            <div>
                                <h3 className="font-semibold text-[#0A1628]">How Level Settings Work</h3>
                                <p className="text-sm text-[#64748B] mt-1">
                                    These settings define the default match configuration for Special tournaments
                                    at each geographic level. When creating a Special tournament, these values
                                    are applied automatically based on the tournament's geographic scope.
                                    Higher-level tournaments (Continental, National) typically have longer matches.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Settings Table */}
                <Card className="border-0 shadow-sm">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-[#0A1628]">
                            <Target className="size-5 text-[#004E86]" />
                            Default Settings by Level
                        </CardTitle>
                        <CardDescription>
                            Set the default race_to values and confirmation window for each level
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-[#F8FAFC]">
                                    <TableHead className="font-semibold text-[#0A1628] w-[200px]">
                                        Geographic Level
                                    </TableHead>
                                    <TableHead className="font-semibold text-[#0A1628]">
                                        Race To
                                    </TableHead>
                                    <TableHead className="font-semibold text-[#0A1628]">
                                        Finals Race To
                                    </TableHead>
                                    <TableHead className="font-semibold text-[#0A1628]">
                                        Confirmation Window
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {sortedSettings.map((setting) => (
                                    <TableRow key={setting.geographic_level} className="hover:bg-[#F8FAFC]">
                                        <TableCell className="font-medium text-[#0A1628]">
                                            {setting.level_label}
                                        </TableCell>
                                        <TableCell>
                                            <Select
                                                value={setting.race_to.toString()}
                                                onValueChange={(value) =>
                                                    updateSetting(setting.geographic_level, 'race_to', parseInt(value))
                                                }
                                            >
                                                <SelectTrigger className="w-[120px] border-[#E2E8F0]">
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
                                        </TableCell>
                                        <TableCell>
                                            <Select
                                                value={setting.finals_race_to.toString()}
                                                onValueChange={(value) =>
                                                    updateSetting(setting.geographic_level, 'finals_race_to', parseInt(value))
                                                }
                                            >
                                                <SelectTrigger className="w-[120px] border-[#E2E8F0]">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {[2, 3, 4, 5, 6, 7, 8, 9].map((n) => (
                                                        <SelectItem key={n} value={n.toString()}>
                                                            Race to {n}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </TableCell>
                                        <TableCell>
                                            <Select
                                                value={setting.confirmation_hours.toString()}
                                                onValueChange={(value) =>
                                                    updateSetting(setting.geographic_level, 'confirmation_hours', parseInt(value))
                                                }
                                            >
                                                <SelectTrigger className="w-[120px] border-[#E2E8F0]">
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
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        <div className="mt-4 p-4 rounded-lg bg-[#F8FAFC] border border-[#E2E8F0]">
                            <p className="text-sm text-[#64748B]">
                                <strong className="text-[#0A1628]">Note:</strong> Changes will only affect
                                new Special tournaments. Existing tournaments will keep their current settings.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                {/* Description of Levels */}
                <Card className="border-0 shadow-sm">
                    <CardHeader>
                        <CardTitle className="text-[#0A1628]">Geographic Level Hierarchy</CardTitle>
                        <CardDescription>
                            Understanding the tournament level system
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid md:grid-cols-2 gap-4">
                            <div className="space-y-3">
                                <div className="flex items-center gap-3 p-3 rounded-lg bg-[#F8FAFC]">
                                    <div className="w-8 h-8 rounded-full bg-[#C9A227]/20 flex items-center justify-center text-xs font-bold text-[#C9A227]">
                                        1
                                    </div>
                                    <div>
                                        <p className="font-medium text-[#0A1628]">Continental (ROOT)</p>
                                        <p className="text-xs text-[#64748B]">Highest level - Africa-wide</p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-3 p-3 rounded-lg bg-[#F8FAFC]">
                                    <div className="w-8 h-8 rounded-full bg-[#004E86]/20 flex items-center justify-center text-xs font-bold text-[#004E86]">
                                        2
                                    </div>
                                    <div>
                                        <p className="font-medium text-[#0A1628]">National</p>
                                        <p className="text-xs text-[#64748B]">Country level tournaments</p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-3 p-3 rounded-lg bg-[#F8FAFC]">
                                    <div className="w-8 h-8 rounded-full bg-[#004E86]/10 flex items-center justify-center text-xs font-bold text-[#004E86]">
                                        3
                                    </div>
                                    <div>
                                        <p className="font-medium text-[#0A1628]">Regional (MACRO)</p>
                                        <p className="text-xs text-[#64748B]">Large regions within a country</p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-3 p-3 rounded-lg bg-[#F8FAFC]">
                                    <div className="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-xs font-bold text-gray-600">
                                        4
                                    </div>
                                    <div>
                                        <p className="font-medium text-[#0A1628]">County/District (MESO)</p>
                                        <p className="text-xs text-[#64748B]">County or district level</p>
                                    </div>
                                </div>
                            </div>
                            <div className="space-y-3">
                                <div className="flex items-center gap-3 p-3 rounded-lg bg-[#F8FAFC]">
                                    <div className="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-xs font-bold text-gray-600">
                                        5
                                    </div>
                                    <div>
                                        <p className="font-medium text-[#0A1628]">Constituency (MICRO)</p>
                                        <p className="text-xs text-[#64748B]">Sub-county level</p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-3 p-3 rounded-lg bg-[#F8FAFC]">
                                    <div className="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-xs font-bold text-gray-600">
                                        6
                                    </div>
                                    <div>
                                        <p className="font-medium text-[#0A1628]">Ward (NANO)</p>
                                        <p className="text-xs text-[#64748B]">Ward level</p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-3 p-3 rounded-lg bg-[#F8FAFC]">
                                    <div className="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-xs font-bold text-gray-600">
                                        7
                                    </div>
                                    <div>
                                        <p className="font-medium text-[#0A1628]">Community (ATOMIC)</p>
                                        <p className="text-xs text-[#64748B]">Smallest local unit</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
