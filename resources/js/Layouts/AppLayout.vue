<script setup>
import { usePage, Link } from '@inertiajs/vue3';
import { computed, ref, onMounted } from 'vue';
import Toast from 'primevue/toast';
import ConfirmDialog from 'primevue/confirmdialog';
import Button from 'primevue/button';

const page = usePage();
const user = computed(() => page.props.auth.user);

const collapsed = ref(false);

const toggleSidebar = () => {
    collapsed.value = !collapsed.value;
    localStorage.setItem('sidebar-collapsed', collapsed.value);
};

onMounted(() => {
    collapsed.value = localStorage.getItem('sidebar-collapsed') === 'true';
});
</script>

<template>
    <div class="app-layout" :class="{ 'sidebar-collapsed': collapsed }">
        <Toast />
        <ConfirmDialog />
        
        <!-- Sidebar -->
        <aside class="app-sidebar sharp-panel">
            <div class="sidebar-header">
                <div class="brand-group">
                    <i class="pi pi-server brand-accent" style="font-size: 1.5rem;"></i>
                    <span v-if="!collapsed" class="brand-title" style="font-size: 1.1rem; margin: 0; padding-left: 0.5rem; letter-spacing: 0.1em;">CMD_CTR</span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <Link href="/dashboard" class="nav-item" title="Dashboard">
                    <i class="pi pi-home"></i> <span v-if="!collapsed">Dashboard</span>
                </Link>
                <Link href="/catalog" class="nav-item" title="Catalog">
                    <i class="pi pi-box"></i> <span v-if="!collapsed">Catalog</span>
                </Link>
                <Link href="/inventory-center" class="nav-item" title="Inventory Center">
                    <i class="pi pi-database"></i> <span v-if="!collapsed">Inventory Center</span>
                </Link>
                <Link href="/vendor-center" class="nav-item" title="Vendor Center">
                    <i class="pi pi-users"></i> <span v-if="!collapsed">Vendor Center</span>
                </Link>
                <Link href="/location-center" class="nav-item" title="Location Center">
                    <i class="pi pi-map-marker"></i> <span v-if="!collapsed">Location Center</span>
                </Link>
                <!-- Placeholders for Future Phases -->
                <a href="#" class="nav-item disabled" title="Transfers">
                    <i class="pi pi-arrow-right-arrow-left"></i> <span v-if="!collapsed">Transfers</span>
                </a>
                <a href="#" class="nav-item disabled" title="Reports">
                    <i class="pi pi-chart-bar"></i> <span v-if="!collapsed">Reports</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                 <button @click="toggleSidebar" class="collapse-toggle">
                    <i :class="collapsed ? 'pi pi-angle-double-right' : 'pi pi-angle-double-left'"></i>
                </button>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="app-content-wrapper">
            <!-- Topbar -->
            <header class="app-topbar sharp-panel">
                <div class="topbar-left">
                    <span class="status-badge" style="letter-spacing: 0.1em; font-size: 0.65rem; font-weight: 600;">SYS_STATUS: <span style="color: var(--accent-primary);">OPTIMAL</span></span>
                </div>
                <div class="topbar-right">
                    <span v-if="user" style="font-weight: 600; text-transform: uppercase; font-size: 0.75rem; padding-right: 1rem; border-right: 1px solid var(--bg-panel-border); color: var(--text-secondary); letter-spacing: 0.1em;">
                        {{ user.username }} <span style="color: var(--text-primary);">[{{ user.role }}]</span>
                    </span>
                    <Link href="/logout" method="post" as="button" class="logout-btn">
                        <i class="pi pi-power-off"></i>
                    </Link>
                </div>
            </header>

            <!-- Page Content -->
            <main class="app-main">
                <slot />
            </main>
        </div>
    </div>
</template>

<style scoped>
.app-layout {
    display: flex;
    min-height: 100vh;
    background-color: var(--bg-deep);
}

.app-sidebar {
    width: 260px;
    border-radius: 0;
    border-left: none;
    border-top: none;
    border-bottom: none;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    z-index: 10;
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.sidebar-collapsed .app-sidebar {
    width: 80px;
    padding: 1.5rem 0.75rem;
}

.sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 2rem;
    border-bottom: 1px solid var(--bg-panel-border);
    margin-bottom: 2rem;
}

.brand-group {
    display: flex;
    align-items: center;
    white-space: nowrap;
}

.sidebar-nav {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    flex: 1;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 1rem;
    color: var(--text-primary);
    text-decoration: none;
    font-size: 14px;
    border-radius: 6px;
    transition: all 0.15s ease-in-out;
    margin-bottom: 0.15rem;
}

.nav-item i {
    font-size: 1rem;
    color: var(--text-secondary);
}

.nav-item:hover:not(.disabled) {
    background-color: #2d333b; /* Canvas Subtle */
}

.nav-item.active {
    background-color: #2d333b;
    font-weight: 600;
}

.nav-item.disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.sidebar-footer {
    padding-top: 1rem;
    border-top: 1px solid var(--bg-panel-border);
    display: flex;
    justify-content: center;
}

.collapse-toggle {
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 1rem;
    padding: 0.5rem;
    transition: color 0.2s;
}

.collapse-toggle:hover {
    color: var(--accent-primary);
}

.app-content-wrapper {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.app-topbar {
    height: 70px;
    border-radius: 0;
    border-top: none;
    border-right: none;
    border-left: none;
    padding: 0 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    z-index: 5;
    box-shadow: none;
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.logout-btn {
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 1.1rem;
    padding: 0.5rem;
    transition: color 0.15s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.logout-btn:hover {
    color: #ef4444;
}

.app-main {
    flex: 1;
    padding: 2rem;
    overflow-y: auto;
    background-color: var(--bg-deep);
}
.status-badge {
    padding: 4px 8px;
    background: #010409;
    border: 1px solid var(--bg-panel-border);
    border-radius: 20px;
    color: var(--text-secondary);
}
</style>
