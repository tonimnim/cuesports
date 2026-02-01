import { Head, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { AdminLayout } from '@/layouts/admin';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import {
    Globe,
    MapPin,
    ChevronRight,
    Plus,
    Edit,
    Trash2,
    Search,
    Building2,
    Map,
    Users,
    Loader2,
    Check,
    AlertCircle,
    Lock,
    Home,
    Layers,
} from 'lucide-react';

interface GeographicUnit {
    id: number;
    name: string;
    code: string;
    level: number;
    level_label?: string;
    parent_id: number | null;
    children_count: number;
}

interface Level {
    value: number;
    name: string;
    label: string;
}

interface AvailableCountry {
    name: string;
    code: string;
    phone_code: string;
}

interface CountryLabels {
    [countryCode: string]: {
        [level: number]: Level;
    };
}

interface Props {
    root: GeographicUnit | null;
    countries: GeographicUnit[];
    levels: Level[];
    countryLabels: CountryLabels;
    stats: {
        countries: number;
        regions: number;
        counties: number;
        communities: number;
    };
    availableCountries: AvailableCountry[];
}

// Level configuration with icons and colors
const levelConfig: Record<number, { icon: typeof Globe; color: string; bgColor: string; borderColor: string }> = {
    2: { icon: Globe, color: 'text-blue-600', bgColor: 'bg-blue-50', borderColor: 'border-blue-500' },
    3: { icon: Map, color: 'text-cyan-600', bgColor: 'bg-cyan-50', borderColor: 'border-cyan-500' },
    4: { icon: Building2, color: 'text-green-600', bgColor: 'bg-green-50', borderColor: 'border-green-500' },
    5: { icon: Layers, color: 'text-yellow-600', bgColor: 'bg-yellow-50', borderColor: 'border-yellow-500' },
    6: { icon: MapPin, color: 'text-orange-600', bgColor: 'bg-orange-50', borderColor: 'border-orange-500' },
    7: { icon: Users, color: 'text-red-600', bgColor: 'bg-red-50', borderColor: 'border-red-500' },
};

export default function GeographyIndex({ root, countries, levels, countryLabels, stats, availableCountries }: Props) {
    // Active level (which card is currently showing its list)
    const [activeLevel, setActiveLevel] = useState<number>(2); // Start with Countries (level 2)

    // Selection path - stores selected item for each level
    const [selections, setSelections] = useState<Record<number, GeographicUnit | null>>({
        2: null, // Country
        3: null, // Macro Region
        4: null, // Meso Region
        5: null, // Micro Region
        6: null, // Nano Region
        7: null, // Community
    });

    // Children data for each selected parent
    const [levelChildren, setLevelChildren] = useState<Record<number, GeographicUnit[]>>({
        2: countries, // Countries are pre-loaded
        3: [],
        4: [],
        5: [],
        6: [],
        7: [],
    });

    // Sync countries from props when they change (e.g., after adding a new country)
    useEffect(() => {
        setLevelChildren(prev => ({
            ...prev,
            2: countries,
        }));
    }, [countries]);

    // Loading states
    const [loadingLevel, setLoadingLevel] = useState<number | null>(null);
    const [searchQuery, setSearchQuery] = useState('');

    // Dialogs
    const [isAddOpen, setIsAddOpen] = useState(false);
    const [isEditOpen, setIsEditOpen] = useState(false);
    const [isDeleteOpen, setIsDeleteOpen] = useState(false);
    const [isAddCountryOpen, setIsAddCountryOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<GeographicUnit | null>(null);

    // Form states
    const [addName, setAddName] = useState('');
    const [addCode, setAddCode] = useState('');
    const [editName, setEditName] = useState('');
    const [editCode, setEditCode] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Country picker
    const [countrySearch, setCountrySearch] = useState('');
    const [selectedCountry, setSelectedCountry] = useState<AvailableCountry | null>(null);

    // Get the selected country code (if any)
    const getSelectedCountryCode = (): string | null => {
        const selectedCountry = selections[2];
        return selectedCountry?.code || null;
    };

    // Get level label - uses country-specific labels when a country is selected
    const getLevelLabel = (level: number): string => {
        const countryCode = getSelectedCountryCode();

        // If we have a selected country and country-specific labels exist
        if (countryCode && countryLabels[countryCode]) {
            const countryLevel = countryLabels[countryCode][level];
            if (countryLevel) {
                return countryLevel.label;
            }
        }

        // Fallback to default labels
        const found = levels.find(l => l.value === level);
        return found?.label || `Level ${level}`;
    };

    // Get counts for each level
    const getLevelCount = (level: number): number => {
        if (level === 2) return countries.length;
        return levelChildren[level]?.length || 0;
    };

    // Check if a level is accessible (has a parent selected or is level 2)
    const isLevelAccessible = (level: number): boolean => {
        if (level === 2) return true;
        return selections[level - 1] !== null;
    };

    // Check if a level has a selection
    const hasSelection = (level: number): boolean => {
        return selections[level] !== null;
    };

    // Get CSRF token from meta tag
    const getCsrfToken = (): string => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') || '' : '';
    };

    // Load children when a parent is selected
    const loadChildren = async (parentId: number, targetLevel: number) => {
        setLoadingLevel(targetLevel);
        try {
            const response = await fetch(`/admin/geography/${parentId}/children`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();
            setLevelChildren(prev => ({ ...prev, [targetLevel]: data.children || [] }));
        } catch (err) {
            console.error('Failed to load children:', err);
            setLevelChildren(prev => ({ ...prev, [targetLevel]: [] }));
        } finally {
            setLoadingLevel(null);
        }
    };

    // Handle item selection
    const handleSelect = async (item: GeographicUnit, level: number) => {
        // If clicking the already selected item, deselect it
        if (selections[level]?.id === item.id) {
            // Clear this selection and all children selections
            const newSelections = { ...selections };
            for (let l = level; l <= 7; l++) {
                newSelections[l] = null;
            }
            setSelections(newSelections);

            // Clear children data for levels below
            const newChildren = { ...levelChildren };
            for (let l = level + 1; l <= 7; l++) {
                newChildren[l] = [];
            }
            setLevelChildren(newChildren);

            return;
        }

        // Select the item
        const newSelections = { ...selections };
        newSelections[level] = item;

        // Clear selections for all levels below
        for (let l = level + 1; l <= 7; l++) {
            newSelections[l] = null;
        }
        setSelections(newSelections);

        // Clear children for levels below current + 1
        const newChildren = { ...levelChildren };
        for (let l = level + 2; l <= 7; l++) {
            newChildren[l] = [];
        }
        setLevelChildren(newChildren);

        // If not at community level, load children and activate next level
        if (level < 7) {
            await loadChildren(item.id, level + 1);
            setActiveLevel(level + 1);
        }

        setSearchQuery('');
    };

    // Handle level card click
    const handleLevelClick = (level: number) => {
        if (!isLevelAccessible(level)) return;
        setActiveLevel(level);
        setSearchQuery('');
    };

    // Get the current parent for adding new items
    const getCurrentParent = (): GeographicUnit | null => {
        if (activeLevel === 2) return root;
        return selections[activeLevel - 1];
    };

    // Filter items based on search
    const getFilteredItems = (): GeographicUnit[] => {
        const items = levelChildren[activeLevel] || [];
        if (!searchQuery) return items;
        return items.filter(item =>
            item.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            item.code?.toLowerCase().includes(searchQuery.toLowerCase())
        );
    };

    // Filter available countries based on search
    const filteredCountries = availableCountries.filter(country =>
        country.name.toLowerCase().includes(countrySearch.toLowerCase()) ||
        country.code.toLowerCase().includes(countrySearch.toLowerCase())
    );

    // Handle add item
    const handleAdd = () => {
        const parent = getCurrentParent();
        if (!parent) return;

        setIsSubmitting(true);
        router.post('/admin/geography', {
            name: addName,
            code: addCode,
            parent_id: parent.id,
        }, {
            onSuccess: () => {
                setIsAddOpen(false);
                setAddName('');
                setAddCode('');
                // Reload the current level's children
                loadChildren(parent.id, activeLevel);
            },
            onFinish: () => setIsSubmitting(false),
        });
    };

    // Handle add country
    const handleAddCountry = () => {
        if (!selectedCountry) return;

        setIsSubmitting(true);
        router.post('/admin/geography/add-country', {
            country_code: selectedCountry.code,
        }, {
            preserveState: false, // Force fresh data from server
            preserveScroll: true,
            onSuccess: () => {
                setIsAddCountryOpen(false);
                setSelectedCountry(null);
                setCountrySearch('');
                // Reset to countries level to see the new country
                setActiveLevel(2);
            },
            onFinish: () => setIsSubmitting(false),
        });
    };

    // Handle edit
    const handleEdit = () => {
        if (!editingItem) return;

        setIsSubmitting(true);
        router.put(`/admin/geography/${editingItem.id}`, {
            name: editName,
            code: editCode,
        }, {
            onSuccess: () => {
                setIsEditOpen(false);
                setEditingItem(null);
                // Reload the current level
                const parent = getCurrentParent();
                if (parent) {
                    loadChildren(parent.id, activeLevel);
                }
            },
            onFinish: () => setIsSubmitting(false),
        });
    };

    // Handle delete
    const handleDelete = () => {
        if (!editingItem) return;

        setIsSubmitting(true);
        router.delete(`/admin/geography/${editingItem.id}`, {
            onSuccess: () => {
                setIsDeleteOpen(false);
                // If the deleted item was selected, clear selections from this level down
                if (selections[activeLevel]?.id === editingItem.id) {
                    const newSelections = { ...selections };
                    for (let l = activeLevel; l <= 7; l++) {
                        newSelections[l] = null;
                    }
                    setSelections(newSelections);
                }
                setEditingItem(null);
                // Reload the current level
                const parent = getCurrentParent();
                if (parent) {
                    loadChildren(parent.id, activeLevel);
                }
            },
            onFinish: () => setIsSubmitting(false),
        });
    };

    // Open edit dialog
    const openEdit = (item: GeographicUnit) => {
        setEditingItem(item);
        setEditName(item.name);
        setEditCode(item.code || '');
        setIsEditOpen(true);
    };

    // Open delete dialog
    const openDelete = (item: GeographicUnit) => {
        setEditingItem(item);
        setIsDeleteOpen(true);
    };

    // Build breadcrumb path
    const getBreadcrumb = (): { level: number; item: GeographicUnit }[] => {
        const path: { level: number; item: GeographicUnit }[] = [];
        for (let l = 2; l <= 7; l++) {
            if (selections[l]) {
                path.push({ level: l, item: selections[l]! });
            }
        }
        return path;
    };

    const breadcrumb = getBreadcrumb();
    const filteredItems = getFilteredItems();
    const config = levelConfig[activeLevel];
    const Icon = config?.icon || MapPin;

    return (
        <AdminLayout>
            <Head title="Geography - Admin" />

            <div className="max-w-7xl mx-auto space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-[#0A1628]">Geography Builder</h1>
                    <p className="text-[#64748B]">
                        Build your geographic hierarchy from countries down to communities
                    </p>
                </div>

                {/* Level Cards - Horizontal Flow */}
                <div className="grid grid-cols-6 gap-3">
                    {[2, 3, 4, 5, 6, 7].map((level) => {
                        const lvlConfig = levelConfig[level];
                        const LvlIcon = lvlConfig?.icon || MapPin;
                        const isAccessible = isLevelAccessible(level);
                        const isActive = activeLevel === level;
                        const selected = selections[level];
                        const count = getLevelCount(level);

                        return (
                            <button
                                key={level}
                                onClick={() => handleLevelClick(level)}
                                disabled={!isAccessible}
                                className={`
                                    relative p-4 rounded-xl border-2 text-left transition-all duration-200
                                    ${isActive
                                        ? `${lvlConfig.bgColor} ${lvlConfig.borderColor} shadow-md scale-[1.02]`
                                        : isAccessible
                                            ? 'bg-white border-gray-200 hover:border-gray-300 hover:shadow-sm'
                                            : 'bg-gray-50 border-gray-100 opacity-50 cursor-not-allowed'
                                    }
                                `}
                            >
                                <div className="flex items-center justify-between mb-2">
                                    <LvlIcon className={`size-5 ${isActive ? lvlConfig.color : 'text-gray-400'}`} />
                                    {!isAccessible && <Lock className="size-4 text-gray-300" />}
                                    {selected && <Check className={`size-4 ${lvlConfig.color}`} />}
                                </div>
                                <p className={`text-xs font-medium ${isActive ? lvlConfig.color : 'text-gray-500'}`}>
                                    {getLevelLabel(level)}
                                </p>
                                <p className={`text-lg font-bold ${isActive ? 'text-gray-900' : 'text-gray-600'}`}>
                                    {count}
                                </p>
                                {selected && (
                                    <p className="text-xs text-gray-500 truncate mt-1" title={selected.name}>
                                        {selected.name}
                                    </p>
                                )}
                            </button>
                        );
                    })}
                </div>

                {/* Breadcrumb Path */}
                {breadcrumb.length > 0 && (
                    <div className="flex items-center gap-2 px-4 py-3 bg-gray-50 rounded-lg">
                        <Home className="size-4 text-gray-400" />
                        <span className="text-sm text-gray-500">Africa</span>
                        {breadcrumb.map(({ level, item }) => (
                            <div key={level} className="flex items-center gap-2">
                                <ChevronRight className="size-4 text-gray-300" />
                                <button
                                    onClick={() => handleLevelClick(level)}
                                    className={`
                                        text-sm px-2 py-1 rounded transition-colors
                                        ${activeLevel === level
                                            ? `${levelConfig[level].bgColor} ${levelConfig[level].color} font-medium`
                                            : 'text-gray-600 hover:bg-gray-100'
                                        }
                                    `}
                                >
                                    {item.name}
                                </button>
                            </div>
                        ))}
                    </div>
                )}

                {/* Active Level Content */}
                <Card className="border-0 shadow-sm">
                    <CardContent className="p-6">
                        {/* Level Header */}
                        <div className="flex items-center justify-between mb-6">
                            <div className="flex items-center gap-3">
                                <div className={`p-2 rounded-lg ${config?.bgColor}`}>
                                    <Icon className={`size-5 ${config?.color}`} />
                                </div>
                                <div>
                                    <h2 className="text-lg font-semibold text-gray-900">
                                        {getLevelLabel(activeLevel)}s
                                        {selections[activeLevel - 1] && activeLevel > 2 && (
                                            <span className="text-gray-400 font-normal"> in {selections[activeLevel - 1]?.name}</span>
                                        )}
                                    </h2>
                                    <p className="text-sm text-gray-500">
                                        {filteredItems.length} {getLevelLabel(activeLevel).toLowerCase()}{filteredItems.length !== 1 ? 's' : ''}
                                    </p>
                                </div>
                            </div>

                            {/* Add Button */}
                            {activeLevel === 2 ? (
                                availableCountries.length > 0 && (
                                    <Button
                                        onClick={() => setIsAddCountryOpen(true)}
                                        className="bg-[#004E86] hover:bg-[#003D6B]"
                                    >
                                        <Plus className="size-4 mr-2" />
                                        Add Country
                                    </Button>
                                )
                            ) : (
                                getCurrentParent() && (
                                    <Button
                                        onClick={() => setIsAddOpen(true)}
                                        className="bg-[#004E86] hover:bg-[#003D6B]"
                                    >
                                        <Plus className="size-4 mr-2" />
                                        Add {getLevelLabel(activeLevel)}
                                    </Button>
                                )
                            )}
                        </div>

                        {/* Search Bar */}
                        <div className="relative mb-6">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-gray-400" />
                            <Input
                                placeholder={`Search ${getLevelLabel(activeLevel).toLowerCase()}s...`}
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="pl-10 border-gray-200"
                            />
                        </div>

                        {/* Items Grid */}
                        {loadingLevel === activeLevel ? (
                            <div className="flex items-center justify-center py-12">
                                <Loader2 className="size-8 animate-spin text-gray-400" />
                            </div>
                        ) : filteredItems.length > 0 ? (
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                {filteredItems.map((item, index) => {
                                    const isSelected = selections[activeLevel]?.id === item.id;
                                    const itemConfig = levelConfig[activeLevel];

                                    return (
                                        <div
                                            key={item.id}
                                            className={`
                                                group relative p-4 rounded-xl border-2 transition-all duration-200
                                                animate-in fade-in slide-in-from-bottom-2
                                                ${isSelected
                                                    ? `${itemConfig.bgColor} ${itemConfig.borderColor}`
                                                    : 'bg-white border-gray-200 hover:border-gray-300 hover:shadow-md'
                                                }
                                            `}
                                            style={{ animationDelay: `${index * 30}ms` }}
                                        >
                                            <button
                                                onClick={() => handleSelect(item, activeLevel)}
                                                className="w-full text-left"
                                            >
                                                <div className="flex items-start justify-between">
                                                    <div className="flex-1 min-w-0">
                                                        <p className={`font-semibold truncate ${isSelected ? 'text-gray-900' : 'text-gray-700'}`}>
                                                            {item.name}
                                                        </p>
                                                        {item.code && (
                                                            <p className="text-xs text-gray-500 mt-0.5">
                                                                Code: {item.code}
                                                            </p>
                                                        )}
                                                    </div>
                                                    {isSelected && (
                                                        <Check className={`size-5 ${itemConfig.color} shrink-0 ml-2`} />
                                                    )}
                                                </div>

                                                {item.children_count > 0 && activeLevel < 7 && (
                                                    <div className="mt-3 flex items-center gap-1 text-xs text-gray-500">
                                                        <span>{item.children_count}</span>
                                                        <span>{getLevelLabel(activeLevel + 1).toLowerCase()}{item.children_count !== 1 ? 's' : ''}</span>
                                                        <ChevronRight className="size-3" />
                                                    </div>
                                                )}
                                            </button>

                                            {/* Edit/Delete buttons - show on hover */}
                                            {activeLevel > 2 && (
                                                <div className={`
                                                    absolute top-2 right-2 flex gap-1 transition-opacity
                                                    ${isSelected ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'}
                                                `}>
                                                    <button
                                                        onClick={(e) => { e.stopPropagation(); openEdit(item); }}
                                                        className="p-1.5 rounded-md bg-white shadow-sm border border-gray-200 hover:bg-gray-50 transition-colors"
                                                    >
                                                        <Edit className="size-3.5 text-gray-500" />
                                                    </button>
                                                    {item.children_count === 0 && (
                                                        <button
                                                            onClick={(e) => { e.stopPropagation(); openDelete(item); }}
                                                            className="p-1.5 rounded-md bg-white shadow-sm border border-gray-200 hover:bg-red-50 transition-colors"
                                                        >
                                                            <Trash2 className="size-3.5 text-red-500" />
                                                        </button>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <div className="text-center py-12">
                                <div className={`w-16 h-16 mx-auto mb-4 rounded-full ${config?.bgColor} flex items-center justify-center`}>
                                    <Icon className={`size-8 ${config?.color} opacity-50`} />
                                </div>
                                <p className="text-gray-500 font-medium">
                                    No {getLevelLabel(activeLevel).toLowerCase()}s yet
                                </p>
                                <p className="text-sm text-gray-400 mt-1">
                                    {activeLevel === 2
                                        ? 'Add countries to start building your geography'
                                        : `Add ${getLevelLabel(activeLevel).toLowerCase()}s to ${selections[activeLevel - 1]?.name || 'continue'}`
                                    }
                                </p>
                            </div>
                        )}

                        {/* Community Level Info */}
                        {activeLevel === 7 && selections[6] && (
                            <div className="mt-6 p-4 bg-green-50 border border-green-200 rounded-xl">
                                <div className="flex items-start gap-3">
                                    <Check className="size-5 text-green-600 mt-0.5" />
                                    <div>
                                        <p className="font-medium text-green-800">Community Level (Final)</p>
                                        <p className="text-sm text-green-700">
                                            This is the lowest level. Players register at this level and
                                            community tournaments are held here.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Level Legend */}
                <div className="flex items-center justify-center gap-6 flex-wrap py-2">
                    {[2, 3, 4, 5, 6, 7].map(level => {
                        const lvlConfig = levelConfig[level];
                        return (
                            <div key={level} className="flex items-center gap-2">
                                <span className={`w-3 h-3 rounded-full ${lvlConfig.bgColor} border-2 ${lvlConfig.borderColor}`} />
                                <span className="text-sm text-gray-500">{getLevelLabel(level)}</span>
                            </div>
                        );
                    })}
                </div>
            </div>

            {/* Add Item Dialog */}
            <Dialog open={isAddOpen} onOpenChange={setIsAddOpen}>
                <DialogContent className="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>Add {getLevelLabel(activeLevel)}</DialogTitle>
                        <DialogDescription>
                            Add a new {getLevelLabel(activeLevel).toLowerCase()} to {selections[activeLevel - 1]?.name || 'the platform'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4 py-4">
                        <div className="space-y-2">
                            <Label htmlFor="add-name">Name *</Label>
                            <Input
                                id="add-name"
                                value={addName}
                                onChange={(e) => setAddName(e.target.value)}
                                placeholder={`Enter ${getLevelLabel(activeLevel).toLowerCase()} name`}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="add-code">Code (Optional)</Label>
                            <Input
                                id="add-code"
                                value={addCode}
                                onChange={(e) => setAddCode(e.target.value)}
                                placeholder="Short code (auto-generated if empty)"
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsAddOpen(false)}>
                            Cancel
                        </Button>
                        <Button
                            onClick={handleAdd}
                            disabled={!addName || isSubmitting}
                            className="bg-[#004E86] hover:bg-[#003D6B]"
                        >
                            {isSubmitting ? <Loader2 className="size-4 mr-2 animate-spin" /> : <Plus className="size-4 mr-2" />}
                            Add
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Edit Dialog */}
            <Dialog open={isEditOpen} onOpenChange={setIsEditOpen}>
                <DialogContent className="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>Edit {getLevelLabel(activeLevel)}</DialogTitle>
                        <DialogDescription>
                            Update the details for {editingItem?.name}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4 py-4">
                        <div className="space-y-2">
                            <Label htmlFor="edit-name">Name *</Label>
                            <Input
                                id="edit-name"
                                value={editName}
                                onChange={(e) => setEditName(e.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="edit-code">Code</Label>
                            <Input
                                id="edit-code"
                                value={editCode}
                                onChange={(e) => setEditCode(e.target.value)}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsEditOpen(false)}>
                            Cancel
                        </Button>
                        <Button
                            onClick={handleEdit}
                            disabled={!editName || isSubmitting}
                            className="bg-[#004E86] hover:bg-[#003D6B]"
                        >
                            {isSubmitting ? 'Saving...' : 'Save Changes'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation */}
            <AlertDialog open={isDeleteOpen} onOpenChange={setIsDeleteOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle className="flex items-center gap-2">
                            <AlertCircle className="size-5 text-red-600" />
                            Delete {getLevelLabel(activeLevel)}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to delete <strong>{editingItem?.name}</strong>?
                            This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleDelete}
                            className="bg-red-600 hover:bg-red-700"
                            disabled={isSubmitting}
                        >
                            {isSubmitting ? 'Deleting...' : 'Delete'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Add Country Dialog */}
            <Dialog open={isAddCountryOpen} onOpenChange={setIsAddCountryOpen}>
                <DialogContent className="sm:max-w-[500px]">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Globe className="size-5 text-[#004E86]" />
                            Add Country to Platform
                        </DialogTitle>
                        <DialogDescription>
                            Select an African country to add. Once added, you can build out its regions.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="py-4 space-y-4">
                        {/* Search */}
                        <div className="relative">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-gray-400" />
                            <Input
                                placeholder="Search countries..."
                                value={countrySearch}
                                onChange={(e) => setCountrySearch(e.target.value)}
                                className="pl-10"
                            />
                        </div>

                        {/* Country List */}
                        <div className="border rounded-lg max-h-[300px] overflow-y-auto">
                            {filteredCountries.length > 0 ? (
                                <div className="divide-y">
                                    {filteredCountries.map((country, index) => (
                                        <button
                                            key={country.code}
                                            type="button"
                                            onClick={() => setSelectedCountry(country)}
                                            className={`
                                                w-full flex items-center gap-3 px-4 py-3 text-left transition-all
                                                ${selectedCountry?.code === country.code
                                                    ? 'bg-[#004E86] text-white'
                                                    : 'hover:bg-gray-50'
                                                }
                                                animate-in fade-in
                                            `}
                                            style={{ animationDelay: `${index * 20}ms` }}
                                        >
                                            <span className={`
                                                w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold
                                                ${selectedCountry?.code === country.code
                                                    ? 'bg-white/20 text-white'
                                                    : 'bg-blue-50 text-blue-600'
                                                }
                                            `}>
                                                {country.code}
                                            </span>
                                            <div className="flex-1">
                                                <p className={`font-medium ${selectedCountry?.code === country.code ? 'text-white' : 'text-gray-900'}`}>
                                                    {country.name}
                                                </p>
                                                <p className={`text-xs ${selectedCountry?.code === country.code ? 'text-white/70' : 'text-gray-500'}`}>
                                                    {country.phone_code}
                                                </p>
                                            </div>
                                            {selectedCountry?.code === country.code && (
                                                <Check className="size-5" />
                                            )}
                                        </button>
                                    ))}
                                </div>
                            ) : (
                                <div className="py-8 text-center text-gray-500">
                                    {availableCountries.length === 0 ? (
                                        <>
                                            <Check className="size-8 mx-auto mb-2 text-green-500" />
                                            <p className="font-medium">All countries added!</p>
                                        </>
                                    ) : (
                                        <p>No countries match "{countrySearch}"</p>
                                    )}
                                </div>
                            )}
                        </div>

                        {/* Selected Preview */}
                        {selectedCountry && (
                            <div className="p-4 bg-blue-50 border border-blue-200 rounded-lg animate-in fade-in">
                                <div className="flex items-center gap-3">
                                    <div className="w-10 h-10 rounded-full bg-blue-500 text-white flex items-center justify-center font-bold text-sm">
                                        {selectedCountry.code}
                                    </div>
                                    <div>
                                        <p className="font-semibold text-gray-900">{selectedCountry.name}</p>
                                        <p className="text-sm text-gray-500">
                                            Code: {selectedCountry.code} | {selectedCountry.phone_code}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => { setIsAddCountryOpen(false); setSelectedCountry(null); }}>
                            Cancel
                        </Button>
                        <Button
                            onClick={handleAddCountry}
                            disabled={!selectedCountry || isSubmitting}
                            className="bg-[#004E86] hover:bg-[#003D6B]"
                        >
                            {isSubmitting ? <Loader2 className="size-4 mr-2 animate-spin" /> : <Plus className="size-4 mr-2" />}
                            Add {selectedCountry?.name || 'Country'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}
