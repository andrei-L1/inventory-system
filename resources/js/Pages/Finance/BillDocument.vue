<script setup>
import { ref, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import axios from 'axios';

const props = defineProps({
    id: { type: [String, Number], required: true }
});

const toast = useToast();
const bill = ref(null);
const loading = ref(true);

const fetchBill = async () => {
    loading.value = true;
    try {
        const response = await axios.get(`/api/bills/${props.id}`);
        bill.value = response.data;
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Could not fetch bill details', life: 3000 });
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchBill();
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(value);
};

const getStatusColor = (status) => {
    switch (status) {
        case 'DRAFT': return 'text-secondary bg-zinc-500/10 border-zinc-500/20';
        case 'POSTED': return 'text-sky-500 bg-sky-500/10 border-sky-500/20';
        case 'PAID': return 'text-emerald-500 bg-emerald-500/10 border-emerald-500/20';
        case 'VOID': return 'text-rose-500 bg-rose-500/10 border-rose-500/20';
        default: return 'text-secondary bg-zinc-500/10 border-zinc-500/20';
    }
};

const handlePost = async () => {
    if (!confirm('Officially post this bill to A/P? This recognizes the liability in your ledger.')) return;
    try {
        await axios.patch(`/api/bills/${props.id}/post`);
        toast.add({ severity: 'success', summary: 'Success', detail: 'Bill posted.', life: 3000 });
        fetchBill();
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Could not post bill.', life: 3000 });
    }
};

const handleVoid = async () => {
    if (!confirm('Are you sure you want to VOID this bill? This will reverse any linked impact.')) return;
    try {
        await axios.patch(`/api/bills/${props.id}/void`);
        toast.add({ severity: 'warn', summary: 'Voided', detail: 'Bill has been voided.', life: 3000 });
        fetchBill();
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: error.response?.data?.message || 'Could not void bill.', life: 3000 });
    }
};
</script>

<template>
    <AppLayout>
        <Head title="Vendor Bill Detail" />
        <Toast />

        <div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8 print:py-0 print:px-0">
            <!-- Headers / Actions (Hidden on Print) -->
            <div class="mb-6 flex justify-between items-end print:hidden">
                <div v-if="!loading">
                    <button @click="router.visit('/finance-center?mode=PAYABLE')" class="bg-transparent border-none text-amber-500 hover:text-amber-400 flex items-center gap-2 text-[11px] font-bold uppercase tracking-widest mb-6 transition-colors p-0 outline-none">
                        <i class="pi pi-arrow-left text-xs"></i> Back to Finance Center
                    </button>
                    <div class="flex items-center gap-4">
                        <h1 class="text-2xl font-bold text-primary tracking-tight mb-0">Record: {{ bill?.bill_number }}</h1>
                        <div class="inline-flex px-2 py-0.5 rounded border text-[10px] font-bold tracking-[0.1em] font-mono" :class="getStatusColor(bill?.status)">
                            {{ bill?.status }}
                        </div>
                    </div>
                </div>
                
                <div v-if="!loading" class="flex items-center gap-3">
                    <button @click="printDocument" class="bg-panel-hover hover:bg-zinc-700 text-primary px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-widest transition-colors flex items-center gap-2">
                        <i class="pi pi-print"></i> Print
                    </button>
                    
                    <button v-if="bill?.status === 'DRAFT'" @click="handlePost" class="bg-amber-500 hover:bg-amber-400 text-zinc-950 px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-widest transition-colors flex items-center gap-2">
                        <i class="pi pi-check" /> Post Bill
                    </button>
                    
                    <button v-if="['DRAFT', 'OPEN'].includes(bill?.status)" @click="handleVoid" class="bg-panel-hover hover:bg-rose-500 text-primary px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-widest transition-colors flex items-center gap-2">
                        <i class="pi pi-ban" /> Void
                    </button>
                </div>
            </div>

            <!-- Loader -->
            <div v-if="loading" class="py-32 flex flex-col items-center justify-center space-y-4">
                <i class="pi pi-spinner pi-spin text-4xl text-amber-500"></i>
                <p class="font-mono text-[10px] uppercase tracking-[0.3em] text-muted animate-pulse">Reconstructing Financial Object...</p>
            </div>

            <!-- Bill Voucher -->
            <div v-else-if="bill" class="bg-white text-zinc-900 rounded-2xl shadow-[0_32px_64px_-16px_rgba(0,0,0,0.5)] border-t-8 border-amber-500 overflow-hidden relative z-10 print:shadow-none print:border-none print:rounded-none">
                <div class="p-12">
                    
                    <div class="flex justify-between items-start border-b-2 border-zinc-200 pb-8 mb-8">
                        <div>
                            <h2 class="text-4xl font-black tracking-tighter text-zinc-900 uppercase">Vendor Bill</h2>
                            <p class="text-secondary font-mono text-sm mt-2">{{ bill.bill_number }}</p>
                        </div>
                        
                        <div class="text-right">
                            <div class="font-bold text-xl text-zinc-900">Nexus Logistics Corp.</div>
                            <div class="text-sm text-secondary mt-1">123 Corporate Ave, Matrix City</div>
                            <div class="text-sm text-secondary uppercase font-mono tracking-tighter">TAX ID: N/A</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-12 mb-8">
                        <!-- Bill From -->
                        <div>
                            <div class="text-xs font-bold uppercase tracking-widest text-secondary mb-2">Bill From</div>
                            <div class="font-bold text-lg text-zinc-900">{{ bill.vendor?.name }}</div>
                            <div class="text-sm text-muted mt-1 uppercase font-mono">{{ bill.vendor?.vendor_code }}</div>
                            <div class="text-xs text-secondary leading-relaxed font-bold mt-4 max-w-sm">
                                {{ bill.vendor?.address || 'Vendor Address not specified (N/A)' }}
                            </div>
                            <div class="text-[10px] font-bold text-secondary uppercase tracking-widest mt-2">TIN: {{ bill.vendor?.tax_number || 'N/A' }}</div>
                        </div>

                        <!-- Meta Info Grid -->
                        <div class="bg-zinc-50 rounded-lg p-4 border border-zinc-100 grid grid-cols-2 gap-4">
                            <div>
                                <div class="text-[10px] font-bold uppercase tracking-widest text-secondary">Date</div>
                                <div class="font-mono text-sm font-bold text-zinc-800">{{ bill.bill_date }}</div>
                            </div>
                            <div>
                                <div class="text-[10px] font-bold uppercase tracking-widest text-secondary">Due Date</div>
                                <div class="font-mono text-sm font-bold text-zinc-800">{{ bill.due_date || 'Upon Receipt' }}</div>
                            </div>
                            <div>
                                <div class="text-[10px] font-bold uppercase tracking-widest text-secondary">Ref. PO</div>
                                <div class="font-mono text-sm font-bold text-zinc-800">{{ bill.purchase_order?.po_number || 'N/A' }}</div>
                            </div>
                            <div>
                                <div class="text-[10px] font-bold uppercase tracking-widest text-secondary">Account</div>
                                <div class="font-mono text-sm font-bold text-zinc-800">AP-{{ bill.vendor_id.toString().padStart(4, '0') }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Lines Table -->
                    <div class="mb-8">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-y-2 border-zinc-200">
                                    <th class="py-3 px-2 text-xs font-bold uppercase tracking-widest text-secondary">Item Description</th>
                                    <th class="py-3 px-2 text-xs font-bold uppercase tracking-widest text-secondary text-right">Qty</th>
                                    <th class="py-3 px-2 text-xs font-bold uppercase tracking-widest text-secondary text-right">Unit Price</th>
                                    <th class="py-3 px-2 text-xs font-bold uppercase tracking-widest text-secondary text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100">
                                <tr v-for="line in bill.lines" :key="line.id" class="text-sm">
                                    <td class="py-3 px-2 font-bold text-zinc-800">{{ line.product?.name || line.notes }}</td>
                                    <td class="py-3 px-2 font-mono text-muted text-right">{{ Number(line.quantity).toFixed(8) }}</td>
                                    <td class="py-3 px-2 font-mono text-muted text-right" style="width: 15%">{{ formatCurrency(line.unit_price) }}</td>
                                    <td class="py-3 px-2 font-mono font-bold text-zinc-900 text-right" style="width: 20%">{{ formatCurrency(line.subtotal) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Totals Section -->
                    <div class="flex justify-end">
                        <div class="w-72">
                            <div class="flex justify-between py-2 border-b border-zinc-100">
                                <span class="text-xs font-bold uppercase tracking-widest text-secondary">Subtotal</span>
                                <span class="font-mono text-sm text-zinc-800">{{ formatCurrency(bill.total_amount) }}</span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-zinc-100">
                                <span class="text-xs font-bold uppercase tracking-widest text-secondary">Tax</span>
                                <span class="font-mono text-sm text-zinc-800">{{ formatCurrency(0) }}</span>
                            </div>
                            <div class="flex justify-between py-3 bg-zinc-50 px-3 -mx-3 mt-2 border border-zinc-200 rounded-lg">
                                <span class="text-xs font-black uppercase tracking-widest text-zinc-900">Total Charged</span>
                                <span class="font-mono text-lg font-black text-amber-600">{{ formatCurrency(bill.total_amount) }}</span>
                            </div>

                            <!-- Balance Block -->
                            <div class="mt-6 flex justify-between py-2 items-center">
                                <span class="text-xs font-bold uppercase tracking-widest text-secondary">Amount Paid</span>
                                <span class="font-mono text-sm text-emerald-600 font-bold">- {{ formatCurrency(bill.paid_amount) }}</span>
                            </div>
                            <div class="flex justify-between py-3 border-t-2 border-zinc-900 mt-1">
                                <span class="text-xs font-black uppercase tracking-widest text-zinc-900">Balance Due</span>
                                <span class="font-mono text-xl font-black text-zinc-900">
                                    {{ formatCurrency(bill.total_amount - bill.paid_amount) }}
                                </span>
                            </div>
                            <div v-if="bill.status === 'PAID'" class="mt-2 flex justify-end">
                                <div class="border-2 border-emerald-500 text-emerald-500 font-black tracking-[0.2em] uppercase px-4 py-1 rotate-[-5deg] text-xl opacity-80 inline-block">PAID IN FULL</div>
                            </div>
                        </div>
                    </div>

                    <!-- Auditor Footer -->
                    <div class="mt-16 pt-8 border-t border-zinc-100 flex justify-between items-center text-[10px] font-bold text-secondary uppercase tracking-[0.2em] font-mono">
                        <div class="flex items-center gap-3">
                            <i class="pi pi-shield text-amber-600/50"></i>
                            <span>Financial Record • ID: {{ bill.id }} • Secure Audit Path Verified</span>
                        </div>
                        <div>Generated on {{ new Date().toLocaleString() }}</div>
                    </div>

                </div>
            </div>
        </div>
    </AppLayout>
</template>


