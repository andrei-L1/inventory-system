<template>
    <AppLayout>
        <Head title="Inventory Transfer" />
        
        <div class="p-8 bg-zinc-950 min-h-[calc(100vh-64px)] overflow-hidden flex flex-col">
            <div class="max-w-[1600px] w-full mx-auto mb-10 flex justify-between items-end">
                <div class="flex flex-col">
                    <span class="text-[10px] font-bold text-violet-400 uppercase tracking-[0.2em] block mb-2 font-mono">Internal Movement</span>
                    <h1 class="text-3xl font-bold text-white tracking-tight m-0 mb-2">Move Items</h1>
                    <p class="text-zinc-500 text-sm max-w-2xl leading-relaxed">
                        Move stock between warehouses or locations. Both locations will be updated simultaneously upon posting.
                    </p>
                </div>
                
                <div class="flex items-center gap-4">
                    <button @click="router.visit('/inventory-center')" class="!bg-zinc-900 !border-zinc-800 !text-zinc-400 hover:!text-white !px-6 !h-12 !font-bold !text-[11px] uppercase tracking-widest transition-all rounded-xl border">
                        CANCEL
                    </button>
                    <button @click="submitForm" :disabled="isSubmitting" class="!bg-violet-500 !border-none !text-white !px-8 !h-12 !font-bold !text-[11px] uppercase tracking-widest shadow-lg shadow-violet-500/10 hover:!bg-violet-400 active:scale-95 transition-all rounded-xl disabled:opacity-50 disabled:cursor-not-allowed">
                        {{ isSubmitting ? 'PROCESSING...' : 'TRANSFER ITEMS' }}
                    </button>
                </div>
            </div>

            <div class="max-w-[1600px] w-full mx-auto flex-1 flex flex-col min-h-0">
                <!-- Transfer Route Map -->
                <div class="mb-10 p-10 rounded-3xl bg-zinc-900/20 border border-zinc-900 flex items-center justify-between gap-12 relative overflow-hidden group shadow-2xl backdrop-blur-sm">
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(139,92,246,0.05),transparent)] pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    
                    <!-- Source Location -->
                    <div class="z-10 flex flex-col items-start gap-4 flex-1">
                        <div class="w-16 h-16 rounded-2xl bg-zinc-950 border-2 border-zinc-900 flex items-center justify-center shadow-2xl relative transition-all group-hover:border-zinc-700">
                            <i class="pi pi-home text-zinc-700 text-xl" />
                        </div>
                        <div class="flex flex-col items-start">
                            <span class="text-[9px] font-black text-zinc-600 uppercase tracking-[0.2em] font-mono mb-1 leading-none">Source Location</span>
                            <span class="text-lg font-bold text-zinc-400 uppercase tracking-tight">{{ form.from_location?.name || 'SELECT SOURCE' }}</span>
                        </div>
                    </div>

                    <!-- Transfer Path -->
                    <div class="flex-[2] h-px bg-zinc-800 relative z-10 max-w-[600px] mx-10">
                        <div class="absolute -top-5 left-1/2 -translate-x-1/2 flex flex-col items-center">
                             <div class="px-6 py-2 rounded-full bg-zinc-950 border border-zinc-800 flex items-center gap-3 shadow-lg group-hover:border-violet-500/30 transition-all">
                                 <div class="w-2 h-2 rounded-full bg-violet-500 animate-pulse"></div>
                                 <span class="text-[9px] font-black text-violet-400 uppercase tracking-[0.4em] font-mono leading-none">READY TO MOVE</span>
                             </div>
                        </div>
                        <div class="absolute inset-0 bg-gradient-to-r from-transparent via-violet-500/30 to-transparent"></div>
                    </div>

                    <!-- Destination Location -->
                    <div class="z-10 flex flex-col items-end gap-4 flex-1 text-right">
                        <div class="w-16 h-16 rounded-2xl bg-zinc-950 border-2 border-violet-500/20 flex items-center justify-center shadow-[0_0_40px_rgba(139,92,246,0.1)] relative transition-all group-hover:border-violet-500/40">
                            <i class="pi pi-map-marker text-violet-400 text-xl" />
                        </div>
                        <div class="flex flex-col items-end">
                            <span class="text-[9px] font-black text-zinc-600 uppercase tracking-[0.2em] font-mono mb-1 leading-none">Destination</span>
                            <span class="text-lg font-bold text-violet-300 uppercase tracking-tight">{{ form.to_location?.name || 'SELECT TARGET' }}</span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-12 gap-8 flex-1 min-h-0">
                    <aside class="col-span-12 lg:col-span-3 flex flex-col min-h-0 bg-zinc-900/40 border border-zinc-800/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm">
                        <div class="p-6 border-b border-zinc-800 bg-zinc-900/60 flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <div class="w-1.5 h-1.5 rounded-full bg-violet-500"></div>
                                <span class="text-[10px] font-bold text-zinc-300 tracking-[0.2em] uppercase font-mono leading-none">Transfer Details</span>
                            </div>
                        </div>
                        
                        <div class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-8">
                             <div class="flex flex-col gap-3">
                                 <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">From Location</label>
                                 <Select 
                                      v-model="form.from_location" 
                                      :options="locations" 
                                      optionLabel="name" 
                                      placeholder="From..." 
                                      class="!w-full !bg-zinc-950 !border-zinc-800 !h-12 !rounded-xl !text-xs font-mono"
                                 />
                             </div>

                             <div class="flex flex-col gap-3">
                                 <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">To Location</label>
                                 <Select 
                                      v-model="form.to_location" 
                                      :options="locations" 
                                      optionLabel="name" 
                                      placeholder="To..." 
                                      class="!w-full !bg-zinc-950 !border-zinc-800 !h-12 !rounded-xl !text-xs font-mono"
                                 />
                             </div>

                             <div class="flex flex-col gap-3">
                                 <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Reference # <span class="text-zinc-700 normal-case font-sans tracking-normal">(Optional)</span></label>
                                 <InputText 
                                      v-model="form.reference_number" 
                                      placeholder="Leave blank to auto-generate" 
                                      class="!w-full !bg-zinc-950 !border-zinc-800 !h-12 !rounded-xl !px-4 !text-[10px] !font-mono text-white placeholder:!text-zinc-800"
                                 />
                             </div>

                             <div class="flex flex-col gap-3">
                                 <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Notes <span class="text-zinc-700 normal-case font-sans tracking-normal">(Optional)</span></label>
                                 <textarea v-model="form.notes" placeholder="Optional transfer notes..." class="bg-zinc-950 border border-zinc-800 rounded-xl p-4 text-xs text-zinc-400 h-24 resize-none outline-none focus:border-violet-500/30 transition-all"></textarea>
                             </div>

                             <div class="p-5 bg-violet-500/5 border border-violet-500/10 rounded-xl">
                                 <p class="text-[9px] text-zinc-600 leading-relaxed uppercase tracking-wider font-bold">Both locations will be updated instantly upon posting.</p>
                             </div>
                        </div>
                    </aside>

                    <main class="col-span-12 lg:col-span-9 flex flex-col min-h-0 bg-zinc-900/40 border border-zinc-800/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm">
                        <div class="p-6 border-b border-zinc-800 bg-zinc-900/60 flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <div class="w-1.5 h-1.5 rounded-full bg-violet-400"></div>
                                <span class="text-[10px] font-bold text-zinc-300 tracking-[0.2em] uppercase font-mono leading-none">Items to Move</span>
                            </div>
                            <button @click="addLine" class="px-6 h-10 rounded-xl bg-violet-500 text-white text-[10px] font-black uppercase tracking-widest hover:bg-violet-400 transition-all active:scale-95 flex items-center gap-2">
                                <i class="pi pi-plus text-[10px]" />
                                ADD ITEM
                            </button>
                        </div>

                        <div class="flex-1 overflow-y-auto custom-scrollbar">
                            <DataTable :value="form.lines" class="transfer-grid border-none" :pt="{
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
                                                placeholder="Search products..." 
                                                filter 
                                                class="!w-full !bg-zinc-950 !border-zinc-800 !h-12 !rounded-xl !text-xs font-mono"
                                            />
                                        </div>
                                    </template>
                                </Column>

                                <Column field="quantity" header="QUANTITY" class="!py-6 !px-4">
                                    <template #body="{ index }">
                                        <InputText 
                                            v-model="form.lines[index].quantity" 
                                            placeholder="0.00" 
                                            class="!w-24 !bg-zinc-950 !border-zinc-800 !h-12 !rounded-xl !px-4 !text-xs !font-mono text-center text-white"
                                        />
                                    </template>
                                </Column>

                                <Column header="STOCK EFFECT" class="!py-6 !px-8 text-right">
                                    <template #body="{ index }">
                                        <div class="flex items-center justify-end gap-8 font-mono">
                                            <div class="flex flex-col items-end gap-1">
                                                <span class="text-[8px] font-bold text-zinc-700 uppercase tracking-widest">Before</span>
                                                <span class="text-xs font-bold font-mono text-zinc-500">{{ form.lines[index].product?.total_qoh || 0 }}</span>
                                            </div>
                                            <i class="pi pi-arrow-right text-[10px] text-zinc-800" />
                                            <div class="flex flex-col items-end gap-1">
                                                <span class="text-[8px] font-bold text-zinc-700 uppercase tracking-widest">After</span>
                                                <span class="text-xs font-black font-mono text-violet-400">{{ ((form.lines[index].product?.total_qoh || 0) - (form.lines[index].quantity || 0)).toFixed(2) }}</span>
                                            </div>
                                            <button @click="removeLine(index)" class="w-8 h-8 rounded-lg hover:bg-red-500/10 text-zinc-700 hover:text-red-400 transition-all border border-transparent hover:border-red-500/20 ml-2">
                                                <i class="pi pi-trash text-[10px]" />
                                            </button>
                                        </div>
                                    </template>
                                </Column>

                                <template #empty>
                                    <div class="py-32 flex flex-col items-center justify-center opacity-10 filter grayscale">
                                        <i class="pi pi-arrow-right-arrow-left text-5xl mb-4" />
                                        <p class="text-[9px] font-black uppercase tracking-[0.4em] font-mono">No items added to transfer</p>
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
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';

const locations = ref([]);
const products = ref([]);
const loadingData = ref(false);

const loadData = async () => {
    loadingData.value = true;
    try {
        const [prodRes, locRes] = await Promise.all([
            axios.get('/api/products'),
            axios.get('/api/locations')
        ]);
        
        products.value = prodRes.data.data;
        locations.value = locRes.data.data;
        
        const { url } = usePage();
        const searchParams = new URLSearchParams(new URL(url, window.location.origin).search);
        const productId = searchParams.get('product_id');
        
        if (productId && products.value.length > 0) {
            const preselected = products.value.find(p => p.id == productId);
            if (preselected) {
                if (form.lines.length === 0) {
                    form.lines.push({ product: preselected, quantity: 1 });
                }
            }
        }
    } catch (e) {
        console.error('Failed to load data', e);
    } finally {
        loadingData.value = false;
    }
};

onMounted(() => {
    loadData();
});

const form = useForm({
    from_location: null,
    to_location: null,
    reference_number: '',
    lines: []
});

const isSubmitting = ref(false);

const submitForm = async () => {
    isSubmitting.value = true;
    
    // Frontend validation for real-time stock checks
    for (const line of form.lines) {
        if (!line.product) continue;
        const requestedQty = parseFloat(line.quantity) || 0;
        const availableQty = line.product.total_qoh || 0;
        if (requestedQty > availableQty) {
            alert(`Insufficient stock for ${line.product.name}. Available: ${availableQty}, Requested: ${requestedQty}`);
            isSubmitting.value = false;
            return;
        }
    }
    
    try {
        const payload = {
            header: {
                transaction_type_id: 3, // Transfer
                transaction_status_id: 3, // Posted
                transaction_date: new Date().toISOString().split('T')[0],
                reference_number: form.reference_number,
                notes: form.notes || 'Internal Transfer',
            },
            from_location_id: form.from_location?.id,
            to_location_id: form.to_location?.id,
            lines: form.lines.map(line => ({
                product_id: line.product?.id,
                quantity: parseFloat(line.quantity),
                unit_cost: parseFloat(line.product?.average_cost || 0)
            }))
        };
        
        await axios.post('/api/transfers', payload);
        router.visit('/inventory-center');
    } catch (e) {
        console.error('Submission failed', e);
        alert(e.response?.data?.message || 'Failed to submit form');
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
.transfer-grid :deep(.p-datatable-thead) {
    display: table-header-group !important;
}
.transfer-grid :deep(.p-datatable-thead > tr > th) {
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
