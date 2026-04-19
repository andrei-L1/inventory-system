<template>
    <div :class="[isDark ? 'app-dark' : '']" class="flex min-h-screen bg-deep font-sans selection:bg-sky-500/30 selection:text-sky-200 transition-colors duration-500">
        <Toast />
        <ConfirmDialog />
        
        <!-- Sidebar Navigation -->
        <aside 
            :class="[
                collapsed ? 'w-20' : 'w-72',
                'fixed inset-y-0 left-0 z-50 flex flex-col bg-panel border-r border-panel-border transition-all duration-500 ease-in-out'
            ]"
        >
            <!-- Sidebar Header: Brand -->
            <div class="h-20 flex items-center px-6 border-b border-panel-border bg-panel-hover/20">
                <div class="flex items-center gap-4 overflow-hidden">
                    <div class="min-w-[32px] w-8 h-8 rounded-lg bg-sky-500 flex items-center justify-center shadow-[0_0_15px_rgba(14,165,233,0.3)]">
                        <i class="pi pi-server text-white text-sm"></i>
                    </div>
                    <div v-if="!collapsed" class="flex flex-col whitespace-nowrap animate-in fade-in slide-in-from-left-4 duration-500">
                        <span class="text-[10px] font-black text-sky-400 font-mono tracking-[0.3em] leading-none mb-1">SYSTEM</span>
                        <span class="text-primary font-bold text-sm tracking-tighter">Nexus</span>
                    </div>
                </div>
            </div>
            
            <!-- Navigation Links -->
            <nav class="flex-1 overflow-y-auto custom-scrollbar p-4 py-8">
                <div v-for="(section, sIndex) in navSections" :key="section.label" :class="{ 'mt-8': sIndex > 0 }">
                    <!-- Section Header -->
                    <div v-if="!collapsed" class="px-4 mb-3">
                        <span class="text-[9px] font-black text-muted uppercase tracking-[0.3em] font-mono leading-none">
                            {{ section.label }}
                        </span>
                    </div>
                    <div v-else class="h-px bg-panel-border mb-4 mx-4"></div>

                    <!-- Section Items -->
                    <div class="space-y-1.5">
                        <template v-for="item in section.items" :key="item.href">
                              <Link 
                                :href="item.href"
                                :class="[
                                    page.url.startsWith(item.href) 
                                        ? (isDark ? 'bg-sky-500/10 text-sky-300 border-sky-500/30' : 'bg-sky-50 text-sky-700 border-sky-200')
                                        : 'text-secondary hover:text-primary hover:bg-panel-hover border-transparent',
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
                                        ? (isDark ? 'text-sky-300' : 'text-sky-700') 
                                        : 'text-secondary'
                                ]" class="text-[11px] font-bold tracking-[0.15em] uppercase font-mono transition-all duration-300">
                                    {{ item.label }}
                                </span>
                            </Link>
                        </template>
                    </div>
                </div>
            </nav>

            <!-- Sidebar Footer: Collapse Toggle -->
            <div class="p-4 border-t border-panel-border bg-panel">
                <button 
                    @click="toggleSidebar" 
                    class="w-full h-12 flex items-center justify-center rounded-xl bg-panel-hover text-secondary hover:text-primary hover:bg-panel-hover transition-all border border-panel-border hover:border-panel-border group no-underline"
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
            <header class="h-20 flex items-center justify-between px-10 sticky top-0 z-40 bg-deep/80 backdrop-blur-xl border-b border-panel-border">
                <div class="flex items-center gap-6">
                    <div class="hidden md:flex flex-col">
                        <div class="flex items-center gap-2 mb-0.5">
                            <span class="text-[10px] font-bold text-muted uppercase tracking-widest font-mono">STATUS</span>
                            <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]"></div>
                        </div>
                        <span class="text-primary text-[11px] font-bold tracking-tight uppercase font-mono">ONLINE // SECURE</span>
                    </div>
                </div>

                <div class="flex items-center gap-8">
                    <!-- Notifications -->
                    <button class="relative w-11 h-11 rounded-xl bg-panel-hover border border-panel-border flex items-center justify-center text-secondary hover:text-amber-400 hover:bg-amber-500/10 hover:border-amber-500/20 transition-all cursor-pointer group outline-none" title="Alerts">
                        <i class="pi pi-bell text-sm group-hover:-rotate-12 transition-transform"></i>
                        <span class="absolute top-2 right-2.5 w-2 h-2 rounded-full bg-amber-500 border border-deep shadow-[0_0_10px_rgba(245,158,11,0.6)] animate-pulse"></span>
                    </button>

                    <!-- Theme Toggle -->
                    <button 
                        @click="toggleDark()"
                        class="w-11 h-11 rounded-xl bg-panel-hover border border-panel-border flex items-center justify-center text-secondary hover:text-sky-400 hover:bg-sky-500/10 hover:border-sky-500/20 transition-all cursor-pointer group outline-none"
                        :title="isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
                    >
                        <i :class="isDark ? 'pi pi-palette' : 'pi pi-palette'" class="text-sm group-hover:rotate-12 transition-transform"></i>
                    </button>

                    <!-- User Profile -->
                    <div v-if="user" class="flex flex-col items-end border-r border-panel-border pr-8">
                        <span class="text-primary font-black text-[10px] tracking-widest uppercase font-mono mb-0.5">{{ user.username }}</span>
                        <div class="flex items-center gap-2">
                             <div class="w-1 h-1 rounded-full bg-sky-500"></div>
                             <span class="text-muted text-[9px] font-bold uppercase tracking-wider font-mono">ROLE: {{ user.role?.toUpperCase() || 'USER' }}</span>
                        </div>
                    </div>

                    <!-- Logout -->
                    <Link href="/logout" method="post" as="button" 
                          class="w-11 h-11 rounded-xl bg-panel-hover border border-panel-border flex items-center justify-center text-secondary hover:text-red-400 hover:bg-red-500/10 hover:border-red-500/20 transition-all active:scale-95 group no-underline"
                          title="Log Out"
                    >
                        <i class="pi pi-power-off text-base group-hover:rotate-12 transition-transform"></i>
                    </Link>
                </div>
            </header>

            <!-- Secondary Topbar (Global Breadcrumbs) -->
            <div class="h-10 bg-panel-hover/10 border-b border-panel-border/30 flex items-center px-10">
                <div class="flex items-center gap-2 text-[9px] font-bold text-muted font-mono tracking-widest uppercase">
                    <span class="hover:text-secondary cursor-pointer">SYSTEM</span>
                    <i class="pi pi-chevron-right text-[7px]" />
                    <span class="text-secondary">{{ page.url.split('/')[1]?.toUpperCase() || 'DASHBOARD' }}</span>
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

