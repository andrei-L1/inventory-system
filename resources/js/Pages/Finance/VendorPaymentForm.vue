<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Select from 'primevue/select';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import axios from 'axios';

const toast = useToast();

const vendors = ref([]);
const selectedVendor = ref(null);
const unpaidBills = ref([]);
const loadingVendors = ref(false);
const loadingBills = ref(false);
const submitting = ref(false);

const form = ref({
    vendor_id: null,
    amount: 0,
    payment_date: new Date().toISOString().split('T')[0],
    reference_number: '',
    payment_method: 'BANK TRANSFER',
    notes: '',
    allocations: []
});

const paymentMethods = ['BANK TRANSFER', 'CASH', 'CHECK', 'E-WALLET', 'OTHER'];

// Methods
const loadVendors = async () => {
    loadingVendors.value = true;
    try {
        const res = await axios.get('/api/vendors');
        vendors.value = res.data.data;
    } finally {
        loadingVendors.value = false;
    }
};

const loadUnpaidBills = async (vendorId) => {
    loadingBills.value = true;
    try {
        const res = await axios.get('/api/bills', {
            params: { vendor_id: vendorId, status: 'POSTED' }
        });
        unpaidBills.value = res.data.data.filter(b => Number(b.balance_due) > 0);
        // Initialize allocations
        form.value.allocations = unpaidBills.value.map(b => ({
            bill_id: b.id,
            bill_number: b.bill_number,
            bill_date: b.bill_date,
            balance_due: Number(b.balance_due),
            total_amount: Number(b.total_amount),
            amount: 0
        }));
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load outstanding bills.' });
    } finally {
        loadingBills.value = false;
    }
};

onMounted(() => {
    loadVendors();
});

watch(selectedVendor, (newVal) => {
    if (newVal) {
        form.value.vendor_id = newVal.id;
        loadUnpaidBills(newVal.id);
    } else {
        unpaidBills.value = [];
        form.value.allocations = [];
    }
});

const totalAllocated = computed(() => {
    return form.value.allocations.reduce((acc, curr) => acc + Number(curr.amount || 0), 0);
});

const unallocatedAmount = computed(() => {
    return Math.max(0, Number(form.value.amount) - totalAllocated.value);
});

const isOveralllocated = computed(() => {
    return totalAllocated.value > Number(form.value.amount);
});

const autoAllocate = () => {
    if (form.value.amount <= 0) return;
    
    let remaining = Number(form.value.amount);
    
    // Sort oldest first (using bill_date)
    const sorted = [...form.value.allocations].sort((a,b) => new Date(a.bill_date) - new Date(b.bill_date));
    
    for (const alloc of sorted) {
        alloc.amount = 0; // reset
        if (remaining <= 0) continue;
        
        const toPay = Math.min(remaining, Number(alloc.balance_due));
        alloc.amount = toPay;
        remaining -= toPay;
    }

    // sync back to original array if needed (though sorted is just a ref to the same objects if they're not deep cloned)
    // Actually, allocations is a plain array of objects. sorted is a copy of references. Modifying sorted modifies form.value.allocations.
};

const submit = async () => {
    if (!selectedVendor.value) return;
    if (form.value.amount <= 0) {
        toast.add({ severity: 'warn', summary: 'Invalid', detail: 'Payment amount must be greater than 0', life: 3000 });
        return;
    }
    if (isOveralllocated.value) {
        toast.add({ severity: 'error', summary: 'Invalid Allocation', detail: 'Allocation exceeds total disbursement amount.', life: 3000 });
        return;
    }

    submitting.value = true;
    try {
        await axios.post('/api/vendor-payments', {
            ...form.value
        });
        toast.add({ severity: 'success', summary: 'Success', detail: 'Disbursement recorded and allocated successfully.', life: 3000 });
        setTimeout(() => router.visit('/finance-center?mode=PAYABLE'), 1500);
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Failed to record payment.' });
    } finally {
        submitting.value = false;
    }
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(val || 0));
};

</script>

<template>
    <AppLayout>
        <Head title="Disbursement Entry" />
        <Toast />
        
        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <div class="mb-6 flex justify-between items-end">
                <div>
                    <button @click="router.visit('/finance-center?mode=PAYABLE')" class="bg-transparent border-none text-amber-500 hover:text-amber-400 flex items-center gap-2 text-[11px] font-bold uppercase tracking-widest mb-6 transition-colors p-0 outline-none">
                        <i class="pi pi-arrow-left text-xs"></i> Back to Finance Center
                    </button>
                    <h1 class="text-2xl font-bold text-white tracking-tight mb-1">Record Disbursement</h1>
                    <p class="text-zinc-400 text-sm">Post a vendor payment and allocate it against outstanding liabilities.</p>
                </div>
            </div>

            <!-- Global Form Error -->
            <div v-if="isOveralllocated" class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-lg flex items-center gap-3 mb-6 font-mono text-sm shadow-[0_0_15px_rgba(239,68,68,0.1)]">
                <i class="pi pi-exclamation-triangle"></i>
                <span>Allocation Exceeds Disbursement Amount! Please review allocations.</span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <!-- Left Col: Disbursement Details -->
                <div class="lg:col-span-4 space-y-6">
                    <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-6 shadow-xl sticky top-24">
                        <h3 class="text-white font-bold tracking-tight mb-6 flex items-center gap-2 text-lg border-b border-zinc-800 pb-4">
                            <i class="pi pi-wallet text-amber-500"></i>
                            Disbursement Details
                        </h3>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-zinc-500 uppercase tracking-widest mb-2">Vendor Entity</label>
                                <Select v-model="selectedVendor" :options="vendors" optionLabel="name" placeholder="Select Vendor" 
                                        class="w-full bg-zinc-950 border-zinc-800 text-white" 
                                        :loading="loadingVendors" filter />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-zinc-500 uppercase tracking-widest mb-2">Payment Amount</label>
                                <InputNumber v-model="form.amount" mode="currency" currency="PHP" locale="en-PH" 
                                             class="w-full" inputClass="bg-zinc-950 border-zinc-800 text-amber-500 font-bold text-xl text-right font-mono focus:border-amber-500 transition-colors w-full" />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-zinc-500 uppercase tracking-widest mb-2">Date</label>
                                <input type="date" v-model="form.payment_date" class="w-full bg-zinc-950 border border-zinc-800 text-white p-2.5 rounded-lg text-sm focus:border-amber-500 outline-none" />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-zinc-500 uppercase tracking-widest mb-2">Method</label>
                                <Select v-model="form.payment_method" :options="paymentMethods"
                                        class="w-full bg-zinc-950 border-zinc-800 text-white" />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-zinc-500 uppercase tracking-widest mb-2">Ref / Check No.</label>
                                <InputText v-model="form.reference_number" class="w-full bg-zinc-950 border-zinc-800 text-white" placeholder="e.g. CHK-10293" />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-zinc-500 uppercase tracking-widest mb-2">Internal Notes</label>
                                <textarea v-model="form.notes" rows="3" 
                                       class="w-full bg-zinc-950 border border-zinc-800 rounded-lg p-3 text-white text-xs focus:border-amber-500 transition-colors outline-none"
                                       placeholder="Additional details..."></textarea>
                            </div>

                            <div class="pt-4 border-t border-zinc-800 mt-4">
                                <button @click="submit" :disabled="submitting || !selectedVendor || form.amount <= 0 || isOveralllocated" 
                                        class="w-full px-6 py-4 rounded-xl font-bold text-[11px] uppercase tracking-widest transition-all shadow-[0_0_20px_rgba(245,158,11,0.15)] flex justify-center items-center gap-2"
                                        :class="isOveralllocated ? 'bg-red-500/20 text-red-400 border border-red-500/50 cursor-not-allowed' : 'bg-amber-500 hover:bg-amber-400 text-zinc-950 hover:shadow-[0_0_25px_rgba(245,158,11,0.3)]'">
                                    <i v-if="submitting" class="pi pi-spinner pi-spin"></i>
                                    Confirm Disbursement
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Col: Allocations -->
                <div class="lg:col-span-8 space-y-6">
                    <!-- Allocation Summary Card -->
                    <div v-if="selectedVendor" class="bg-zinc-900 border border-zinc-800 rounded-xl p-6 shadow-xl flex items-center justify-between">
                        <div class="flex gap-8">
                            <div class="flex flex-col">
                                <span class="text-zinc-500 text-[10px] font-bold uppercase tracking-widest mb-1">To Allocate</span>
                                <span class="text-white font-mono font-bold">{{ formatCurrency(totalAllocated) }}</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-zinc-500 text-[10px] font-bold uppercase tracking-widest mb-1">Unallocated Credit</span>
                                <span class="font-mono font-bold" :class="unallocatedAmount > 0 ? 'text-amber-500' : 'text-zinc-600'">
                                    {{ formatCurrency(unallocatedAmount) }}
                                </span>
                            </div>
                        </div>
                        <button @click="autoAllocate" v-if="form.allocations.length > 0 && form.amount > 0" class="px-4 py-2 bg-amber-500/10 border border-amber-500/20 text-amber-400 hover:bg-amber-500 hover:text-white rounded-lg text-xs font-bold uppercase tracking-widest transition-colors">
                            Auto Allocate
                        </button>
                    </div>

                    <!-- Bills Table -->
                    <div v-if="selectedVendor && form.allocations.length > 0" class="bg-zinc-900 border border-zinc-800 rounded-xl p-0 shadow-xl overflow-hidden">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="bg-zinc-950/50 border-b border-zinc-800">
                                    <th class="text-left text-[9px] font-bold text-zinc-500 uppercase tracking-widest py-4 px-6">Bill Number</th>
                                    <th class="text-right text-[9px] font-bold text-zinc-500 uppercase tracking-widest py-4 px-6">Bill Date</th>
                                    <th class="text-right text-[9px] font-bold text-zinc-500 uppercase tracking-widest py-4 px-6">Outstanding</th>
                                    <th class="text-right text-[9px] font-bold text-zinc-500 uppercase tracking-widest py-4 px-6" style="width: 200px">Apply Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="alloc in form.allocations" :key="alloc.bill_id" class="border-b border-zinc-800/50 hover:bg-white/[0.01] transition-colors">
                                    <td class="py-3 px-6">
                                        <span class="text-xs font-bold text-zinc-200 bg-zinc-950 px-2 py-1 rounded border border-zinc-800 font-mono">{{ alloc.bill_number }}</span>
                                    </td>
                                    <td class="py-3 px-6 text-right text-xs text-zinc-500 font-mono">{{ alloc.bill_date }}</td>
                                    <td class="py-3 px-6 text-right text-sm font-mono font-bold text-amber-500/80">{{ formatCurrency(alloc.balance_due) }}</td>
                                    <td class="py-2 px-6">
                                        <InputNumber v-model="alloc.amount" mode="currency" currency="PHP" locale="en-PH"
                                                     class="w-full" :min="0" :max="alloc.balance_due" fluid
                                                     inputClass="!bg-zinc-950 !border-zinc-800 !text-amber-500 !font-mono !text-sm !text-right !py-1.5 focus:!border-amber-500 transition-colors" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-else-if="selectedVendor" class="bg-zinc-900 border border-zinc-800 rounded-xl p-12 text-center opacity-50 relative overflow-hidden flex flex-col items-center">
                        <i class="pi pi-check-circle text-5xl text-amber-500 mb-4 opacity-50"></i>
                        <h3 class="text-white font-bold text-lg mb-2">No Payables Found!</h3>
                        <p class="text-zinc-500 text-sm">This vendor has no outstanding bills. Any payment recorded will be stored as unallocated credit.</p>
                    </div>

                    <div v-else class="h-full bg-zinc-900/40 border border-zinc-800/50 rounded-xl p-12 text-center flex flex-col items-center justify-center border-dashed opacity-70">
                        <i class="pi pi-user text-4xl text-zinc-600 mb-4"></i>
                        <h3 class="text-zinc-400 font-bold mb-1">No Vendor Selected</h3>
                        <p class="text-zinc-600 text-[11px] uppercase tracking-widest font-mono">Select a vendor to see outstanding bills</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
@reference "tailwindcss";

:deep(.p-inputnumber-input) {
    @apply focus:ring-1 focus:ring-amber-500 focus:border-amber-500 outline-none;
}
</style>
