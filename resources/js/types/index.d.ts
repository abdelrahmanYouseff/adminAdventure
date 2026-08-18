import type { LucideIcon } from 'lucide-vue-next';
import type { Config } from 'ziggy-js';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href?: string;
    icon?: LucideIcon;
    isActive?: boolean;
    badge?: number;
    children?: NavItem[];
}

export type StaffRole = 'admin' | 'general_manager' | 'manager' | 'accounts' | 'workers_manager' | 'worker' | 'warehouse_keeper';

export type AppPageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: { location: string };
    sidebarOpen: boolean;
    impersonation?: {
        active: boolean;
        admin_name: string | null;
        as_name: string | null;
        as_role: string | null;
    } | null;
    sidebarBadges?: {
        work_orders: number;
        returns: number;
        payment_receipts: number;
    };
};

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    role?: StaffRole | null;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
