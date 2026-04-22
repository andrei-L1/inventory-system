<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import Popover from 'primevue/popover';
import Select from 'primevue/select';
import InputNumber from 'primevue/inputnumber';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";
import axios from 'axios';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({ id: [String, Number] });
const { can } = usePermissions();
const confirm = useConfirm();
const toast = useToast();

const po = ref(null);
const loading = ref(true);

const canBill = computed(() => {
    if (!po.value) return false;
    // Industry standard: Bill is possible if there is at least one received item that hasn't been billed yet
    return (['open', 'sent', 'in_transit', 'partially_received', 'closed'].includes(po.value.status)) &&
           po.value.lines.some(l => Number(l.received_qty) > Number(l.billed_qty || 0));
});

const approveLoading = ref(false);
const sendLoading = ref(false);
const shipLoading = ref(false);
const cancelLoading = ref(false);
const grnDialog = ref(false);
const grnDetailDialog = ref(false);
const returnDetailDialog = ref(false);
const selectedGrn = ref(null);
const selectedReturn = ref(null);
const shipDialog = ref(false);
const returnDialog = ref(false);
const grnLoading = ref(false);
const returnLoading = ref(false);
const locations = ref([]);
const uoms = ref([]);
const availableInventory = ref([]); // Stores { product_id, location_id, qoh, location_name, location_code }
const uomConversions = ref([]);
const loadingConversions = ref(false);

// H-1: Popover for per-location stock breakdown in the GRN dialog
const stockPopover = ref(null);
const popoverLine = ref(null);

const toggleStockInfo = (event, line) => {
    popoverLine.value = line;
    stockPopover.value.toggle(event);
};
// UOM Configs

const selectedLineForStock = ref(null);

const fetchProductInventory = async (line) => {
    if (!line.po_line_id) return;
    const poLine = po.value.lines.find(l => l.id === line.po_line_id);
    if (!poLine) return;
    try {
        const res = await axios.get(`/api/inventory/${poLine.product_id}/locations`);
        line.inventories = res.data.data;
    } catch (e) {
        console.error('Failed to fetch inventories', e);
    }
};

const getScaledQty = (line, rawPieces) => {
    if (rawPieces === undefined || rawPieces === null) return '0';
    const factor = Number(getFactorToBase(line.uom_id, line.product_id).factor);
    const scaled = Number(rawPieces) / factor;
    const uom = uoms.value.find(u => u.id === line.uom_id);
    return (uom?.category === 'count')
        ? Math.floor(scaled + 0.0001).toString()
        : scaled.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: uom?.decimals ?? 8 });
};

const getLocalStock = (line) => {
    if (!line.inventories || !grnForm.value.location_id) return 0;
    const inv = line.inventories.find(i => Number(i.location_id) === Number(grnForm.value.location_id));
    return inv ? Number(inv.quantity_on_hand) : 0;
};

const getUomAbbr = (id) => {
    const uom = uoms.value.find(u => u.id === id);
    return uom ? uom.abbreviation : '';
};

const isUomIdDiscrete = (id) => {
    const uom = uoms.value.find(u => u.id === id);
    return uom ? uom.category === 'count' : true;
};

const getFilteredUoms = (line) => {
    let list = uoms.value;
    
    // Determine the category of the line's current UOM
    const currentUom = uoms.value.find(u => u.id === line.uom_id);
    if (currentUom && currentUom.category) {
        list = uoms.value.filter(u => u.category === currentUom.category);
    } else {
        // Safe fallback using id-based check mapped up to line's uom if resolving fails
        const isCount = isUomIdDiscrete(line.uom_id);
        list = uoms.value.filter(u => u.category === (isCount ? 'count' : u.category));
    }

    return list.map(u => {
        // Find conversion rule for this UOM and this specific product safely with Number casting
        let rule = uomConversions.value.find(c => Number(c.from_uom_id) === Number(u.id) && c.product_id !== null && Number(c.product_id) === Number(line.product_id));
        let isCustom = !!rule;
        
        // Fallback to global rule
        if (!rule) {
            rule = uomConversions.value.find(c => Number(c.from_uom_id) === Number(u.id) && c.product_id === null);
        }

        let conversion_text = '';
        if (rule) {
            const toUom = uoms.value.find(tu => tu.id === rule.to_uom_id);
            if (toUom) {
                conversion_text = `× ${Number(rule.conversion_factor)} ${toUom.abbreviation}`;
            }
        }

        return {
            ...u,
            conversion_text,
            is_custom: isCustom
        };
    });
};

const getFactorToBase = (uomId, productId = null) => {
    if (!uomId) return { factor: 1, baseId: null };
    let factor = 1.0;
    let current = Number(uomId);
    let processed = [current];
    while (true) {
        let rule = null;
        if (productId) {
            rule = uomConversions.value.find(c => Number(c.from_uom_id) === current && c.product_id === productId);
        }
        if (!rule) {
            rule = uomConversions.value.find(c => Number(c.from_uom_id) === current && c.product_id === null);
        }
        
        if (!rule || processed.includes(Number(rule.to_uom_id))) break;
        factor = Number(factor) * Number(rule.conversion_factor);
        current = Number(rule.to_uom_id);
        processed.push(current);
    }
    return { factor, baseId: current };
};

const onGrnUomChange = (line) => {
    const poLine = po.value.lines.find(l => l.id === line.po_line_id);
    if (!poLine) return;

    const targetInfo = getFactorToBase(line.uom_id, line.product_id);
    const poBaseInfo = getFactorToBase(poLine.uom_id, poLine.product_id);

    if (Number(targetInfo.baseId) === Number(poBaseInfo.baseId)) {
        const effectiveFactor = Number(poBaseInfo.factor) / Number(targetInfo.factor);
        line.received_qty = Number(poLine.pending_qty) * effectiveFactor;
        return;
    }

    toast.add({ severity: 'warn', summary: 'No Conversion', detail: 'No common base unit found for this UOM pairing.', life: 4000 });
};

const onReturnUomChange = (line) => {
    const poLine = po.value.lines.find(l => l.id === line.po_line_id);
    if (!poLine) return;

    const targetInfo = getFactorToBase(line.uom_id, line.product_id);
    const poBaseInfo = getFactorToBase(poLine.uom_id, poLine.product_id);

    if (Number(targetInfo.baseId) === Number(poBaseInfo.baseId)) {
        const effectiveFactor = Number(poBaseInfo.factor) / Number(targetInfo.factor);
        // L-3: Scale the current return_qty to the new UOM instead of resetting to max.
        line.return_qty = Number(line.received_qty_in_po_unit) * effectiveFactor;
        return;
    }

    toast.add({ severity: 'warn', summary: 'No Conversion', detail: 'No common base unit found for this UOM pairing.', life: 4000 });
};

const grnForm = ref({
    location_id: null,
    lines: []
});
const returnForm = ref({
    location_id: null,
    lines: []
});
const shipForm = ref({
    carrier: '',
    tracking_number: ''
});

const loadPO = async () => {
    if (!props.id || props.id === 'undefined') {
        console.error("Invalid Purchase Order ID detected at boot.");
        router.visit('/purchase-orders');
        return;
    }
    
    loading.value = true;
    try {
        const res = await axios.get(`/api/purchase-orders/${props.id}`);
        po.value = res.data.data;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Could not load PO', life: 3000 });
        router.visit('/purchase-orders');
    } finally {
        loading.value = false;
    }
};

const loadLocations = async () => {
    try {
        const res = await axios.get('/api/locations?limit=1000');
        locations.value = res.data.data;
    } catch (e) {
        console.error(e);
    }
};

const loadUoms = async () => {
    try {
        const res = await axios.get('/api/uom?limit=1000');
        uoms.value = res.data.data;
    } catch (e) {
        console.error(e);
    }
};

const loadConversions = async () => {
    try {
        const res = await axios.get('/api/uom-conversions?limit=1000');
        uomConversions.value = res.data.data;
    } catch (e) {
        console.error(e);
    }
};

onMounted(() => {
    loadPO();
    loadLocations();
    loadUoms();
    loadConversions();
});

const getStatusColor = (statusName) => {
    const map = { 
        'draft': 'warning', 
        'open': 'info', 
        'sent': 'info',
        'in_transit': 'help',
        'partially_received': 'help', 
        'closed': 'success', 
        'cancelled': 'danger' 
    };
    return map[statusName] || 'info';
};

const approve = async () => {
    confirm.require({
        message: 'Are you sure you want to approve this Purchase Order? It will be moved to Open status.',
        header: 'Confirm Approval',
        icon: 'pi pi-check-circle',
        acceptClass: 'p-button-success',
        accept: async () => {
            try {
                approveLoading.value = true;
                await axios.patch(`/api/purchase-orders/${po.value.id}/approve`);
                toast.add({ severity: 'success', summary: 'Approved', detail: 'PO is now Open', life: 3000 });
                loadPO();
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Approval failed', life: 3000 });
            } finally {
                approveLoading.value = false;
            }
        }
    });
};

const sendPO = async () => {
    try {
        sendLoading.value = true;
        await axios.patch(`/api/purchase-orders/${po.value.id}/send`);
        toast.add({ severity: 'success', summary: 'Success', detail: 'PO marked as Sent to Vendor', life: 3000 });
        loadPO();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Send failed', life: 3000 });
    } finally {
        sendLoading.value = false;
    }
};

const submitShipment = async () => {
    if (!shipForm.value.carrier) {
        toast.add({ severity: 'warn', summary: 'Warning', detail: 'Carrier is required', life: 3000 });
        return;
    }
    try {
        shipLoading.value = true;
        await axios.post(`/api/purchase-orders/${po.value.id}/ship`, shipForm.value);
        toast.add({ severity: 'success', summary: 'Success', detail: 'Order marked as In Transit', life: 3000 });
        shipDialog.value = false;
        loadPO();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Shipment record failed', life: 3000 });
    } finally {
        shipLoading.value = false;
    }
};

const openGrnMode = async () => {
    grnLoading.value = true;
    try {
        const productIds = po.value.lines.map(l => l.product_id);
        const inventoryRes = await Promise.all(
            productIds.map(id => axios.get(`/api/inventory/${id}/locations`))
        );
        
        const invMap = {};
        productIds.forEach((id, index) => {
            invMap[id] = inventoryRes[index].data.data;
        });

        grnForm.value.location_id = null;
        grnForm.value.lines = po.value.lines
            .filter(l => l.pending_qty > 0)
            .map(l => ({
                po_line_id: l.id,
                product_id: l.product_id,
                sku: l.sku,
                product_name: l.product_name,
                product_code: l.product_code,
                pending_qty: Number(l.pending_qty),
                formatted_pending_qty: l.formatted_pending_qty,
                received_qty: Number(l.pending_qty),
                unit: l.uom ?? l.base_uom ?? '???',
                uom_id: l.uom_id,
                product_uom: l.uom ?? l.base_uom ?? '???',
                inventories: invMap[l.product_id] || []
            }));
        
        if (grnForm.value.lines.length === 0) {
            toast.add({ severity: 'info', summary: 'Completed', detail: 'All lines are fully received', life: 3000 });
            return;
        }
        
        grnDialog.value = true;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Could not fetch inventory distribution', life: 3000 });
    } finally {
        grnLoading.value = false;
    }
};

const viewGrnDetails = (receipt) => {
    selectedGrn.value = receipt;
    grnDetailDialog.value = true;
};

const viewReturnDetails = (ret) => {
    selectedReturn.value = ret;
    returnDetailDialog.value = true;
};

const postReceipt = async () => {
    if (!grnForm.value.location_id) {
        toast.add({ severity: 'warn', summary: 'Validation', detail: 'Destination Location is required', life: 3000 });
        return;
    }

    const payloadLines = grnForm.value.lines.filter(l => Number(l.received_qty) > 0);
    if (payloadLines.length === 0) {
        toast.add({ severity: 'warn', summary: 'Validation', detail: 'No quantities specified to receive', life: 3000 });
        return;
    }

    grnLoading.value = true;
    try {
        await axios.post(`/api/purchase-orders/${po.value.id}/receive`, {
            location_id: grnForm.value.location_id,
            lines: payloadLines.map(l => ({ 
                po_line_id: l.po_line_id, 
                received_qty: l.received_qty,
                uom_id: l.uom_id
            }))
        });
        toast.add({ severity: 'success', summary: 'Success', detail: 'Goods Receipt Note posted!', life: 3000 });
        grnDialog.value = false;
        loadPO();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'GRN failed', life: 3000 });
    } finally {
        grnLoading.value = false;
    }
};

const openReturnMode = async () => {
    returnLoading.value = true;
    try {
        // Fetch current inventory distribution for all products on this PO
        const productIds = po.value.lines.map(l => l.product_id);
        const inventoryRes = await Promise.all(
            productIds.map(id => axios.get(`/api/inventory?product_id=${id}&limit=100`))
        );
        
        availableInventory.value = inventoryRes.flatMap(res => res.data.data);

        returnForm.value.location_id = null;
        returnForm.value.lines = po.value.lines
            .filter(l => l.received_qty > 0)
            .map(l => ({
                po_line_id: l.id,
                product_id: l.product_id, // ensure ID is available for filtering
                product_name: l.product_name,
                sku: l.sku,
                received_qty: Number(l.received_qty),
                formatted_received_qty: l.formatted_received_qty,
                received_qty_in_po_unit: Number(l.received_qty), // Keep track of base received qty
                uom: l.uom,
                uom_id: l.uom_id,
                product_uom: l.uom ?? l.base_uom ?? '???',
                return_qty: 0,
                resolution: 'replacement',
                reason: ''
            }));
        
        if (returnForm.value.lines.length === 0) {
            toast.add({ severity: 'warn', summary: 'Cannot Return', detail: 'No items have been received for this PO.', life: 3000 });
            return;
        }
        
        returnDialog.value = true;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Could not fetch inventory distribution', life: 3000 });
    } finally {
        returnLoading.value = false;
    }
};

const filteredReturnLocations = computed(() => {
    if (availableInventory.value.length === 0) return [];
    
    // Get unique location IDs that have stock for any of the products in the PO
    const locIdsWithStock = new Set(availableInventory.value.map(inv => inv.location?.id).filter(Boolean));
    return locations.value.filter(loc => locIdsWithStock.has(loc.id));
});

const getStockInSelectedLocation = (productId) => {
    if (!returnForm.value.location_id) return 0;
    const inv = availableInventory.value.find(
        i => Number(i.product?.id) === Number(productId) && Number(i.location?.id) === Number(returnForm.value.location_id)
    );
    return inv ? Number(inv.quantity_on_hand) : 0;
};

const postReturn = async () => {
    if (!returnForm.value.location_id) {
        toast.add({ severity: 'warn', summary: 'Validation', detail: 'Return Location is required', life: 3000 });
        return;
    }

    const payloadLines = returnForm.value.lines.filter(l => Number(l.return_qty) > 0);
    if (payloadLines.length === 0) {
        toast.add({ severity: 'warn', summary: 'Validation', detail: 'Please specify items and quantities to return.', life: 3000 });
        return;
    }

    returnLoading.value = true;
    try {
        await axios.post(`/api/purchase-orders/${po.value.id}/return`, {
            location_id: returnForm.value.location_id,
            lines: payloadLines.map(l => ({ 
                po_line_id: l.po_line_id, 
                return_qty: l.return_qty,
                uom_id: l.uom_id,
                resolution: l.resolution,
                reason: l.reason
            }))
        });
        toast.add({ severity: 'success', summary: 'RTV Success', detail: 'Purchase Return processed successfully.', life: 3000 });
        returnDialog.value = false;
        loadPO();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Return failed', life: 3000 });
    } finally {
        returnLoading.value = false;
    }
};

const deletePO = async () => {
    confirm.require({
        message: 'Permanently delete this draft PO?',
        header: 'Confirm Deletion',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await axios.delete(`/api/purchase-orders/${po.value.id}`);
                toast.add({ severity: 'success', summary: 'Deleted', detail: 'PO removed', life: 3000 });
                router.visit('/purchase-orders');
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Deletion failed', life: 3000 });
            }
        }
    });
};

const cancelPO = async () => {
    confirm.require({
        message: 'Are you sure you want to cancel this Purchase Order? This action cannot be undone.',
        header: 'Confirm Cancellation',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                cancelLoading.value = true;
                await axios.patch(`/api/purchase-orders/${po.value.id}/cancel`);
                toast.add({ severity: 'success', summary: 'Cancelled', detail: 'Purchase Order has been cancelled.', life: 3000 });
                loadPO();
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Cancellation failed', life: 3000 });
            } finally {
                cancelLoading.value = false;
            }
        }
    });
};

const openPrint = () => {
    window.open(`/purchase-orders/${po.value.id}/print`, '_blank');
};
</script>

<template>
    <Head :title="po ? po.po_number : 'Loading...'" />
    <AppLayout v-if="po">
        <div class="h-full flex flex-col gap-4">

            <!-- Header Panel -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-4 bg-panel/40 border border-panel-border/80 rounded-2xl shadow-xl relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-orange-500/5 blur-[100px] pointer-events-none"></div>

                <div class="flex items-center gap-4 z-10">
                    <button @click="router.visit('/purchase-orders')" class="w-10 h-10 rounded-xl bg-deep border border-panel-border flex items-center justify-center text-secondary hover:text-primary transition-colors hover:border-zinc-600">
                        <i class="pi pi-arrow-left"></i>
                    </button>
                    <div>
                        <div class="flex items-center gap-3 mb-1">
                            <h1 class="text-primary text-2xl font-black tracking-tight font-mono">{{ po.po_number }}</h1>
                            <Tag 
                                :severity="getStatusColor(po.status)" 
                                :value="po.status.replace('_', ' ').toUpperCase()" 
                                class="text-[9px] font-bold tracking-widest font-mono uppercase px-2 py-0.5 rounded shadow-[inset_0_1px_4px_rgba(0,0,0,0.5)]"
                            />
                        </div>
                        <p class="text-[10px] font-bold tracking-[0.2em] uppercase font-mono">
                            <span @click="router.visit(`/vendor-center?vendor_id=${po.vendor_id}`)" class="text-secondary hover:text-sky-400 cursor-pointer transition-colors">{{ po.vendor_name }}</span>
                            <span class="text-muted mx-2">&bull;</span>
                            <span class="text-secondary">₱{{ po.formatted_total_amount }}</span>
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3 z-10">
                    <!-- Actions based on status -->
                    <Button 
                        v-if="po.status === 'draft' && can('manage-purchase-orders')" 
                        icon="pi pi-trash" 
                        class="p-button-danger p-button-text p-button-sm !font-bold" 
                        @click="deletePO"
                    />

                    <Button 
                        v-if="po.status === 'draft' && can('manage-purchase-orders')" 
                        label="Edit" 
                        icon="pi pi-pen-to-square" 
                        class="p-button-sm !bg-panel-hover hover:!bg-zinc-700 !text-zinc-300 !border-zinc-700 font-bold tracking-widest uppercase font-mono transition-all" 
                        @click="router.visit(`/purchase-orders/${po.id}/edit`)"
                    />
                    
                    <Button 
                        v-if="po.status === 'draft' && can('manage-purchase-orders')" 
                        label="Approve Order" 
                        icon="pi pi-check" 
                        :loading="approveLoading"
                        class="p-button-sm !bg-emerald-500/20 hover:!bg-emerald-500/30 !text-emerald-400 !border-emerald-500/50 font-bold tracking-widest uppercase font-mono transition-all" 
                        @click="approve"
                    />

                    <Button 
                        v-if="po.status === 'open' && can('manage-purchase-orders')" 
                        label="Send to Vendor" 
                        icon="pi pi-send" 
                        :loading="sendLoading"
                        class="p-button-sm !bg-sky-500/20 hover:!bg-sky-500/30 !text-sky-400 !border-sky-500/50 font-bold tracking-widest uppercase font-mono transition-all" 
                        @click="sendPO"
                    />

                    <Button 
                        v-if="['open', 'sent'].includes(po.status) && can('manage-purchase-orders')" 
                        label="Mark Shipped" 
                        icon="pi pi-truck" 
                        class="p-button-sm !bg-panel-hover hover:!bg-zinc-700 !text-zinc-300 !border-zinc-700 font-bold tracking-widest uppercase font-mono transition-all" 
                        @click="shipDialog = true"
                    />

                    <!-- M-1: Cancel button — only for non-terminal states with no received stock -->
                    <Button 
                        v-if="['draft', 'open', 'sent', 'in_transit'].includes(po.status) && can('manage-purchase-orders')" 
                        label="Cancel PO" 
                        icon="pi pi-ban" 
                        :loading="cancelLoading"
                        class="p-button-sm !bg-panel-hover hover:!bg-red-900/30 !text-red-400 !border-red-500/20 font-bold tracking-widest uppercase font-mono transition-all" 
                        @click="cancelPO"
                    />

                    <!-- L-4: po.status is a plain string from the API; removed redundant .name fallback -->
                    <Button 
                        v-if="['open', 'sent', 'in_transit', 'partially_received', 'closed'].includes(po.status) && po.lines.some(l => Number(l.received_qty) > 0) && can('manage-purchase-orders')" 
                        label="Return Items (RTV)" 
                        icon="pi pi-replay" 
                        class="p-button-sm !bg-panel-hover hover:!bg-red-900/40 !text-red-400 !border-red-500/30 font-bold tracking-widest uppercase font-mono transition-all" 
                        @click="openReturnMode"
                    />

                    <Button 
                        v-if="canBill && can('manage-purchase-orders')" 
                        label="Convert to Bill" 
                        icon="pi pi-file-export" 
                        class="p-button-sm !bg-amber-500 hover:!bg-amber-400 !text-zinc-950 font-black tracking-widest uppercase font-mono transition-all shadow-[0_0_20px_rgba(245,158,11,0.2)]" 
                        @click="router.visit(`/finance/bills/create?from_po=${po.id}`)"
                    />
                    
                    <Button 
                        v-if="['open', 'sent', 'in_transit', 'partially_received', 'closed'].includes(po.status) && can('manage-purchase-orders')" 
                        label="Print PO" 
                        icon="pi pi-print" 
                        class="p-button-sm !bg-white/5 hover:!bg-white/10 !text-primary !border-white/10 font-bold tracking-widest uppercase font-mono transition-all" 
                        @click="openPrint"
                    />

                    <Button 
                        v-if="['open', 'sent', 'in_transit', 'partially_received'].includes(po.status) && can('manage-purchase-orders')" 
                        label="Receive Stock (GRN)" 
                        icon="pi pi-download" 
                        class="p-button-sm !bg-orange-500 hover:!bg-orange-600 !border-none !text-zinc-950 font-bold shadow-[0_0_15px_rgba(249,115,22,0.3)] tracking-widest uppercase font-mono transition-all" 
                        @click="openGrnMode"
                    />
                </div>
            </div>

            <!-- Details Board -->
            <div class="grid grid-cols-12 gap-4">
                <!-- Meta Info Side -->
                <div class="col-span-12 lg:col-span-3 flex flex-col gap-4">
                    <div class="bg-panel/40 border border-panel-border/80 rounded-2xl p-4 shadow-xl flex flex-col gap-3">
                        <span class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono border-b border-panel-border/50 pb-2">Order Metadata</span>
                        
                        <div class="flex justify-between items-center py-1.5 border-b border-panel-border/30">
                            <span class="text-[10px] font-bold text-secondary font-mono tracking-widest uppercase">Drafted By</span>
                            <span class="text-xs font-bold text-primary">{{ po.created_by }}</span>
                        </div>
                        <div class="flex justify-between items-center py-1.5 border-b border-panel-border/30">
                            <span class="text-[10px] font-bold text-secondary font-mono tracking-widest uppercase">Created On</span>
                            <span class="text-xs font-bold text-secondary">{{ po.created_at }}</span>
                        </div>
                        <div v-if="po.approved_by" class="flex flex-col gap-2 py-2 border-b border-panel-border/30">
                            <div class="flex justify-between items-center">
                                <span class="text-[10px] font-bold text-secondary font-mono tracking-widest uppercase">Approved By</span>
                                <span class="text-xs font-bold text-emerald-400">{{ po.approved_by }}</span>
                            </div>
                            <div v-if="po.approved_at" class="flex justify-between items-center mt-1">
                                <span class="text-[9px] font-bold text-muted font-mono tracking-tighter uppercase">Approved At</span>
                                <span class="text-[10px] font-bold text-secondary uppercase">{{ po.approved_at }}</span>
                            </div>
                        </div>

                        <!-- Workflow Timestamps -->
                        <div v-if="po.sent_at" class="flex justify-between items-center py-2 border-b border-panel-border/30">
                            <span class="text-[10px] font-bold text-secondary font-mono tracking-widest uppercase">Sent To Vendor</span>
                            <span class="text-xs font-bold text-sky-400">{{ po.sent_at }}</span>
                        </div>

                        <div v-if="po.shipped_at" class="flex flex-col gap-2 py-2 border-b border-panel-border/30">
                            <div class="flex justify-between items-center">
                                <span class="text-[10px] font-bold text-secondary font-mono tracking-widest uppercase">Shipped On</span>
                                <span class="text-xs font-bold text-orange-400">{{ po.shipped_at }}</span>
                            </div>
                            <div class="flex flex-col gap-1 mt-1 p-2 bg-deep rounded border border-panel-border">
                                <div class="flex justify-between text-[10px]">
                                    <span class="text-muted font-bold uppercase tracking-tighter">Carrier:</span>
                                    <span class="text-zinc-300 font-bold">{{ po.carrier }}</span>
                                </div>
                                <div class="flex justify-between text-[10px]">
                                    <span class="text-muted font-bold uppercase tracking-tighter">Tracking:</span>
                                    <span class="text-sky-500 font-mono">{{ po.tracking_number || 'N/A' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-between items-center py-2 border-b border-panel-border/30">
                            <span class="text-[10px] font-bold text-secondary font-mono tracking-widest uppercase">Latest Receiver</span>
                            <span class="text-xs font-bold" :class="po.receipts.length > 0 ? 'text-orange-400' : 'text-muted'">
                                {{ po.receipts.length > 0 ? po.receipts[po.receipts.length - 1].received_by : 'N/A' }}
                            </span>
                        </div>
                        <div v-if="po.notes" class="flex flex-col gap-2 pt-2">
                            <span class="text-[10px] font-bold text-secondary font-mono tracking-widest uppercase">Notes</span>
                            <p class="text-xs text-secondary leading-relaxed bg-deep/50 p-3 rounded-lg border border-panel-border/50">{{ po.notes }}</p>
                        </div>
                    </div>

                    <!-- Receipt History (GRN) -->
                    <div v-if="po.receipts && po.receipts.length > 0" class="bg-panel/40 border border-panel-border/80 rounded-2xl p-4 shadow-xl flex flex-col gap-3 animate-in fade-in slide-in-from-left duration-700">
                        <span class="text-[10px] font-bold text-sky-500 uppercase tracking-widest font-mono border-b border-white/[0.03] pb-2">Goods Receipt History (GRN)</span>
                        
                        <div v-for="receipt in po.receipts" :key="receipt.id" class="flex flex-col gap-3 p-4 bg-deep/50 rounded-xl border border-panel-border/50 group hover:border-sky-500/20 transition-all">
                            <div class="flex justify-between items-center">
                                <span @click="viewGrnDetails(receipt)" 
                                      class="text-[10px] font-mono text-sky-400 font-bold cursor-pointer hover:underline">{{ receipt.reference_number }}</span>
                                <span class="text-[9px] font-mono text-secondary">{{ receipt.received_at }}</span>
                            </div>
                            <div class="flex flex-col gap-1">
                                <div class="flex justify-between text-[11px]">
                                    <span class="text-muted font-bold uppercase tracking-tighter text-[9px]">Logistics:</span>
                                    <span class="text-zinc-300 font-bold text-[10px]">{{ receipt.received_by }}</span>
                                </div>
                                <div class="flex justify-between text-[11px]">
                                    <span class="text-muted font-bold uppercase tracking-tighter text-[9px]">Receipt Bin:</span>
                                    <span class="text-secondary text-[10px]">{{ receipt.to_location }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Return History (PRN) -->
                    <div v-if="po.returns && po.returns.length > 0" class="bg-panel/40 border border-panel-border/80 rounded-2xl p-4 shadow-xl flex flex-col gap-3 animate-in fade-in slide-in-from-left duration-700 border-l-2 border-l-red-900/50">
                        <span class="text-[10px] font-bold text-red-500 uppercase tracking-widest font-mono border-b border-white/[0.03] pb-2">Purchase Return History (PRN)</span>
                        
                        <div v-for="ret in po.returns" :key="ret.id" class="flex flex-col gap-3 p-4 bg-deep/50 rounded-xl border border-panel-border/50 group hover:border-red-500/20 transition-all">
                            <div class="flex justify-between items-center">
                                <span @click="viewReturnDetails(ret)" 
                                      class="text-[10px] font-mono text-red-400 font-bold cursor-pointer hover:underline">{{ ret.reference_number }}</span>
                                <span class="text-[9px] font-mono text-secondary">{{ ret.returned_at }}</span>
                            </div>
                            <div class="flex flex-col gap-1">
                                <div class="flex justify-between text-[11px]">
                                    <span class="text-muted font-bold uppercase tracking-tighter text-[9px]">Authorized:</span>
                                    <span class="text-zinc-300 font-bold text-[10px]">{{ ret.returned_by }}</span>
                                </div>
                                <div class="flex justify-between text-[11px]">
                                    <span class="text-muted font-bold uppercase tracking-tighter text-[9px]">Origin Bin:</span>
                                    <span class="text-secondary text-[10px]">{{ ret.from_location }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lines Data -->
                <div class="col-span-12 lg:col-span-9 flex flex-col gap-4">
                    <div class="flex-1 bg-panel/40 border border-panel-border/80 rounded-2xl flex flex-col overflow-hidden shadow-xl p-4">
                        <span class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono border-b border-panel-border/50 pb-2 mb-3 block">Line Items</span>
                        
                        <DataTable 
                            :value="po.lines" 
                            class="p-datatable-sm w-full"
                            stripedRows
                        >
                            <Column field="sku" header="SKU" style="width: 15rem">
                                <template #body="{ data }">
                                    <span class="text-sky-400 font-mono text-[10px] font-bold tracking-widest text-[#50e3c2]">{{ data.sku }}</span>
                                </template>
                            </Column>
                            
                            <Column field="product_name" header="PRODUCT">
                                <template #body="{ data }">
                                    <div class="flex flex-col">
                                        <span class="text-primary font-bold text-xs">{{ data.product_name }}</span>
                                        <span v-if="data.product_code" class="text-[9px] font-bold text-muted font-mono tracking-widest uppercase">MPN: {{ data.product_code }}</span>
                                    </div>
                                </template>
                            </Column>

                            <Column field="unit_cost" header="UNIT COST">
                                <template #body="{ data }">
                                    <span class="text-secondary font-mono text-xs">{{ data.formatted_unit_cost }}</span>
                                </template>
                            </Column>

                            <Column field="ordered_qty" header="ORDER GOAL">
                                <template #body="{ data }">
                                    <div class="flex flex-col">
                                        <span class="text-primary font-mono text-xs font-bold">{{ data.formatted_ordered_qty || data.ordered_qty }}</span>
                                        <span class="text-[8px] font-bold text-muted uppercase tracking-widest">Requirement</span>
                                    </div>
                                </template>
                            </Column>

                            <Column field="received_qty" header="NET RCV">
                                <template #body="{ data }">
                                    <div class="flex flex-col">
                                        <span :class="[Number(data.net_received_qty) >= Number(data.requirement_qty) ? 'text-emerald-400' : 'text-amber-400', 'font-mono text-xs font-bold']">
                                            {{ data.formatted_received_qty || data.received_qty }}
                                        </span>
                                        <span class="text-[8px] font-bold text-muted uppercase tracking-widest">Balanced</span>
                                    </div>
                                </template>
                            </Column>

                            <Column field="returned_qty" header="RETURNED">
                                <template #body="{ data }">
                                    <span :class="[data.returned_qty > 0 ? 'text-red-400 font-black' : 'text-muted', 'font-mono text-xs']">
                                        {{ Number(data.returned_qty) > 0 ? (data.formatted_returned_qty || data.returned_qty) : '-' }}
                                    </span>
                                </template>
                            </Column>
                            
                            <Column field="pending_qty" header="REMAINING">
                                <template #body="{ data }">
                                    <span :class="[Number(data.pending_qty) === 0 ? 'text-muted' : 'text-orange-500', 'font-mono text-xs font-bold']">
                                        {{ data.formatted_pending_qty || data.pending_qty }}
                                    </span>
                                </template>
                            </Column>

                            <Column field="total_line_cost" header="TOTAL">
                                <template #body="{ data }">
                                    <span class="text-primary font-mono text-xs font-bold">₱{{ Number(data.total_line_cost).toFixed(2) }}</span>
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </div>
            </div>
        </div>

        <!-- GRN Dialog -->
        <Dialog v-model:visible="grnDialog" modal header="Post Goods Receipt Note (GRN)" :style="{ width: '50rem' }" :breakpoints="{ '1199px': '75vw', '575px': '90vw' }">
            <div class="flex flex-col gap-6 py-2">
                <div class="bg-amber-500/10 border border-amber-500/20 p-4 rounded-xl flex items-start gap-4">
                    <i class="pi pi-exclamation-triangle text-amber-500 mt-0.5"></i>
                    <p class="text-xs text-amber-400 font-bold leading-relaxed">
                        Posting a Goods Receipt Note will instantly bring this stock into your physical inventory and update your FIFO cost layers. This action cannot be natively undone.
                    </p>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-[10px] font-bold text-secondary tracking-widest font-mono uppercase">Receiving Location (Destination Bin)</label>
                    <Select 
                        v-model="grnForm.location_id" 
                        :options="locations" 
                        optionLabel="name" 
                        optionValue="id" 
                        placeholder="Select active bin" 
                        filter
                        class="w-full bg-deep border-zinc-700 text-sm focus:border-orange-500/50"
                    >
                        <template #option="slotProps">
                            <span class="font-bold text-xs">{{ slotProps.option.code }} — {{ slotProps.option.name }}</span>
                        </template>
                    </Select>
                </div>

                <div class="border border-panel-border rounded-xl overflow-hidden mt-2">
                    <DataTable :value="grnForm.lines" class="p-datatable-sm w-full">
                        <Column field="sku" header="SKU">
                            <template #body="{ data }">
                                <span class="text-sky-400 font-mono text-[10px] font-bold">{{ data.sku }}</span>
                            </template>
                        </Column>
                        <Column field="product_name" header="PRODUCT">
                            <template #body="{ data }">
                                <div class="flex flex-col">
                                    <span class="text-primary font-bold text-xs">{{ data.product_name }}</span>
                                    <span v-if="data.product_code" class="text-[8px] font-bold text-muted font-mono tracking-widest uppercase">MPN: {{ data.product_code }}</span>
                                </div>
                            </template>
                        </Column>
                        <Column field="pending_qty" header="PENDING">
                            <template #body="{ data }">
                                <span class="text-amber-400 font-mono text-xs font-bold">{{ data.formatted_pending_qty || data.pending_qty }}</span>
                            </template>
                        </Column>
                        <Column header="STOCK LEVEL" style="width: 10rem">
                            <template #body="{ data }">
                                <div class="flex flex-col items-start gap-1">
                                    <div class="flex items-center gap-2 cursor-help group/stock" @click="toggleStockInfo($event, data)">
                                        <div class="px-2 py-0.5 rounded bg-panel border border-panel-border flex items-center gap-1.5 transition-all group-hover/stock:border-orange-500/30">
                                            <div class="w-1 h-1 rounded-full animate-pulse" :class="getLocalStock(data) > 0 ? 'bg-emerald-500' : 'bg-red-500'"></div>
                                            <span class="text-[10px] font-mono font-bold" :class="getLocalStock(data) > 0 ? 'text-emerald-400' : 'text-secondary'">
                                                {{ getScaledQty(data, getLocalStock(data)) }}
                                            </span>
                                        </div>
                                        <i class="pi pi-info-circle text-[10px] text-muted group-hover/stock:text-orange-500/50 italic transition-colors"></i>
                                    </div>
                                    <span class="text-[8px] font-bold text-muted uppercase tracking-tighter">Existing in Bin</span>
                                </div>
                            </template>
                        </Column>
                        <Column field="received_qty" header="RECEIVE QTY" style="width: 14rem">
                            <template #body="{ data }">
                                <div class="flex items-center bg-deep border border-panel-border rounded-lg overflow-hidden focus-within:border-orange-500/50 transition-all shadow-inner h-9 group">
                                    <div class="flex-1 flex items-center px-1">
                                        <InputNumber 
                                            v-model="data.received_qty" 
                                            :min="0" 
                                            :minFractionDigits="0" 
                                            :maxFractionDigits="isUomIdDiscrete(data.uom_id) ? 0 : 4" 
                                            class="p-inputtext-sm text-center font-mono font-bold text-primary border-0 bg-transparent flex-1 focus:ring-0 w-full"
                                            :inputStyle="{ background: 'transparent', border: '0', textAlign: 'center', color: 'white', width: '100%', boxShadow: 'none' }"
                                        />
                                    </div>
                                    
                                    <!-- Simple Divider -->
                                    <div class="w-px h-5 bg-panel-hover group-focus-within:bg-orange-500/20"></div>
                                    
                                    <div class="w-24">
                                        <Select 
                                            v-model="data.uom_id" 
                                            :options="getFilteredUoms(data)" 
                                            optionLabel="abbreviation" 
                                            optionValue="id" 
                                            placeholder="Unit"
                                            @change="onGrnUomChange(data)"
                                            class="!bg-transparent !border-0 !shadow-none !h-full w-full !text-[10px] font-mono font-black"
                                            pt:root:class="!border-0 !bg-transparent !shadow-none"
                                            pt:label:class="!text-amber-500 !p-1.5 !text-center !uppercase font-black"
                                            pt:dropdown:class="!text-muted !w-6"
                                        >
                                            <template #value="slotProps">
                                                <span v-if="slotProps.value" class="text-amber-500 uppercase font-black">
                                                    {{ uoms.find(u => u.id === slotProps.value)?.abbreviation }}
                                                </span>
                                                <span v-else class="text-amber-500/50 uppercase font-black">?</span>
                                            </template>
                                            <template #option="slotProps">
                                                <div class="flex flex-col gap-1 w-full py-1">
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-[11px] font-bold text-zinc-100 uppercase tracking-tight">{{ slotProps.option.abbreviation }}</span>
                                                        <span v-if="slotProps.option.is_custom" 
                                                              class="text-[8px] px-1.5 py-0.5 bg-amber-500/10 text-amber-400 border border-amber-500/20 rounded font-black tracking-widest leading-none">
                                                            CUSTOM
                                                        </span>
                                                    </div>
                                                    <div v-if="slotProps.option.conversion_text" class="text-[9px] text-secondary font-mono italic">
                                                        {{ slotProps.option.conversion_text }}
                                                    </div>
                                                </div>
                                            </template>
                                        </Select>
                                    </div>
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="grnDialog = false" class="p-button-text !text-secondary hover:!text-primary" />
                <Button label="Post Receipt" icon="pi pi-check" @click="postReceipt" :loading="grnLoading" class="p-button-sm !bg-orange-500 hover:!bg-orange-600 !border-none !text-zinc-950 font-bold tracking-widest uppercase font-mono shadow-[0_0_15px_rgba(249,115,22,0.3)]" />
            </template>
        </Dialog>

        <!-- H-1: Popover for per-location stock breakdown -->
        <Popover ref="stockPopover">
            <div v-if="popoverLine" class="flex flex-col gap-2 p-1 min-w-[200px]">
                <span class="text-[9px] font-bold text-secondary uppercase tracking-widest font-mono border-b border-panel-border pb-1">Stock by Location</span>
                <div v-if="popoverLine.inventories && popoverLine.inventories.length > 0">
                    <div v-for="inv in popoverLine.inventories" :key="inv.location_id" class="flex justify-between items-center py-1">
                        <span class="text-[10px] text-secondary font-mono">{{ inv.location_name || inv.location?.name || 'Unknown' }}</span>
                        <span class="text-[10px] font-bold" :class="Number(inv.quantity_on_hand) > 0 ? 'text-emerald-400' : 'text-muted'">{{ inv.quantity_on_hand }}</span>
                    </div>
                </div>
                <span v-else class="text-[10px] text-muted italic">No stock in any location.</span>
            </div>
        </Popover>
        <!-- Return Items (RTV) Dialog (Surgical Precision Redesign) -->
        <Dialog v-model:visible="returnDialog" modal :closable="false" :style="{ width: '65rem' }" :breakpoints="{ '1199px': '75vw', '575px': '90vw' }" pt:root:class="!border-0 !bg-transparent !shadow-2xl" pt:header:class="!hidden" pt:content:class="!p-0 !bg-transparent !overflow-hidden">
            <div class="flex flex-col bg-deep border border-red-900/40 rounded-2xl overflow-hidden relative shadow-[0_0_50px_rgba(220,38,38,0.15)] ring-1 ring-white/5">
                <!-- Red Ambient Glow -->
                <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[80%] h-32 bg-red-600/10 blur-[100px] pointer-events-none"></div>

                <!-- Modal Header (Premium) -->
                <div class="px-6 py-5 border-b border-red-900/30 bg-deep/80 backdrop-blur-xl flex justify-between items-center relative z-10">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-red-900/30 border border-red-500/20 flex items-center justify-center">
                            <i class="pi pi-replay text-red-500 text-lg"></i>
                        </div>
                        <div class="flex flex-col">
                            <h2 class="text-lg font-black text-primary tracking-tight font-mono">PROCESS REVERSAL (RTV)</h2>
                            <p class="text-[9px] text-secondary font-bold uppercase tracking-widest mt-0.5">Revert previously received stock from <span class="text-sky-400">PO-{{ po.po_number }}</span></p>
                        </div>
                    </div>
                    <button @click="returnDialog = false" class="w-8 h-8 rounded-full bg-panel border border-panel-border flex items-center justify-center text-secondary hover:text-primary hover:bg-panel-hover transition-colors">
                        <i class="pi pi-times text-xs"></i>
                    </button>
                </div>

                <div class="p-6 flex flex-col gap-6 max-h-[65vh] overflow-y-auto custom-scrollbar">
                    
                    <!-- Technical Alert (Sleeker integration) -->
                    <div class="bg-red-500/5 border-l-2 border-l-red-500/50 p-4 rounded-r-xl flex items-center gap-4">
                        <i class="pi pi-shield text-red-500/60 shadow-[0_0_10px_rgba(239,68,68,0.2)]"></i>
                        <p class="text-[11px] text-secondary font-medium leading-relaxed">
                            <span class="text-primary font-mono font-black mr-2 tracking-tighter uppercase opacity-80">System Protocol: Stock Reversal</span>
                            Executing a return generates a <span class="text-primary font-mono font-bold">PRET</span> ledger. 
                            <span class="text-emerald-400/80 font-black text-[10px] tracking-wide uppercase mx-1">Replacement</span> resets status. 
                            <span class="text-sky-400/80 font-black text-[10px] tracking-wide uppercase mx-1">Credit</span> adjusts valuation layers.
                        </p>
                    </div>

                    <!-- Filter Configuration (More Integrated) -->
                    <div class="flex flex-col gap-2">
                        <label class="text-[9px] font-black text-muted tracking-widest uppercase font-mono pl-1">Source Picking Location</label>
                        <Select 
                            v-model="returnForm.location_id" 
                            :options="filteredReturnLocations" 
                            optionLabel="name" 
                            optionValue="id" 
                            placeholder="SELECT ORIGIN BIN..." 
                            filter
                            class="!w-full !bg-panel/30 !border-panel-border/80 !rounded-xl !h-12 !flex !items-center font-mono focus-within:!border-red-500/30 shadow-none transition-all"
                        >
                            <template #option="slotProps">
                                <div class="flex items-center justify-between w-full">
                                    <span class="text-[11px] font-bold text-zinc-300 uppercase tracking-tight">{{ slotProps.option.name }}</span>
                                    <span class="text-[10px] font-mono font-black text-red-500/60">{{ slotProps.option.code }}</span>
                                </div>
                            </template>
                        </Select>
                    </div>

                    <!-- Item Matrix (Box-less Design) -->
                    <div class="flex flex-col gap-2">
                        <label class="text-[9px] font-black text-muted tracking-widest uppercase font-mono pl-1">Inventory Position Context</label>
                        
                        <div class="flex flex-col gap-3">
                            <div v-for="(line, index) in returnForm.lines" :key="line.po_line_id" 
                                 class="grid grid-cols-1 xl:grid-cols-12 gap-4 p-4 items-center bg-panel/20 border border-panel-border/40 rounded-2xl hover:bg-panel/40 transition-all hover:border-red-500/20 group/row"
                            >
                                <!-- Product Identity (Col 3) -->
                                <div class="xl:col-span-3 flex flex-col gap-1">
                                    <span class="text-xs font-black text-primary group-hover/row:text-red-400 transition-colors">{{ line.product_name }}</span>
                                    <div class="flex items-center gap-2">
                                        <span class="text-[9px] font-mono font-black text-sky-500/60 uppercase tracking-tighter bg-sky-500/5 px-1.5 py-0.5 rounded border border-sky-500/10">{{ line.sku }}</span>
                                        <span v-if="line.product_code" class="text-[8px] font-mono font-bold text-muted uppercase tracking-tighter">REF: {{ line.product_code }}</span>
                                    </div>
                                </div>

                                <!-- Status Indicators (Col 2 - Simplified) -->
                                <div class="xl:col-span-2 flex items-center gap-3">
                                    <div class="flex flex-col">
                                        <span class="text-[8px] font-black text-muted uppercase tracking-widest">Orig RCV</span>
                                        <span class="text-[11px] font-mono font-bold text-zinc-300">{{ line.formatted_received_qty || line.received_qty }}</span>
                                    </div>
                                    <div class="w-px h-6 bg-panel-hover/50"></div>
                                    <div class="flex flex-col">
                                        <span class="text-[8px] font-black uppercase tracking-widest" :class="getStockInSelectedLocation(line.product_id) > 0 ? 'text-emerald-500/60' : 'text-muted'">In Bin</span>
                                        <span class="text-[11px] font-mono font-bold" :class="getStockInSelectedLocation(line.product_id) > 0 ? 'text-emerald-400' : 'text-muted'">
                                            {{ getStockInSelectedLocation(line.product_id) ?? '0' }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Entries (Col 7 - Unified Action Bar) -->
                                <div class="xl:col-span-7 grid grid-cols-12 gap-2 h-10">
                                    <!-- Qty + UOM (5) -->
                                    <div class="col-span-5 flex items-center bg-deep/80 border border-panel-border rounded-xl focus-within:border-red-500/50 transition-all overflow-hidden h-full group/input">
                                        <InputNumber 
                                            v-model="line.return_qty" 
                                            class="w-full h-full"
                                            :inputStyle="{ background: 'transparent', border: '0', textAlign: 'center', color: '#f87171', width: '100%', fontWeight: '900', fontSize: '14px', fontFamily: 'monospace' }"
                                            placeholder="0"
                                        />
                                        <div class="w-px h-4 bg-panel-hover/50 group-focus-within/input:bg-red-500/20"></div>
                                        <Select 
                                            v-model="line.uom_id" 
                                            :options="getFilteredUoms(line)" 
                                            optionLabel="abbreviation" 
                                            optionValue="id" 
                                            @change="onReturnUomChange(line)"
                                            class="!bg-transparent !border-0 !shadow-none !h-full w-24 !text-[11px] font-black"
                                            pt:label:class="!text-red-400 !font-black !p-0 !flex !items-center !justify-center !text-center !uppercase !h-full"
                                            pt:dropdown:class="!text-muted !w-4"
                                        >
                                            <template #value="slotProps">
                                                <span v-if="slotProps.value" class="text-red-400 uppercase font-black">
                                                    {{ uoms.find(u => u.id === slotProps.value)?.abbreviation }}
                                                </span>
                                            </template>
                                            <template #option="slotProps">
                                                <div class="flex flex-col gap-1 w-full py-1">
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-[11px] font-bold text-zinc-100 uppercase tracking-tight">{{ slotProps.option.abbreviation }}</span>
                                                        <span v-if="slotProps.option.is_custom" 
                                                              class="text-[8px] px-1.5 py-0.5 bg-rose-500/10 text-rose-400 border border-rose-500/20 rounded font-black tracking-widest leading-none">
                                                            CUSTOM
                                                        </span>
                                                    </div>
                                                    <div v-if="slotProps.option.conversion_text" class="text-[9px] text-secondary font-mono italic">
                                                        {{ slotProps.option.conversion_text }}
                                                    </div>
                                                </div>
                                            </template>
                                        </Select>
                                    </div>

                                    <!-- Resolution (3) -->
                                    <div class="col-span-3">
                                        <Select 
                                            v-model="line.resolution" 
                                            :options="[{label: 'REPLACE', value: 'replacement'}, {label: 'CREDIT', value: 'credit'}]" 
                                            optionLabel="label" 
                                            optionValue="value" 
                                            class="!w-full !bg-deep/80 !border-panel-border !rounded-xl !h-full !flex !items-center !text-[10px] !font-black tracking-widest focus-within:!border-red-500/20" 
                                            pt:label:class="!pl-3 !flex !items-center !h-full"
                                        >
                                            <template #value="slotProps">
                                                <span v-if="slotProps.value" class="text-[9px] font-black tracking-widest uppercase" :class="slotProps.value === 'replacement' ? 'text-emerald-500/60' : 'text-sky-500/60'">
                                                    {{ slotProps.value === 'replacement' ? 'REPLACE' : 'CREDIT' }}
                                                </span>
                                            </template>
                                        </Select>
                                    </div>

                                    <!-- Reason (4) -->
                                    <div class="col-span-4">
                                        <InputText 
                                            v-model="line.reason" 
                                            placeholder="Reason..." 
                                            class="!w-full !bg-deep/80 !border-panel-border !rounded-xl !text-[11px] !font-bold !h-full !px-4 focus:!border-red-500/30 text-zinc-300 placeholder:text-muted placeholder:font-bold" 
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Footer -->
                <div class="px-6 py-5 border-t border-red-900/30 bg-deep/80 backdrop-blur-xl flex justify-between items-center z-10 relative">
                    <Button 
                        label="Cancel" 
                        icon="pi pi-times"
                        @click="returnDialog = false" 
                        class="p-button-text !text-secondary hover:!text-primary !font-bold !text-[11px] !tracking-widest uppercase" 
                    />
                    
                    <div class="flex items-center gap-6">
                        <span v-if="returnForm.lines.some(l => Number(l.return_qty) > 0)" class="text-[10px] font-mono text-red-400 font-bold uppercase tracking-widest flex items-center gap-2">
                            <div class="w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse"></div>
                            {{ returnForm.lines.filter(l => Number(l.return_qty) > 0).length }} Items Ready for Reversal
                        </span>
                        <Button 
                            label="Process Return" 
                            icon="pi pi-replay"
                            @click="postReturn" 
                            :loading="returnLoading" 
                            class="!min-h-[2.5rem] !px-8 !bg-red-600 hover:!bg-red-500 !text-primary !font-black !text-[11px] !tracking-widest !rounded-xl !border-none transition-all uppercase shadow-[0_0_20px_rgba(220,38,38,0.4)]" 
                        />
                    </div>
                </div>
            </div>
        </Dialog>

        <!-- Ship Dialog -->
        <Dialog v-model:visible="shipDialog" modal header="Log Vendor Shipment" :style="{ width: '30rem' }">
            <div class="flex flex-col gap-4 py-2">
                <div class="flex flex-col gap-2">
                    <label class="text-[10px] font-bold text-secondary tracking-widest font-mono uppercase">Carrier Name</label>
                    <InputText v-model="shipForm.carrier" placeholder="e.g. FedEx, DHL, LBC" class="w-full !bg-deep !border-zinc-700 !text-secondary !text-sm" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-[10px] font-bold text-secondary tracking-widest font-mono uppercase">Tracking Number</label>
                    <InputText v-model="shipForm.tracking_number" placeholder="Optional tracking #" class="w-full !bg-deep !border-zinc-700 !text-secondary !text-sm" />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="shipDialog = false" class="p-button-text !text-secondary hover:!text-primary" />
                <Button label="Confirm Shipment" icon="pi pi-truck" @click="submitShipment" :loading="shipLoading" class="p-button-sm !bg-white hover:!bg-zinc-200 !border-none !text-zinc-950 font-bold uppercase tracking-widest transition-all" />
            </template>
        </Dialog>
        
        <!-- GRN Detail View Modal -->
        <Dialog v-model:visible="grnDetailDialog" modal header="Goods Receipt Note Details" :style="{ width: '45rem' }">
            <div v-if="selectedGrn" class="flex flex-col gap-6">
                <!-- Receipt Header Info -->
                <div class="grid grid-cols-2 gap-4 p-4 bg-deep rounded-xl border border-panel-border">
                    <div class="flex flex-col gap-1">
                        <label class="text-[9px] font-bold text-muted uppercase tracking-widest font-mono">Reference Number</label>
                        <span 
                            class="text-sm font-black text-sky-400 font-mono cursor-pointer hover:underline"
                            @click="router.visit(`/movements/${selectedGrn.id}`)"
                        >
                            {{ selectedGrn.reference_number }}
                        </span>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-[9px] font-bold text-muted uppercase tracking-widest font-mono">Received At</label>
                        <span class="text-sm font-bold text-primary">{{ selectedGrn.received_at }}</span>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-[9px] font-bold text-muted uppercase tracking-widest font-mono">Received By</label>
                        <span class="text-sm font-bold text-orange-400">{{ selectedGrn.received_by }}</span>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-[9px] font-bold text-muted uppercase tracking-widest font-mono">Storage Location</label>
                        <span class="text-sm font-bold text-primary">{{ selectedGrn.to_location }}</span>
                    </div>
                </div>

                <!-- Receipt Line Items -->
                <div class="border border-panel-border rounded-xl overflow-hidden">
                    <DataTable :value="selectedGrn.lines" class="p-datatable-sm w-full">
                        <Column field="sku" header="SKU">
                            <template #body="{ data }">
                                <span class="text-sky-400 font-mono text-[10px] font-bold">{{ data.sku }}</span>
                            </template>
                        </Column>
                        <Column field="product_name" header="PRODUCT">
                            <template #body="{ data }">
                                <span class="text-xs font-bold">{{ data.product_name }}</span>
                            </template>
                        </Column>
                        <Column field="quantity" header="RECEIVED">
                            <template #body="{ data }">
                                <span class="text-primary font-mono text-xs font-bold">{{ data.formatted_quantity }}</span>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
            <template #footer>
                <Button label="Close Audit Trail" icon="pi pi-times" @click="grnDetailDialog = false" class="p-button-sm !bg-panel-hover hover:!bg-zinc-700 !text-zinc-300 !border-none font-bold tracking-widest uppercase font-mono transition-all" />
            </template>
        </Dialog>

        <!-- PRN (Return) Detail View Modal -->
        <Dialog v-model:visible="returnDetailDialog" modal header="Purchase Return Note Details" :style="{ width: '45rem' }">
            <div v-if="selectedReturn" class="flex flex-col gap-6">
                <!-- Return Header Info -->
                <div class="grid grid-cols-2 gap-4 p-4 bg-deep rounded-xl border border-panel-border border-l-4 border-l-red-600">
                    <div class="flex flex-col gap-1">
                        <label class="text-[9px] font-bold text-muted uppercase tracking-widest font-mono">Reference Number</label>
                        <span 
                            class="text-sm font-black text-red-500 font-mono cursor-pointer hover:underline"
                            @click="router.visit(`/movements/${selectedReturn.id}`)"
                        >
                            {{ selectedReturn.reference_number }}
                        </span>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-[9px] font-bold text-muted uppercase tracking-widest font-mono">Returned At</label>
                        <span class="text-sm font-bold text-zinc-300">{{ selectedReturn.returned_at }}</span>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-[9px] font-bold text-muted uppercase tracking-widest font-mono">Authorized By</label>
                        <span class="text-sm font-bold text-zinc-100">{{ selectedReturn.returned_by }}</span>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-[9px] font-bold text-muted uppercase tracking-widest font-mono">Origin Location</label>
                        <span class="text-sm font-bold text-zinc-300">{{ selectedReturn.from_location }}</span>
                    </div>
                </div>

                <!-- Return Line Items -->
                <div class="border border-panel-border rounded-xl overflow-hidden">
                    <DataTable :value="selectedReturn.lines" class="p-datatable-sm w-full">
                        <Column field="sku" header="SKU">
                            <template #body="{ data }">
                                <span class="text-sky-400 font-mono text-[10px] font-bold">{{ data.sku }}</span>
                            </template>
                        </Column>
                        <Column field="product_name" header="PRODUCT">
                            <template #body="{ data }">
                                <span class="text-xs font-bold text-primary">{{ data.product_name }}</span>
                            </template>
                        </Column>
                        <Column field="notes" header="RESOLUTION">
                            <template #body="{ data }">
                                <span 
                                    :class="[
                                        data.notes?.includes('Replacement') ? 'text-sky-400' : 'text-amber-400',
                                        'text-[9px] font-mono font-bold uppercase tracking-tighter border border-current/20 px-1.5 py-0.5 opacity-80'
                                    ]"
                                >
                                    {{ data.notes?.replace('Resolution: ', '') || 'N/A' }}
                                </span>
                            </template>
                        </Column>
                        <Column field="quantity" header="QTY">
                            <template #body="{ data }">
                                <span class="text-red-500 font-mono text-xs font-black">{{ data.formatted_quantity }}</span>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
            <template #footer>
                <Button label="Close Audit Trail" icon="pi pi-times" @click="returnDetailDialog = false" class="p-button-sm !bg-panel-hover hover:!bg-zinc-700 !text-zinc-300 !border-none font-bold tracking-widest uppercase font-mono transition-all" />
            </template>
        </Dialog>

        <!-- Global ConfirmDialog is provided by AppLayout -->
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
    padding: 0.5rem 0.75rem;
}
:deep(.p-datatable .p-datatable-tbody > tr) {
    background: transparent;
}
:deep(.p-datatable .p-datatable-tbody > tr > td) {
    border-bottom: 1px solid rgba(39, 39, 42, 0.5); /* zinc-800/50 */
    color: #e4e4e7; /* zinc-200 */
    padding: 0.5rem 0.75rem;
}
:deep(.p-tag.p-tag-warning) { background: rgba(245, 158, 11, 0.1); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.2); }
:deep(.p-tag.p-tag-info) { background: rgba(14, 165, 233, 0.1); color: #38bdf8; border: 1px solid rgba(14, 165, 233, 0.2); }
:deep(.p-tag.p-tag-success) { background: rgba(16, 185, 129, 0.1); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.2); }
:deep(.p-tag.p-tag-danger) { background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2); }
:deep(.p-tag.p-tag-help) { background: rgba(139, 92, 246, 0.1); color: #a78bfa; border: 1px solid rgba(139, 92, 246, 0.2); }

:deep(.p-dialog) {
    background: #09090b;
    border: 1px solid #27272a;
    border-radius: 1rem;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}
:deep(.p-dialog-header) {
    background: rgba(24, 24, 27, 0.8);
    border-bottom: 1px solid rgba(39, 39, 42, 0.8);
    color: white;
    padding: 1rem;
}
:deep(.p-dialog-content) { background: transparent; padding: 1rem; color: #a1a1aa; }
:deep(.p-dialog-footer) { background: rgba(24, 24, 27, 0.8); border-top: 1px solid rgba(39, 39, 42, 0.8); padding: 1rem; }
:deep(.p-select), :deep(.p-inputnumber-input) { background: #09090b !important; border-color: #27272a; color: white; }
:deep(.p-inputnumber-button) { background: #18181b; border-color: #27272a; color: #a1a1aa; }
:deep(.p-inputnumber-button:hover) { background: #27272a; color: white; }
</style>


