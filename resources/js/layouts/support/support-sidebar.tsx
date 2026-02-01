import { Link, usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { supportNavItems } from './support-nav-items';
import { HeadphonesIcon, XIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface SupportSidebarProps {
    isMobileOpen: boolean;
    onCloseMobile: () => void;
}

export function SupportSidebar({ isMobileOpen, onCloseMobile }: SupportSidebarProps) {
    const { url } = usePage();

    const isActive = (href: string) => {
        if (href === '/support') {
            return url === '/support';
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
                    'fixed inset-y-0 left-0 z-50 w-64 bg-[#004E86] text-white transition-transform duration-300 lg:translate-x-0',
                    isMobileOpen ? 'translate-x-0' : '-translate-x-full'
                )}
            >
                {/* Header */}
                <div className="flex h-16 items-center justify-between px-4 border-b border-[#003D6B]">
                    <Link href="/support" className="flex items-center gap-2">
                        <div className="flex size-8 items-center justify-center rounded-lg bg-[#C9A227]">
                            <HeadphonesIcon className="size-5 text-[#0A1628]" />
                        </div>
                        <span className="font-semibold text-lg">Support</span>
                    </Link>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        className="lg:hidden text-white hover:bg-[#003D6B]"
                        onClick={onCloseMobile}
                    >
                        <XIcon className="size-5" />
                    </Button>
                </div>

                {/* Navigation */}
                <nav className="flex flex-col gap-1 p-4">
                    {supportNavItems.map((item) => {
                        const Icon = item.icon;
                        const active = isActive(item.href);

                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                onClick={onCloseMobile}
                                className={cn(
                                    'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                                    active
                                        ? 'bg-[#C9A227] text-[#0A1628]'
                                        : 'text-white/80 hover:bg-[#003D6B] hover:text-white'
                                )}
                            >
                                <Icon className="size-5" />
                                <span>{item.title}</span>
                                {item.badge !== undefined && item.badge > 0 && (
                                    <span className="ml-auto flex size-5 items-center justify-center rounded-full bg-red-500 text-xs font-bold text-white">
                                        {item.badge}
                                    </span>
                                )}
                            </Link>
                        );
                    })}
                </nav>

                {/* Footer */}
                <div className="absolute bottom-0 left-0 right-0 border-t border-[#003D6B] p-4">
                    <p className="text-xs text-white/50">
                        CueSports Africa Support
                    </p>
                </div>
            </aside>
        </>
    );
}
