<script setup>
import { usePage, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Toast from 'primevue/toast';
import ConfirmDialog from 'primevue/confirmdialog';

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <div class="app-layout">
        <Toast />
        <ConfirmDialog />
        <!-- Sidebar -->
        <aside class="app-sidebar sharp-panel">
            <div class="sidebar-header">
                <i class="pi pi-server brand-accent" style="font-size: 1.5rem;"></i>
                <span class="brand-title" style="font-size: 1.1rem; margin: 0; padding-left: 0.5rem; letter-spacing: 0.1em;">CMD_CTR</span>
            </div>
            <nav class="sidebar-nav">
                <Link href="/dashboard" class="nav-item">
                    <i class="pi pi-home"></i> <span>Dashboard</span>
                </Link>
                <Link href="/catalog" class="nav-item">
                    <i class="pi pi-box"></i> <span>Catalog</span>
                </Link>
                <!-- Placeholders for Future Phases -->
                <a href="#" class="nav-item disabled">
                    <i class="pi pi-arrow-right-arrow-left"></i> <span>Transfers</span>
                </a>
                <a href="#" class="nav-item disabled">
                    <i class="pi pi-chart-bar"></i> <span>Reports</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="app-content-wrapper">
            <!-- Topbar -->
            <header class="app-topbar sharp-panel">
                <div class="topbar-left">
                    <span class="text-muted" style="letter-spacing: 0.1em; font-size: 0.75rem; font-weight: 600;">SYS_STATUS: <span style="color: var(--accent-primary);">OPTIMAL</span></span>
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
}

.sidebar-header {
    display: flex;
    align-items: center;
    padding-bottom: 2rem;
    border-bottom: 1px solid var(--bg-panel-border);
    margin-bottom: 2rem;
}

.sidebar-nav {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.85rem 1rem;
    color: var(--text-secondary);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    border-radius: 2px;
    transition: all 0.15s ease-in-out;
    border-left: 2px solid transparent;
}

.nav-item:hover:not(.disabled) {
    background-color: rgba(59, 130, 246, 0.05); /* very subtle blue */
    color: var(--text-primary);
    border-left: 2px solid var(--accent-primary);
}

.nav-item.disabled {
    opacity: 0.4;
    cursor: not-allowed;
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
    border-left: none; /* Already defined by sidebar */
    padding: 0 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    z-index: 5;
    box-shadow: none; /* Flatten it out */
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
    color: #ef4444; /* Sharp Red Alert */
}

.app-main {
    flex: 1;
    padding: 2.5rem;
    overflow-y: auto;
}
</style>
