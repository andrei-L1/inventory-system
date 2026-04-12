<template>
    <div class="flex min-h-screen bg-zinc-950 font-sans selection:bg-sky-500/30 selection:text-sky-200">
        <Toast />
        <ConfirmDialog />
        
        <!-- Sidebar Navigation -->
        <aside 
            :class="[
                collapsed ? 'w-20' : 'w-72',
                'fixed inset-y-0 left-0 z-50 flex flex-col bg-zinc-950 border-r border-zinc-900 transition-all duration-500 ease-in-out'
            ]"
        >
            <!-- Sidebar Header: Brand -->
            <div class="h-20 flex items-center px-6 border-b border-zinc-900/50 bg-zinc-900/20">
                <div class="flex items-center gap-4 overflow-hidden">
                    <div class="min-w-[32px] w-8 h-8 rounded-lg bg-sky-500 flex items-center justify-center shadow-[0_0_15px_rgba(14,165,233,0.3)]">
                        <i class="pi pi-server text-white text-sm"></i>
                    </div>
                    <div v-if="!collapsed" class="flex flex-col whitespace-nowrap animate-in fade-in slide-in-from-left-4 duration-500">
                        <span class="text-[10px] font-black text-sky-400 font-mono tracking-[0.3em] leading-none mb-1">SYSTEM</span>
                        <span class="text-white font-bold text-sm tracking-tighter">Nexus</span>
                    </div>
                </div>
            </div>
            
            <!-- Navigation Links -->
            <nav class="flex-1 overflow-y-auto custom-scrollbar p-4 py-8">
                <div v-for="(section, sIndex) in navSections" :key="section.label" :class="{ 'mt-8': sIndex > 0 }">
                    <!-- Section Header -->
                    <div v-if="!collapsed" class="px-4 mb-3">
                        <span class="text-[9px] font-black text-zinc-700 uppercase tracking-[0.3em] font-mono leading-none">
                            {{ section.label }}
                        </span>
                    </div>
                    <div v-else class="h-px bg-zinc-900/50 mb-4 mx-4"></div>

                    <!-- Section Items -->
                    <div class="space-y-1.5">
                        <template v-for="item in section.items" :key="item.href">
                             <Link 
                                :href="item.href"
                                :class="[
                                    page.url.startsWith(item.href) 
                                        ? 'bg-zinc-900 text-white border-zinc-700' 
                                        : 'text-zinc-500 hover:text-zinc-200 hover:bg-zinc-900 border-transparent',
                                    'group flex items-center gap-4 px-4 py-3.5 rounded-xl border transition-all duration-300 no-underline'
                                ]"
                                :title="item.label"
                            >
                                <div class="min-w-[20px] flex items-center justify-center">
                                    <i :class="[
                                        item.icon, 
                                        item.color,
                                        page.url.startsWith(item.href) ? 'opacity-100' : 'opacity-40 group-hover:opacity-100'
                                    ]" class="text-base transition-all duration-300"></i>
                                </div>
                                
                                <span v-if="!collapsed" :class="[page.url.startsWith(item.href) ? 'text-zinc-100' : 'text-zinc-500']" class="text-[11px] font-bold tracking-[0.15em] uppercase font-mono transition-all duration-300">
                                    {{ item.label }}
                                </span>
                            </Link>
                        </template>
                    </div>
                </div>
            </nav>

            <!-- Sidebar Footer: Collapse Toggle -->
            <div class="p-4 border-t border-zinc-900/50 bg-zinc-950">
                <button 
                    @click="toggleSidebar" 
                    class="w-full h-12 flex items-center justify-center rounded-xl bg-zinc-900/40 text-zinc-500 hover:text-white hover:bg-zinc-800 transition-all border border-zinc-900 hover:border-zinc-700 group no-underline"
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
            <header class="h-20 flex items-center justify-between px-10 sticky top-0 z-40 bg-zinc-950/80 backdrop-blur-xl border-b border-zinc-900/50">
                <div class="flex items-center gap-6">
                    <div class="hidden md:flex flex-col">
                        <div class="flex items-center gap-2 mb-0.5">
                            <span class="text-[10px] font-bold text-zinc-700 uppercase tracking-widest font-mono">STATUS</span>
                            <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]"></div>
                        </div>
                        <span class="text-white text-[11px] font-bold tracking-tight uppercase font-mono">ONLINE // SECURE</span>
                    </div>
                </div>

                <div class="flex items-center gap-8">
                    <!-- Notifications -->
                    <button class="relative w-11 h-11 rounded-xl bg-zinc-900/40 border border-zinc-800 flex items-center justify-center text-zinc-500 hover:text-amber-400 hover:bg-amber-500/10 hover:border-amber-500/20 transition-all cursor-pointer group outline-none" title="Alerts">
                        <i class="pi pi-bell text-sm group-hover:-rotate-12 transition-transform"></i>
                        <span class="absolute top-2 right-2.5 w-2 h-2 rounded-full bg-amber-500 border border-zinc-950 shadow-[0_0_10px_rgba(245,158,11,0.6)] animate-pulse"></span>
                    </button>

                    <!-- User Profile -->
                    <div v-if="user" class="flex flex-col items-end border-r border-zinc-900 pr-8">
                        <span class="text-white font-black text-[10px] tracking-widest uppercase font-mono mb-0.5">{{ user.username }}</span>
                        <div class="flex items-center gap-2">
                             <div class="w-1 h-1 rounded-full bg-sky-500"></div>
                             <span class="text-zinc-600 text-[9px] font-bold uppercase tracking-wider font-mono">ROLE: {{ user.role?.toUpperCase() || 'USER' }}</span>
                        </div>
                    </div>

                    <!-- Logout -->
                    <Link href="/logout" method="post" as="button" 
                          class="w-11 h-11 rounded-xl bg-zinc-900/50 border border-zinc-800 flex items-center justify-center text-zinc-600 hover:text-red-400 hover:bg-red-500/10 hover:border-red-500/20 transition-all active:scale-95 group no-underline"
                          title="Log Out"
                    >
                        <i class="pi pi-power-off text-base group-hover:rotate-12 transition-transform"></i>
                    </Link>
                </div>
            </header>

            <!-- Secondary Topbar (Global Breadcrumbs) -->
            <div class="h-10 bg-zinc-900/10 border-b border-zinc-900/30 flex items-center px-10">
                <div class="flex items-center gap-2 text-[9px] font-bold text-zinc-700 font-mono tracking-widest uppercase">
                    <span class="hover:text-zinc-500 cursor-pointer">SYSTEM</span>
                    <i class="pi pi-chevron-right text-[7px]" />
                    <span class="text-zinc-500">{{ page.url.split('/')[1]?.toUpperCase() || 'DASHBOARD' }}</span>
                    <i v-if="page.url.split('/').length > 2" class="pi pi-chevron-right text-[7px]" />
                    <span v-if="page.url.split('/').length > 2" class="text-sky-400/80">{{ page.url.split('/').pop().toUpperCase().replace(/-/g, ' ') }}</span>
                </div>
            </div>

            <!-- Core App Slot -->
            <main class="flex-1 bg-zinc-950 p-10">
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
import Toast from 'primevue/toast';
import ConfirmDialog from 'primevue/confirmdialog';

const page = usePage();
const user = computed(() => page.props.auth.user);

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
    background: #18181b;
    border-radius: 10px;
    border: 1px solid #27272a;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #27272a;
}
</style>
