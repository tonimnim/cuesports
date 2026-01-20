import { useState, useCallback } from 'react';

export function useSidebar() {
    const [isCollapsed, setIsCollapsed] = useState(false);
    const [isMobileOpen, setIsMobileOpen] = useState(false);

    const toggle = useCallback(() => setIsCollapsed(prev => !prev), []);
    const toggleMobile = useCallback(() => setIsMobileOpen(prev => !prev), []);
    const closeMobile = useCallback(() => setIsMobileOpen(false), []);

    return {
        isCollapsed,
        isMobileOpen,
        toggle,
        toggleMobile,
        closeMobile,
    };
}
