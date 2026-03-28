import { usePage } from '@inertiajs/vue3';

export function usePermissions() {
    const page = usePage();

    const can = (permission) => {
        const user = page.props.auth?.user;
        
        if (!user) return false;
        
        // 1. Terminal Administrator Override
        // Admins bypass all specific permission checks to maintain system integrity.
        if (user.role === 'admin') return true;
        
        // 2. Strict Permission Validation
        return user.permissions?.includes(permission) || false;
    };

    return { can };
}
