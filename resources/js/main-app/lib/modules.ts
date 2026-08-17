import {
    BarChart3,
    Building2,
    FileSpreadsheet,
    FileText,
    HardHat,
    LayoutGrid,
    MessageCircle,
    Package,
    Receipt,
    ShieldCheck,
    ShoppingBag,
    ShoppingCart,
    Tags,
    Undo2,
    UserCog,
    Users,
    type LucideIcon,
} from 'lucide-vue-next';

export type ModuleTone =
    | 'teal'
    | 'sky'
    | 'amber'
    | 'violet'
    | 'indigo'
    | 'fuchsia'
    | 'blue'
    | 'emerald'
    | 'orange'
    | 'cyan'
    | 'slate'
    | 'lime'
    | 'rose'
    | 'green';

export interface AppModule {
    key: string;
    title: string;
    description: string;
    icon: string;
    tone: ModuleTone;
    desktop_path: string;
    roles: string[];
}

const iconMap: Record<string, LucideIcon> = {
    'layout-grid': LayoutGrid,
    'shopping-bag': ShoppingBag,
    'undo-2': Undo2,
    tags: Tags,
    'building-2': Building2,
    package: Package,
    'shopping-cart': ShoppingCart,
    receipt: Receipt,
    'hard-hat': HardHat,
    users: Users,
    'user-cog': UserCog,
    'file-text': FileText,
    'shield-check': ShieldCheck,
    'file-spreadsheet': FileSpreadsheet,
    'bar-chart-3': BarChart3,
    'message-circle': MessageCircle,
};

const toneClasses: Record<ModuleTone, { card: string; icon: string; soft: string }> = {
    teal: {
        card: 'from-teal-600 to-teal-800',
        icon: 'bg-teal-50 text-teal-700',
        soft: 'bg-teal-50 text-teal-800 ring-teal-100',
    },
    sky: {
        card: 'from-sky-500 to-sky-700',
        icon: 'bg-sky-50 text-sky-700',
        soft: 'bg-sky-50 text-sky-800 ring-sky-100',
    },
    amber: {
        card: 'from-amber-500 to-orange-600',
        icon: 'bg-amber-50 text-amber-700',
        soft: 'bg-amber-50 text-amber-900 ring-amber-100',
    },
    violet: {
        card: 'from-violet-500 to-violet-700',
        icon: 'bg-violet-50 text-violet-700',
        soft: 'bg-violet-50 text-violet-800 ring-violet-100',
    },
    indigo: {
        card: 'from-indigo-500 to-indigo-700',
        icon: 'bg-indigo-50 text-indigo-700',
        soft: 'bg-indigo-50 text-indigo-800 ring-indigo-100',
    },
    fuchsia: {
        card: 'from-fuchsia-500 to-fuchsia-700',
        icon: 'bg-fuchsia-50 text-fuchsia-700',
        soft: 'bg-fuchsia-50 text-fuchsia-800 ring-fuchsia-100',
    },
    blue: {
        card: 'from-blue-500 to-blue-700',
        icon: 'bg-blue-50 text-blue-700',
        soft: 'bg-blue-50 text-blue-800 ring-blue-100',
    },
    emerald: {
        card: 'from-emerald-500 to-emerald-700',
        icon: 'bg-emerald-50 text-emerald-700',
        soft: 'bg-emerald-50 text-emerald-800 ring-emerald-100',
    },
    orange: {
        card: 'from-orange-500 to-orange-700',
        icon: 'bg-orange-50 text-orange-700',
        soft: 'bg-orange-50 text-orange-800 ring-orange-100',
    },
    cyan: {
        card: 'from-cyan-500 to-cyan-700',
        icon: 'bg-cyan-50 text-cyan-700',
        soft: 'bg-cyan-50 text-cyan-800 ring-cyan-100',
    },
    slate: {
        card: 'from-slate-600 to-slate-800',
        icon: 'bg-slate-100 text-slate-700',
        soft: 'bg-slate-100 text-slate-800 ring-slate-200',
    },
    lime: {
        card: 'from-lime-500 to-lime-700',
        icon: 'bg-lime-50 text-lime-700',
        soft: 'bg-lime-50 text-lime-800 ring-lime-100',
    },
    rose: {
        card: 'from-rose-500 to-rose-700',
        icon: 'bg-rose-50 text-rose-700',
        soft: 'bg-rose-50 text-rose-800 ring-rose-100',
    },
    green: {
        card: 'from-green-500 to-green-700',
        icon: 'bg-green-50 text-green-700',
        soft: 'bg-green-50 text-green-800 ring-green-100',
    },
};

export function moduleIcon(name: string): LucideIcon {
    return iconMap[name] || LayoutGrid;
}

export function moduleTone(tone: string) {
    return toneClasses[(tone as ModuleTone)] || toneClasses.teal;
}

export function badgeToneClass(tone?: string | null): string {
    switch (tone) {
        case 'emerald':
            return 'bg-emerald-50 text-emerald-700 ring-emerald-100';
        case 'rose':
            return 'bg-rose-50 text-rose-700 ring-rose-100';
        case 'amber':
            return 'bg-amber-50 text-amber-800 ring-amber-100';
        case 'sky':
            return 'bg-sky-50 text-sky-700 ring-sky-100';
        case 'teal':
            return 'bg-teal-50 text-teal-700 ring-teal-100';
        default:
            return 'bg-slate-100 text-slate-700 ring-slate-200';
    }
}
