<template>
    <div :class="[isDark ? 'app-dark' : '']" class="flex min-h-screen bg-deep font-sans selection:bg-sky-500/30 selection:text-sky-200 transition-colors duration-500">
        <Toast />
        <ConfirmDialog />
        
        <!-- Sidebar Navigation -->
        <aside 
            :class="[
                collapsed ? 'w-20' : 'w-72',
                'fixed inset-y-0 left-0 z-50 flex flex-col transition-all duration-500 ease-in-out',
                isDark ? 'bg-[#111113] border-r border-zinc-900/80' : 'bg-white border-r border-slate-200 shadow-sm'
            ]"
        >
            <!-- Sidebar Header: Brand -->
            <div :class="['h-20 flex items-center px-6 border-b transition-colors', isDark ? 'border-zinc-900/80 bg-zinc-900/20' : 'border-slate-100 bg-slate-50/50']">
                <div class="flex items-center gap-4 overflow-hidden">
                    <div class="min-w-[32px] w-8 h-8 rounded-lg bg-sky-500 flex items-center justify-center shadow-[0_0_15px_rgba(14,165,233,0.3)]">
                        <i class="pi pi-server text-white text-sm"></i>
                    </div>
                    <div v-if="!collapsed" class="flex flex-col whitespace-nowrap animate-in fade-in slide-in-from-left-4 duration-500">
                        <span class="text-[10px] font-black text-sky-400 font-mono tracking-[0.3em] leading-none mb-1">SYSTEM</span>
                        <span :class="['font-bold text-sm tracking-tighter', isDark ? 'text-white' : 'text-slate-900']">Nexus</span>
                    </div>
                </div>
            </div>
            
            <!-- Navigation Links -->
            <nav class="flex-1 overflow-y-auto custom-scrollbar p-4 py-8">
                <div v-for="(section, sIndex) in navSections" :key="section.label" :class="{ 'mt-8': sIndex > 0 }">
                    <!-- Section Header -->
                    <div v-if="!collapsed" class="px-4 mb-3">
                        <span :class="['text-[9px] font-black uppercase tracking-[0.3em] font-mono leading-none', isDark ? 'text-zinc-500' : 'text-slate-400']">
                            {{ section.label }}
                        </span>
                    </div>
                    <div v-else :class="['h-px mb-4 mx-4', isDark ? 'bg-zinc-800/50' : 'bg-slate-200']"></div>

                    <!-- Section Items -->
                    <div class="space-y-1.5">
                        <template v-for="item in section.items" :key="item.href">
                              <Link 
                                :href="item.href"
                                :class="[
                                    page.url.startsWith(item.href) 
                                        ? (isDark ? 'bg-zinc-900/60 text-white border-zinc-700/50 shadow-[inset_0_1px_10px_rgba(0,0,0,0.2)]' : 'bg-sky-50 text-sky-700 border-sky-200 shadow-sm')
                                        : (isDark ? 'text-zinc-500 hover:text-zinc-200 hover:bg-zinc-900/40 border-transparent' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-50 border-transparent'),
                                    'group flex items-center gap-4 px-4 py-3.5 rounded-xl border transition-all duration-300 no-underline'
                                ]"
                                :title="item.label"
                            >
                                <div class="min-w-[20px] flex items-center justify-center">
                                    <i :class="[
                                        item.icon, 
                                        page.url.startsWith(item.href) ? (isDark ? 'text-sky-400' : 'text-sky-600') : item.color,
                                        page.url.startsWith(item.href) ? 'opacity-100' : 'opacity-40 group-hover:opacity-100'
                                    ]" class="text-base transition-all duration-300"></i>
                                </div>
                                
                                <span v-if="!collapsed" :class="[
                                    page.url.startsWith(item.href) 
                                        ? (isDark ? 'text-white' : 'text-sky-700') 
                                        : (isDark ? 'text-zinc-400' : 'text-slate-500')
                                ]" class="text-[11px] font-bold tracking-[0.15em] uppercase font-mono transition-all duration-300 group-hover:text-current">
                                    {{ item.label }}
                                </span>
                            </Link>
                        </template>
                    </div>
                </div>
            </nav>

            <!-- Sidebar Footer: Collapse Toggle -->
            <div :class="['p-4 border-t', isDark ? 'border-zinc-900/80 bg-[#111113]' : 'border-slate-200 bg-slate-50']">
                <button 
                    @click="toggleSidebar" 
                    :class="['w-full h-12 flex items-center justify-center rounded-xl transition-all border group no-underline', isDark ? 'bg-zinc-900/40 text-zinc-500 hover:text-white hover:bg-zinc-800/60 border-zinc-900/50 hover:border-zinc-700/50' : 'bg-white text-slate-500 hover:text-slate-900 hover:bg-slate-100 border-slate-200 shadow-sm']"
                >
                    <i :class="collapsed ? 'pi pi-angle-right' : 'pi pi-angle-left'" class="text-sm group-hover:scale-125 transition-transform"></i>
                    <span v-if="!collapsed" class="ml-3 text-[10px] font-bold tracking-[0.15em] uppercase font-mono">Collapse</span>
                </button>
            </div>
        </aside>

        <!-- Main Workspace Area -->
        <div 
            :class="[
                collapsed ? 'pl-20' : 'pl-72',
                'flex-1 flex flex-col transition-all duration-500 ease-in-out min-h-screen'
            ]"
        >
            <!-- Identity & Topbar Status -->
            <header :class="['h-20 flex items-center justify-between px-10 sticky top-0 z-40 backdrop-blur-xl border-b transition-colors', isDark ? 'bg-[#111113]/80 border-zinc-900/80' : 'bg-white/80 border-slate-200']">
                <div class="flex items-center gap-6">
                    <div class="hidden md:flex flex-col">
                        <div class="flex items-center gap-2 mb-0.5">
                            <span :class="['text-[10px] font-bold uppercase tracking-widest font-mono', isDark ? 'text-zinc-600' : 'text-slate-400']">STATUS</span>
                            <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]"></div>
                        </div>
                        <span :class="['text-[11px] font-bold tracking-tight uppercase font-mono', isDark ? 'text-white' : 'text-slate-900']">ONLINE // SECURE</span>
                    </div>
                </div>

                <div class="flex items-center gap-8">
                    <!-- Notifications -->
                    <button :class="['relative w-11 h-11 rounded-xl flex items-center justify-center transition-all cursor-pointer group outline-none border', isDark ? 'bg-zinc-900/40 border-zinc-800/60 text-zinc-500 hover:text-amber-400 hover:bg-amber-500/10 hover:border-amber-500/20' : 'bg-slate-50 border-slate-200 text-slate-500 hover:text-amber-500 hover:bg-amber-50']" title="Alerts">
                        <i class="pi pi-bell text-sm group-hover:-rotate-12 transition-transform"></i>
                        <span class="absolute top-2 right-2.5 w-2 h-2 rounded-full bg-amber-500 border shadow-[0_0_10px_rgba(245,158,11,0.6)] animate-pulse" :class="isDark ? 'border-[#111113]' : 'border-white'"></span>
                    </button>

                    <!-- Theme Toggle -->
                    <button 
                        @click="toggleDark()"
                        :class="['w-11 h-11 rounded-xl flex items-center justify-center transition-all cursor-pointer group outline-none border', isDark ? 'bg-zinc-900/40 border-zinc-800/60 text-zinc-500 hover:text-sky-400 hover:bg-sky-500/10 hover:border-sky-500/20' : 'bg-slate-50 border-slate-200 text-slate-500 hover:text-sky-600 hover:bg-sky-50']"
                        :title="isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
                    >
                        <i :class="isDark ? 'pi pi-palette' : 'pi pi-palette'" class="text-sm group-hover:rotate-12 transition-transform"></i>
                    </button>

                    <!-- User Profile -->
                    <div v-if="user" :class="['flex flex-col items-end border-r pr-8', isDark ? 'border-zinc-800' : 'border-slate-200']">
                        <span :class="['font-black text-[10px] tracking-widest uppercase font-mono mb-0.5', isDark ? 'text-white' : 'text-slate-900']">{{ user.username }}</span>
                        <div class="flex items-center gap-2">
                             <div class="w-1 h-1 rounded-full bg-sky-500"></div>
                             <span :class="['text-[9px] font-bold uppercase tracking-wider font-mono', isDark ? 'text-zinc-600' : 'text-slate-400']">ROLE: {{ user.role?.toUpperCase() || 'USER' }}</span>
                        </div>
                    </div>

                    <!-- Logout -->
                    <Link href="/logout" method="post" as="button" 
                          :class="['w-11 h-11 rounded-xl flex items-center justify-center transition-all active:scale-95 group no-underline border', isDark ? 'bg-zinc-900/40 border-zinc-800/60 text-zinc-500 hover:text-red-400 hover:bg-red-500/10 hover:border-red-500/20' : 'bg-slate-50 border-slate-200 text-slate-500 hover:text-red-500 hover:bg-red-50']"
                          title="Log Out"
                    >
                        <i class="pi pi-power-off text-base group-hover:rotate-12 transition-transform"></i>
                    </Link>
                </div>
            </header>

            <!-- Secondary Topbar (Global Breadcrumbs) -->
            <div :class="['h-10 border-b flex items-center px-10', isDark ? 'bg-zinc-900/20 border-zinc-900/50' : 'bg-slate-50 border-slate-200']">
                <div :class="['flex items-center gap-2 text-[9px] font-bold font-mono tracking-widest uppercase', isDark ? 'text-zinc-600' : 'text-slate-400']">
                    <span :class="['cursor-pointer', isDark ? 'hover:text-zinc-400' : 'hover:text-slate-600']">SYSTEM</span>
                    <i class="pi pi-chevron-right text-[7px]" />
                    <span :class="isDark ? 'text-zinc-400' : 'text-slate-600'">{{ page.url.split('/')[1]?.toUpperCase() || 'DASHBOARD' }}</span>
                    <i v-if="page.url.split('/').length > 2" class="pi pi-chevron-right text-[7px]" />
                    <span v-if="page.url.split('/').length > 2" class="text-sky-400/80">{{ page.url.split('/').pop().toUpperCase().replace(/-/g, ' ') }}</span>
                </div>
            </div>

            <!-- Core App Slot -->
            <main class="flex-1 bg-deep p-10 transition-colors duration-500">
                <div class="max-w-[1700px] mx-auto min-h-full">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>

<script setup>
import { usePage, Link } from '@inertiajs/vue3';
import { computed, ref, onMounted } from 'vue';
import { useDark, useToggle } from '@vueuse/core';
import Toast from 'primevue/toast';
import ConfirmDialog from 'primevue/confirmdialog';

const page = usePage();
const user = computed(() => page.props.auth.user);

const isDark = useDark({
    selector: 'html',
    attribute: 'class',
    valueDark: 'app-dark',
    valueLight: '',
});
const toggleDark = useToggle(isDark);

const collapsed = ref(false);

const toggleSidebar = () => {
    collapsed.value = !collapsed.value;
    localStorage.setItem('sidebar-collapsed', collapsed.value);
};

const navSections = [
    {
        label: 'Analytics',
        items: [
            { label: 'Overview', href: '/dashboard', icon: 'pi pi-compass', color: 'text-sky-400' },
        ]
    },
    {
        label: 'Commerce',
        items: [
            { label: 'Procurement', href: '/purchase-orders', icon: 'pi pi-shopping-bag', color: 'text-orange-400' },
            { label: 'Sales', href: '/sales-orders', icon: 'pi pi-receipt', color: 'text-teal-400' },
            { label: 'Finance', href: '/finance-center', icon: 'pi pi-wallet', color: 'text-indigo-400' },
            { label: 'Vendors', href: '/vendor-center', icon: 'pi pi-users', color: 'text-rose-400' },
            { label: 'Customers', href: '/customer-center', icon: 'pi pi-id-card', color: 'text-cyan-400' },
        ]
    },
    {
        label: 'Logistics',
        items: [
            { label: 'Catalog', href: '/catalog', icon: 'pi pi-box', color: 'text-emerald-400' },
            { label: 'Inventory', href: '/inventory-center', icon: 'pi pi-database', color: 'text-amber-400' },
            { label: 'Transfers', href: '/movements/transfer', icon: 'pi pi-arrow-right-arrow-left', color: 'text-indigo-400' },
            { label: 'Locations', href: '/location-center', icon: 'pi pi-map-marker', color: 'text-violet-400' },
        ]
    },
    {
        label: 'System',
        items: [
            { label: 'Categories', href: '/category-center', icon: 'pi pi-tags', color: 'text-orange-400' },
            { label: 'UOM Config', href: '/uom-center', icon: 'pi pi-sort-alt', color: 'text-fuchsia-400' },
        ]
    }
];

onMounted(() => {
    collapsed.value = localStorage.getItem('sidebar-collapsed') === 'true';
});
</script>

<style>
/* Global scrollbar technical redesign */
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: var(--bg-panel-border);
    border-radius: 10px;
    border: 1px solid var(--bg-panel);
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: var(--text-muted);
}
</style>

