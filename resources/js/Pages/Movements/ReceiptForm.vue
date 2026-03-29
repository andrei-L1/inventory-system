<template>
    <AppLayout>
        <Head title="Inventory Receipt" />
        
        <div class="p-8 bg-zinc-950 min-h-[calc(100vh-64px)] overflow-hidden flex flex-col">
            <div class="max-w-[1600px] w-full mx-auto mb-10 flex justify-between items-end">
                <div class="flex flex-col">
                    <span class="text-[10px] font-bold text-sky-400 uppercase tracking-[0.2em] block mb-2 font-mono">New Stock Entry</span>
                    <h1 class="text-3xl font-bold text-white tracking-tight m-0 mb-2">Receive Stock</h1>
                    <p class="text-zinc-500 text-sm max-w-2xl leading-relaxed">
                        Add new stock items into your warehouse. Verify items and quantities against the vendor's documentation.
                    </p>
                </div>
                
                <div class="flex items-center gap-4">
                    <button @click="router.visit('/inventory-center')" class="!bg-zinc-900 !border-zinc-800 !text-zinc-400 hover:!text-white !px-6 !h-12 !font-bold !text-[11px] uppercase tracking-widest transition-all rounded-xl border">
                        CANCEL
                    </button>
                    <button class="!bg-sky-500 !border-none !text-white !px-8 !h-12 !font-bold !text-[11px] uppercase tracking-widest shadow-lg shadow-sky-500/10 hover:!bg-sky-400 active:scale-95 transition-all rounded-xl">
                        RECEIVE ITEMS
                    </button>
                </div>
            </div>

            <div class="max-w-[1600px] w-full mx-auto flex-1 flex flex-col min-h-0">
                <!-- Summary Bar -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-6 shadow-xl backdrop-blur-sm">
                        <span class="text-[9px] font-bold text-zinc-600 uppercase tracking-widest font-mono mb-2 block text-center lg:text-left">TOTAL_ITEMS</span>
                        <div class="text-2xl font-bold text-white tracking-tight text-center lg:text-left">{{ form.lines.length.toString().padStart(2, '0') }}</div>
                    </div>
                    <div class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-6 shadow-xl backdrop-blur-sm">
                        <span class="text-[9px] font-bold text-zinc-600 uppercase tracking-widest font-mono mb-2 block text-center lg:text-left">TOTAL_PIECES</span>
                        <div class="text-2xl font-bold text-white tracking-tight text-center lg:text-left">{{ totalQty.toFixed(2) }}</div>
                    </div>
                    <div class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-6 shadow-xl backdrop-blur-sm">
                        <span class="text-[9px] font-bold text-zinc-600 uppercase tracking-widest font-mono mb-2 block text-center lg:text-left">TOTAL_VALUE</span>
                        <div class="text-2xl font-bold text-emerald-400 tracking-tight text-center lg:text-left">₱ {{ totalValue.toLocaleString() }}</div>
                    </div>
                    <div class="bg-zinc-900/30 border border-zinc-800/50 rounded-2xl p-6 border-l-4 border-l-sky-500 shadow-xl backdrop-blur-sm">
                        <span class="text-[9px] font-bold text-sky-500/80 uppercase tracking-widest font-mono mb-2 block text-center lg:text-left italic">LOCATION</span>
                        <div class="text-[11px] font-bold text-zinc-300 uppercase truncate text-center lg:text-left tracking-tight">{{ form.to_location?.name || 'NOT_SELECTED' }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-12 gap-8 flex-1 min-h-0">
                    <aside class="col-span-12 lg:col-span-3 flex flex-col min-h-0 bg-zinc-900/40 border border-zinc-800/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm">
                        <div class="p-6 border-b border-zinc-800 bg-zinc-900/60 flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <div class="w-1.5 h-1.5 rounded-full bg-sky-500"></div>
                                <span class="text-[10px] font-bold text-zinc-300 tracking-[0.2em] uppercase font-mono leading-none">General Info</span>
                            </div>
                        </div>
                        
                        <div class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-8">
                             <div class="flex flex-col gap-3">
                                 <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Supplier</label>
                                 <Select 
                                      v-model="form.vendor" 
                                      :options="vendors" 
                                      optionLabel="name" 
                                      placeholder="Select Vendor" 
                                      class="!w-full !bg-zinc-950 !border-zinc-800 !h-12 !rounded-xl !text-xs font-mono"
                                 />
                             </div>

                             <div class="flex flex-col gap-3">
                                 <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Reference / PO #</label>
                                 <InputText 
                                      v-model="form.reference_number" 
                                      placeholder="PO-XXXX" 
                                      class="!w-full !bg-zinc-950 !border-zinc-800 !h-12 !rounded-xl !px-4 !text-xs !font-mono text-white placeholder:!text-zinc-800"
                                 />
                             </div>

                             <div class="flex flex-col gap-3">
                                 <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Remarks</label>
                                 <textarea v-model="form.notes" placeholder="Remarks..." class="bg-zinc-950 border border-zinc-800 rounded-xl p-4 text-xs text-zinc-400 h-32 resize-none outline-none focus:border-sky-500/30 transition-all"></textarea>
                             </div>
                        </div>

                        <div class="p-6 bg-zinc-950/50 border-t border-zinc-800">
                             <div class="flex items-center justify-between">
                                 <div class="flex flex-col">
                                     <span class="text-white font-bold text-[10px] uppercase tracking-tight">Print Barcodes</span>
                                 </div>
                                 <ToggleSwitch v-model="form.print_label" />
                             </div>
                        </div>
                    </aside>

                    <main class="col-span-12 lg:col-span-9 flex flex-col min-h-0 bg-zinc-900/40 border border-zinc-800/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm">
                        <div class="p-6 border-b border-zinc-800 bg-zinc-900/60 flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <div class="w-1.5 h-1.5 rounded-full bg-emerald-500"></div>
                                <span class="text-[10px] font-bold text-zinc-300 tracking-[0.2em] uppercase font-mono leading-none">Items to Receive</span>
                            </div>
                            <button @click="addLine" class="px-6 h-10 rounded-xl bg-sky-500 text-white text-[10px] font-black uppercase tracking-widest hover:bg-sky-400 transition-all active:scale-95 flex items-center gap-2">
                                <i class="pi pi-plus text-[10px]" />
                                ADD ITEM
                            </button>
                        </div>

                        <div class="flex-1 overflow-y-auto custom-scrollbar">
                            <DataTable :value="form.lines" class="receipt-grid border-none" :pt="{
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
                                                placeholder="Search Catalog..." 
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

                                <Column field="unit_cost" header="COST PER UNIT" class="!py-6 !px-4">
                                    <template #body="{ index }">
                                        <div class="relative w-32">
                                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-zinc-700 text-[10px] font-mono">₱</span>
                                            <InputText 
                                                v-model="form.lines[index].unit_cost" 
                                                placeholder="0.00" 
                                                class="!w-full !bg-zinc-950 !border-zinc-800 !h-12 !rounded-xl !pl-8 !pr-4 !text-xs !font-mono text-emerald-400"
                                            />
                                        </div>
                                    </template>
                                </Column>

                                <Column header="SUBTOTAL" class="!py-6 !px-8 text-right">
                                    <template #body="{ index }">
                                        <div class="flex items-center justify-end gap-6">
                                            <span class="text-xs font-bold font-mono text-white">₱ {{ (form.lines[index].quantity * form.lines[index].unit_cost || 0).toLocaleString() }}</span>
                                            <button @click="removeLine(index)" class="w-8 h-8 rounded-lg hover:bg-red-500/10 text-zinc-700 hover:text-red-400 transition-all border border-transparent hover:border-red-500/20">
                                                <i class="pi pi-trash text-[10px]" />
                                            </button>
                                        </div>
                                    </template>
                                </Column>

                                <template #empty>
                                    <div class="py-32 flex flex-col items-center justify-center opacity-10 filter grayscale">
                                        <i class="pi pi-inbox text-5xl mb-4" />
                                        <p class="text-[9px] font-black uppercase tracking-[0.4em] font-mono">No Items Added</p>
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
import { Head, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import ToggleSwitch from 'primevue/toggleswitch';
import axios from 'axios';

const { props, url } = usePage();
const queryParams = computed(() => {
    const searchParams = new URLSearchParams(new URL(url, window.location.origin).search);
    return Object.fromEntries(searchParams.entries());
});

const vendors = ref([
    { id: 1, name: 'Cyberdyne Systems Research' },
    { id: 2, name: 'Tyrell Corp Manufacturing' },
    { id: 3, name: 'Weyland-Yutani Logistics' }
]);
const products = ref([]);
const loadingProducts = ref(false);

const form = ref({
    vendor: null,
    reference_number: '',
    to_location: { id: 1, name: 'Secure Vault Alpha' },
    notes: '',
    lines: [],
    print_label: true
});

const addLine = () => {
    form.value.lines.push({ product: null, quantity: 0, unit_cost: 0 });
};

const removeLine = (index) => {
    form.value.lines.splice(index, 1);
};

const totalQty = computed(() => form.value.lines.reduce((s, l) => s + (parseFloat(l.quantity) || 0), 0));
const totalValue = computed(() => form.value.lines.reduce((s, l) => s + ((parseFloat(l.quantity) || 0) * (parseFloat(l.unit_cost) || 0)), 0));

const loadProducts = async () => {
    loadingProducts.value = true;
    try {
        const res = await axios.get('/api/products');
        products.value = res.data.data;
        if (queryParams.value.product_id && products.value.length > 0) {
            const preselected = products.value.find(p => p.id == queryParams.value.product_id);
            if (preselected) {
                form.value.lines.push({ product: preselected, quantity: 1, unit_cost: preselected.average_cost || 0 });
            }
        }
    } catch (e) {
        console.error('Failed to load products', e);
    } finally {
        loadingProducts.value = false;
    }
};

onMounted(() => {
    loadProducts();
});
</script>

<style scoped>
.receipt-grid :deep(.p-datatable-thead) {
    display: table-header-group !important;
}
.receipt-grid :deep(.p-datatable-thead > tr > th) {
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
