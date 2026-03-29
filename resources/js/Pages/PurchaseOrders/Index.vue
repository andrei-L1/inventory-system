<script setup>
import { ref, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import Button from 'primevue/button';
import axios from 'axios';
import { usePermissions } from '@/Composables/usePermissions';

const { can } = usePermissions();
const purchaseOrders = ref([]);
const loading = ref(false);
const search = ref('');

const loadPurchaseOrders = async () => {
    loading.value = true;
    try {
        const res = await axios.get('/api/purchase-orders', { params: { query: search.value } });
        purchaseOrders.value = res.data.data;
    } catch (e) {
        console.error("Failed to load POs:", e);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    loadPurchaseOrders();
});

const getStatusColor = (statusName) => {
    const map = {
        'draft': 'warning',
        'open': 'info',
        'partially_received': 'help',
        'closed': 'success',
        'cancelled': 'danger'
    };
    return map[statusName] || 'info';
};
</script>

<template>
    <Head title="Procurement - Purchase Orders" />
    <AppLayout>
        <div class="flex flex-col gap-6 h-full">
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-zinc-900/40 p-6 rounded-2xl border border-zinc-800/80 shadow-xl relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-orange-500/5 blur-[100px] pointer-events-none"></div>
                
                <div class="flex items-center gap-4 z-10">
                    <div class="w-12 h-12 rounded-xl bg-orange-500/10 flex items-center justify-center border border-orange-500/20 shadow-[0_0_15px_rgba(249,115,22,0.1)]">
                        <i class="pi pi-shopping-bag text-xl text-orange-400"></i>
                    </div>
                    <div>
                        <h1 class="text-white text-xl font-bold tracking-tight mb-1">Procurement</h1>
                        <p class="text-zinc-500 text-[10px] font-bold tracking-[0.2em] uppercase font-mono">Purchase Order Ledger</p>
                    </div>
                </div>

                <div class="flex items-center gap-3 z-10">
                    <span class="p-input-icon-left w-full md:w-auto">
                        <i class="pi pi-search text-zinc-500" />
                        <InputText 
                            v-model="search" 
                            @input="loadPurchaseOrders"
                            placeholder="Search orders..." 
                            class="w-full md:w-64 bg-zinc-950 border-zinc-800 text-sm text-zinc-300 focus:border-orange-500/50"
                        />
                    </span>
                    <Link href="/purchase-orders/create" class="no-underline pb-1">
                        <Button 
                            v-if="can('manage-inventory')" 
                            icon="pi pi-plus" 
                            label="Draft PO" 
                            class="p-button-sm !bg-orange-500 hover:!bg-orange-600 !border-none !text-zinc-950 font-bold shadow-[0_0_15px_rgba(249,115,22,0.3)] transition-all"
                        />
                    </Link>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 bg-zinc-900/40 border border-zinc-800/80 rounded-2xl flex flex-col overflow-hidden shadow-xl">
                <DataTable 
                    :value="purchaseOrders" 
                    :loading="loading" 
                    scrollable 
                    scrollHeight="flex"
                    class="p-datatable-sm w-full flex-1"
                    stripedRows
                    hoverableRows
                    @row-click="(e) => router.visit(`/purchase-orders/${e.data.id}`)"
                >
                    <template #empty>
                        <div class="flex flex-col items-center justify-center p-12 opacity-50">
                            <i class="pi pi-inbox text-4xl text-zinc-600 mb-4"></i>
                            <span class="text-xs font-bold text-zinc-500 uppercase tracking-widest font-mono">No purchase orders found.</span>
                        </div>
                    </template>

                    <Column field="po_number" header="PO NUMBER">
                        <template #body="{ data }">
                            <span class="text-sky-400 font-mono text-xs font-bold cursor-pointer hover:underline">{{ data.po_number }}</span>
                        </template>
                    </Column>

                    <Column field="vendor_name" header="VENDOR">
                        <template #body="{ data }">
                            <span class="text-white font-bold text-xs">{{ data.vendor_name }}</span>
                        </template>
                    </Column>

                    <Column field="order_date" header="DOCUMENT DATE">
                        <template #body="{ data }">
                            <span class="text-zinc-400 text-xs font-mono">{{ data.order_date }}</span>
                        </template>
                    </Column>

                    <Column field="status" header="STATUS">
                        <template #body="{ data }">
                            <Tag 
                                :severity="getStatusColor(data.status)" 
                                :value="data.status.replace('_', ' ').toUpperCase()" 
                                class="text-[9px] font-bold tracking-widest font-mono uppercase px-2 py-0.5 rounded"
                            />
                        </template>
                    </Column>

                    <Column field="total_amount" header="TOTAL VALUE">
                        <template #body="{ data }">
                            <span class="text-emerald-400 font-mono text-xs font-bold">{{ data.currency }} {{ Number(data.total_amount).toFixed(2) }}</span>
                        </template>
                    </Column>

                    <Column bodyStyle="text-align: right; width: 5rem;">
                        <template #body="{ data }">
                            <Button 
                                icon="pi pi-chevron-right" 
                                class="p-button-text p-button-rounded p-button-sm !text-zinc-500 hover:!text-orange-400 transition-colors"
                                @click.stop="router.visit(`/purchase-orders/${data.id}`)"
                            />
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.p-datatable .p-datatable-thead > tr > th) {
    background: #18181b; /* zinc-950 */
    border-bottom: 1px solid rgba(39, 39, 42, 0.8); /* zinc-800 */
    color: #a1a1aa; /* zinc-400 */
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    padding: 1rem;
}
:deep(.p-datatable .p-datatable-tbody > tr) {
    background: transparent;
    transition: background-color 0.2s;
    cursor: pointer;
}
:deep(.p-datatable .p-datatable-tbody > tr:hover) {
    background: rgba(39, 39, 42, 0.4); /* zinc-800/40 */
}
:deep(.p-datatable .p-datatable-tbody > tr > td) {
    border-bottom: 1px solid rgba(39, 39, 42, 0.5); /* zinc-800/50 */
    color: #e4e4e7; /* zinc-200 */
    padding: 1rem;
}
:deep(.p-tag) {
    background: rgba(39, 39, 42, 0.8);
}
:deep(.p-tag.p-tag-warning) {
    background: rgba(245, 158, 11, 0.1);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.2);
}
:deep(.p-tag.p-tag-info) {
    background: rgba(14, 165, 233, 0.1);
    color: #38bdf8;
    border: 1px solid rgba(14, 165, 233, 0.2);
}
:deep(.p-tag.p-tag-success) {
    background: rgba(16, 185, 129, 0.1);
    color: #34d399;
    border: 1px solid rgba(16, 185, 129, 0.2);
}
:deep(.p-tag.p-tag-danger) {
    background: rgba(239, 68, 68, 0.1);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.2);
}
:deep(.p-tag.p-tag-help) {
    background: rgba(139, 92, 246, 0.1);
    color: #a78bfa;
    border: 1px solid rgba(139, 92, 246, 0.2);
}
</style>
