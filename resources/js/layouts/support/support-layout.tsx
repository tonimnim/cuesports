import { PropsWithChildren, useEffect } from 'react';
import { useSidebar, useAuth } from '@/hooks';
import { SupportSidebar } from './support-sidebar';
import { SupportHeader } from './support-header';

interface SupportLayoutProps extends PropsWithChildren {
    title?: string;
}

export function SupportLayout({ children, title }: SupportLayoutProps) {
    const { isMobileOpen, toggleMobile, closeMobile } = useSidebar();
    const { flash } = useAuth();

    // Handle flash messages
    useEffect(() => {
        if (flash.success) {
            // You can integrate with a toast library here
            console.log('Success:', flash.success);
        }
        if (flash.error) {
            console.error('Error:', flash.error);
        }
    }, [flash]);

    return (
        <div className="min-h-screen bg-slate-50">
            <SupportSidebar isMobileOpen={isMobileOpen} onCloseMobile={closeMobile} />

            {/* Main content area */}
            <div className="lg:pl-64">
                <SupportHeader onMenuClick={toggleMobile} />

                {/* Page content */}
                <main className="p-4 lg:p-6">
                    {title && (
                        <div className="mb-6">
                            <h2 className="text-2xl font-bold tracking-tight text-slate-900">
                                {title}
                            </h2>
                        </div>
                    )}
                    {children}
                </main>
            </div>
        </div>
    );
}
