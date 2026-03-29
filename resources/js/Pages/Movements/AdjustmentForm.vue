<template>
    <AppLayout>
        <Head title="Inventory Adjustment" />
        
        <div class="p-8 bg-zinc-950 min-h-[calc(100vh-64px)] overflow-hidden flex flex-col">
            <div class="max-w-[1600px] w-full mx-auto mb-10 flex justify-between items-end">
                <div class="flex flex-col">
                    <span class="text-[10px] font-bold text-amber-400 uppercase tracking-[0.2em] block mb-2 font-mono">Stock Reconciliation</span>
                    <h1 class="text-3xl font-bold text-white tracking-tight m-0 mb-2">Inventory Adjustment</h1>
                    <p class="text-zinc-500 text-sm max-w-2xl leading-relaxed">
                        Manually update stock levels to match physical counts. Use this for breakage, shrinkage, or audit corrections.
                    </p>
                </div>
                
                <div class="flex items-center gap-4">
                    <button @click="router.visit('/inventory-center')" class="!bg-zinc-900 !border-zinc-800 !text-zinc-400 hover:!text-white !px-6 !h-12 !font-bold !text-[11px] uppercase tracking-widest transition-all rounded-xl border">
                        CANCEL
                    </button>
                    <button @click="postAdjustment" :disabled="isSubmitting" class="!bg-amber-500 !border-none !text-zinc-950 !px-8 !h-12 !font-bold !text-[11px] uppercase tracking-widest shadow-lg shadow-amber-500/10 hover:!bg-amber-400 active:scale-95 transition-all rounded-xl disabled:opacity-50 disabled:cursor-not-allowed">
                        {{ isSubmitting ? 'PROCESSING...' : 'APPLY ADJUSTMENT' }}
                    </button>
                </div>
            </div>

            <div class="max-w-[1600px] w-full mx-auto flex-1 flex flex-col min-h-0">
                <div class="grid grid-cols-12 gap-8 flex-1 min-h-0">
                    <aside class="col-span-12 lg:col-span-3 flex flex-col min-h-0 bg-zinc-900/40 border border-zinc-800/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm">
                        <div class="p-6 border-b border-zinc-800 bg-zinc-900/60 flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <div class="w-1.5 h-1.5 rounded-full bg-amber-500"></div>
                                <span class="text-[10px] font-bold text-zinc-300 tracking-[0.2em] uppercase font-mono leading-none">Adjustment Info</span>
                            </div>
                        </div>
                        
                        <div class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-8">
                             <div class="flex flex-col gap-3">
                                 <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Location</label>
                                 <Select 
                                      v-model="form.location" 
                                      :options="locations" 
                                      optionLabel="name" 
                                      placeholder="Warehouse..." 
                                      class="!w-full !bg-zinc-950 !border-zinc-800 !h-12 !rounded-xl !text-xs font-mono"
                                 />
                             </div>

                             <div class="flex flex-col gap-3">
                                 <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Reason for Adjustment</label>
                                 <Select 
                                      v-model="form.reason" 
                                      :options="reasons" 
                                      optionLabel="label" 
                                      placeholder="Reason..." 
                                      class="!w-full !bg-zinc-950 !border-zinc-800 !h-12 !rounded-xl !text-xs font-mono"
                                 />
                             </div>

                             <div class="flex flex-col gap-3">
                                 <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Remarks / Notes</label>
                                 <textarea v-model="form.notes" placeholder="Notes for audit trail..." class="bg-zinc-950 border border-zinc-800 rounded-xl p-4 text-xs text-zinc-400 h-40 resize-none outline-none focus:border-amber-500/30 transition-all"></textarea>
                             </div>
                        </div>
                    </aside>

                    <main class="col-span-12 lg:col-span-9 flex flex-col min-h-0 bg-zinc-900/40 border border-zinc-800/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm">
                        <div class="p-6 border-b border-zinc-800 bg-zinc-900/60 flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <div class="w-1.5 h-1.5 rounded-full bg-amber-500"></div>
                                <span class="text-[10px] font-bold text-zinc-300 tracking-[0.2em] uppercase font-mono leading-none">Items to Adjust</span>
                            </div>
                            <button @click="addLine" class="px-6 h-10 rounded-xl bg-amber-500 text-zinc-950 text-[10px] font-black uppercase tracking-widest hover:bg-amber-400 transition-all active:scale-95 flex items-center gap-2">
                                <i class="pi pi-plus text-[10px]" />
                                ADD ITEM
                            </button>
                        </div>

                        <div class="flex-1 overflow-y-auto custom-scrollbar">
                            <DataTable :value="form.lines" class="adj-grid border-none" :pt="{
                                header: { class: 'hidden' },
                                bodyrow: { class: 'hover:!bg-white/[0.02] border-b border-zinc-800/50 transition-all duration-200' }
                            }">
                                <Column field="product" header="PRODUCT" class="!py-6 !px-8">
                                    <template #body="{ index }">
                                        <div class="flex flex-col gap-2 min-w-[300px]">
                                            <Select 
                                                v-model="form.lines[index].product" 
                                                :options="products" 
                                                optionLabel="name" 
                                                placeholder="Select product..." 
                                                filter 
                                                class="!w-full !bg-zinc-950 !border-zinc-800 !h-12 !rounded-xl !text-xs font-mono"
                                            />
                                        </div>
                                    </template>
                                </Column>

                                <Column field="quantity" header="QUANTITY (+/-)" class="!py-6 !px-4">
                                    <template #body="{ index }">
                                        <div class="flex flex-col gap-1 items-center">
                                            <InputText 
                                                v-model="form.lines[index].quantity" 
                                                placeholder="0.00" 
                                                class="!w-24 !bg-zinc-950 !border-zinc-800 !h-12 !rounded-xl !px-4 !text-xs !font-mono text-center"
                                                :class="form.lines[index].quantity < 0 ? 'text-red-400' : 'text-emerald-400'"
                                            />
                                            <span class="text-[8px] font-bold text-zinc-600 font-mono tracking-widest">CHANGE AMOUNT</span>
                                        </div>
                                    </template>
                                </Column>

                                <Column header="EFFECT" class="!py-6 !px-8 text-right">
                                    <template #body="{ index }">
                                        <div class="flex items-center justify-end gap-10 font-mono">
                                             <div class="flex flex-col items-end">
                                                 <span class="text-[8px] font-bold text-zinc-700 uppercase tracking-widest">Current</span>
                                                 <span class="text-xs font-bold text-zinc-500">{{ form.lines[index].product?.total_qoh || 0 }}</span>
                                             </div>
                                             <div class="flex flex-col items-end">
                                                 <span class="text-[8px] font-bold text-zinc-700 uppercase tracking-widest">Adjustment</span>
                                                 <span :class="form.lines[index].quantity < 0 ? 'text-red-500' : 'text-emerald-500'" class="text-xs font-black">
                                                     {{ form.lines[index].quantity > 0 ? '+' : '' }}{{ form.lines[index].quantity || 0 }}
                                                 </span>
                                             </div>
                                             <button @click="removeLine(index)" class="w-8 h-8 rounded-lg hover:bg-red-500/10 text-zinc-700 hover:text-red-400 transition-all border border-transparent hover:border-red-500/20 ml-2">
                                                <i class="pi pi-trash text-[10px]" />
                                             </button>
                                        </div>
                                    </template>
                                </Column>

                                <template #empty>
                                    <div class="py-32 flex flex-col items-center justify-center opacity-10 filter grayscale">
                                        <i class="pi pi-sliders-h text-5xl mb-4" />
                                        <p class="text-[9px] font-black uppercase tracking-[0.4em] font-mono">No items added to adjustment</p>
                                    </div>
                                </template>
                            </DataTable>
                        </div>
                    </main>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';

const locations = ref([
    { id: 1, name: 'Warehouse Alpha', code: 'WHS-A' },
    { id: 2, name: 'Warehouse Beta', code: 'WHS-B' }
]);
const products = ref([]);

const loadProducts = async () => {
    try {
        const res = await axios.get('/api/products');
        products.value = res.data.data;
    } catch (e) {
        console.error('Failed to load products', e);
    }
};

onMounted(() => {
    loadProducts();
});
const reasons = ref([
    { label: 'Physical Count Difference', value: 'disc' },
    { label: 'Damaged Items', value: 'dmg' },
    { label: 'Lost / Stolen', value: 'loss' },
    { label: 'System Correction', value: 'err' }
]);

const form = useForm({
    location: { id: 1, name: 'Warehouse Alpha' },
    reason: { label: 'Physical Count Difference', id: 1 },
    notes: '',
    lines: []
});

const isSubmitting = ref(false);

const postAdjustment = async () => {
    isSubmitting.value = true;
    
    // Frontend validation for real-time stock checks (if adjusting down)
    for (const line of form.lines) {
        if (!line.product) continue;
        const adjustmentQty = parseFloat(line.quantity) || 0;
        const availableQty = line.product.total_qoh || 0;
        
        // If we are deducting more than we have
        if (adjustmentQty < 0 && Math.abs(adjustmentQty) > availableQty) {
            alert(`Insufficient stock for ${line.product.name} to perform this adjustment. Available: ${availableQty}, Trying to deduct: ${Math.abs(adjustmentQty)}`);
            isSubmitting.value = false;
            return;
        }
    }
    
    try {
        const payload = {
            header: {
                transaction_type_id: 4, // Ignored by backend as it overrides with ADJS
                transaction_status_id: 3, // Posted
                transaction_date: new Date().toISOString().split('T')[0],
                adjustment_reason_id: form.reason?.id || null,
                from_location_id: form.location?.id, // adjustments happen at a specific location
                to_location_id: form.location?.id, // usually adjustments affect only one location (or from/to are same)
                notes: form.notes,
            },
            lines: form.lines.map(line => ({
                product_id: line.product?.id,
                location_id: form.location?.id,
                quantity: parseFloat(line.quantity), // +/-
                unit_cost: parseFloat(line.product?.average_cost || 0)
            }))
        };
        
        await axios.post('/api/adjustments', payload);
        router.visit('/inventory-center');
    } catch (e) {
        console.error('Submission failed', e);
        alert(e.response?.data?.message || 'Failed to submit adjustment');
    } finally {
        isSubmitting.value = false;
    }
};

const addLine = () => {
    form.lines.push({ product: null, quantity: 0 });
};

const removeLine = (index) => {
    form.lines.splice(index, 1);
};
</script>

<style scoped>
.adj-grid :deep(.p-datatable-thead) {
    display: table-header-group !important;
}
.adj-grid :deep(.p-datatable-thead > tr > th) {
    background: rgba(24, 24, 27, 0.4) !important;
    border-color: rgba(39, 39, 42, 0.5) !important;
    color: rgba(113, 113, 122, 1) !important;
    font-size: 8px !important;
    text-transform: uppercase !important;
    font-weight: 900 !important;
    letter-spacing: 0.3em !important;
    padding: 1rem 2rem !important;
}
</style>
