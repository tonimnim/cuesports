import { Link, usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { adminNavSections } from './admin-nav-items';
import { XIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface AdminSidebarProps {
    isMobileOpen: boolean;
    onCloseMobile: () => void;
}

export function AdminSidebar({ isMobileOpen, onCloseMobile }: AdminSidebarProps) {
    const { url } = usePage();

    const isActive = (href: string) => {
        if (href === '/admin') {
            return url === '/admin';
        }
        return url.startsWith(href);
    };

    return (
        <>
            {/* Mobile overlay */}
            {isMobileOpen && (
                <div
                    className="fixed inset-0 z-40 bg-black/50 lg:hidden"
                    onClick={onCloseMobile}
                />
            )}

            {/* Sidebar */}
            <aside
                className={cn(
                    'fixed inset-y-0 left-0 z-50 w-64 bg-[#0A1628] text-white transition-transform duration-300 lg:translate-x-0',
                    isMobileOpen ? 'translate-x-0' : '-translate-x-full'
                )}
            >
                {/* Header */}
                <div className="flex h-16 items-center justify-between px-4 border-b border-[#1E3A5F]">
                    <Link href="/admin" className="flex items-center gap-3">
                        <div className="flex size-9 items-center justify-center rounded-lg bg-[#004E86]">
                            <img src="/logo.svg" alt="CueSports" className="size-6" />
                        </div>
                        <div>
                            <span className="font-semibold text-sm">CueSports Africa</span>
                            <span className="block text-xs text-[#64748B]">Admin Panel</span>
                        </div>
                    </Link>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="lg:hidden text-white hover:bg-[#1E3A5F]"
                        onClick={onCloseMobile}
                    >
                        <XIcon className="size-5" />
                    </Button>
                </div>

                {/* Navigation */}
                <nav className="flex flex-col gap-1 p-3 overflow-y-auto h-[calc(100vh-8rem)]">
                    {adminNavSections.map((section, sectionIndex) => (
                        <div key={sectionIndex} className={cn(sectionIndex > 0 && 'mt-4')}>
                            {/* Section Title */}
                            {section.title && (
                                <h3 className="px-3 mb-2 text-xs font-semibold uppercase tracking-wider text-[#64748B]">
                                    {section.title}
                                </h3>
                            )}

                            {/* Section Items */}
                            <div className="space-y-1">
                                {section.items.map((item) => {
                                    const Icon = item.icon;
                                    const active = item.href ? isActive(item.href) : false;

                                    return (
                                        <Link
                                            key={item.href || item.title}
                                            href={item.href || '#'}
                                            onClick={onCloseMobile}
                                            className={cn(
                                                'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all',
                                                active
                                                    ? 'bg-[#004E86] text-white'
                                                    : 'text-[#94A3B8] hover:bg-[#1E3A5F] hover:text-white'
                                            )}
                                        >
                                            <Icon className={cn('size-5', active && 'text-[#C9A227]')} />
                                            <span>{item.title}</span>
                                            {item.badge !== undefined && item.badge > 0 && (
                                                <span className="ml-auto flex size-5 items-center justify-center rounded-full bg-red-500 text-xs font-bold text-white">
                                                    {item.badge}
                                                </span>
                                            )}
                                        </Link>
                                    );
                                })}
                            </div>
                        </div>
                    ))}
                </nav>

                {/* Footer */}
                <div className="absolute bottom-0 left-0 right-0 border-t border-[#1E3A5F] p-4">
                    <div className="flex items-center gap-2">
                        <div className="size-2 rounded-full bg-green-500"></div>
                        <p className="text-xs text-[#64748B]">
                            All systems operational
                        </p>
                    </div>
                </div>
            </aside>
        </>
    );
}
