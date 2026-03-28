<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';

const form = useForm({});
const logout = () => {
    form.post('/logout');
};
</script>

<template>
    <Head title="Terminal Dashboard" />
    
    <div style="padding: 2rem; min-height: 100vh; display: flex; flex-direction: column;">
        <div class="sharp-panel" style="width: 100%; max-width: 1400px; margin: 0 auto; flex: 1;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--bg-panel-border); padding-bottom: 1.5rem; margin-bottom: 2.5rem;">
                <div>
                    <h2 class="brand-title" style="margin: 0; font-size: 1.25rem;">
                        <i class="pi pi-server brand-accent" style="margin-right: 0.75rem;"></i>
                        Stock Command Interface
                    </h2>
                </div>
                <div>
                    <Button label="Terminate Session" icon="pi pi-power-off" @click="logout" severity="danger" variant="outlined" style="border-radius: 2px; font-size: 0.8rem; letter-spacing: 0.05em;" />
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
               <div style="border: 1px solid var(--bg-panel-border); padding: 1.5rem; border-radius: 2px;">
                   <div style="color: var(--text-secondary); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.5rem;">System Status</div>
                   <div style="color: var(--accent-primary); font-size: 1.5rem; font-weight: 600;">ONLINE / ACTIVE</div>
               </div>
               <div style="border: 1px solid var(--bg-panel-border); padding: 1.5rem; border-radius: 2px;">
                   <div style="color: var(--text-secondary); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.5rem;">Active Modules</div>
                   <div style="color: var(--text-primary); font-size: 1.5rem; font-weight: 600;">CATALOG / INVENTORY</div>
               </div>
            </div>
        </div>
    </div>
</template>
