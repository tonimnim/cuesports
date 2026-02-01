import { PropsWithChildren, useEffect } from 'react';
import { useSidebar, useAuth } from '@/hooks';
import { AdminSidebar } from './admin-sidebar';
import { AdminHeader } from './admin-header';

interface AdminLayoutProps extends PropsWithChildren {
    title?: string;
}

export function AdminLayout({ children, title }: AdminLayoutProps) {
    const { isMobileOpen, toggleMobile, closeMobile } = useSidebar();
    const { flash } = useAuth();

    // Handle flash messages
    useEffect(() => {
        if (flash.success) {
            console.log('Success:', flash.success);
        }
        if (flash.error) {
            console.error('Error:', flash.error);
        }
    }, [flash]);

    return (
        <div className="min-h-screen bg-slate-50">
            <AdminSidebar isMobileOpen={isMobileOpen} onCloseMobile={closeMobile} />

            {/* Main content area */}
            <div className="lg:pl-64">
                <AdminHeader onMenuClick={toggleMobile} />

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
