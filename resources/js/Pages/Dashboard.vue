<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
</script>

<template>
    <AppLayout>
        <Head title="Terminal Dashboard" />
        
        <div style="display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1px solid var(--bg-panel-border); padding-bottom: 1rem; margin-bottom: 2rem;">
            <div>
                <h2 class="brand-title" style="margin: 0; font-size: 1.25rem;">Command Overview</h2>
                <div style="color: var(--text-secondary); font-size: 0.75rem; letter-spacing: 0.1em; text-transform: uppercase;">
                    Real-time network status
                </div>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
            <!-- Status Card -->
            <div class="sharp-panel" style="padding: 1.5rem;">
                <div style="color: var(--text-secondary); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.5rem;">Network Status</div>
                <div style="color: var(--accent-primary); font-size: 1.75rem; font-weight: 600; line-height: 1;">ONLINE</div>
                <div style="color: #a1a1aa; font-size: 0.75rem; margin-top: 1rem;">All datacenters connected.</div>
            </div>

            <!-- Active Modules Card -->
            <div class="sharp-panel" style="padding: 1.5rem;">
                <div style="color: var(--text-secondary); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.5rem;">Active Systems</div>
                <div style="color: var(--text-primary); font-size: 1.2rem; font-weight: 500; font-family: 'Outfit', sans-serif;">CATALOG_API</div>
                <div style="color: var(--text-primary); font-size: 1.2rem; font-weight: 500; font-family: 'Outfit', sans-serif; margin-top: 5px;">STOCK_ENGINE</div>
            </div>
            
            <!-- Global Inventory Card -->
            <div class="sharp-panel" style="padding: 1.5rem;">
                <div style="color: var(--text-secondary); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.5rem;">Transaction Queue</div>
                <div style="color: #fb923c; font-size: 1.75rem; font-weight: 600; line-height: 1;">EMPTY</div>
                <div style="color: #a1a1aa; font-size: 0.75rem; margin-top: 1rem;">Awaiting stock movements.</div>
            </div>
        </div>
    </AppLayout>
</template>
