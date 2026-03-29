<script setup>
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';

const stats = ref({
    total_products: 0,
    total_vendors: 0,
    inventory_value: 0,
    low_stock_count: 0
});
const recentTransactions = ref([]);
const systemStatus = ref('FETCHING...');
const loading = ref(true);

const loadDashboard = async () => {
    loading.value = true;
    try {
        const res = await axios.get('/api/dashboard/stats');
        stats.value = res.data.stats;
        recentTransactions.value = res.data.recent_transactions;
        systemStatus.value = res.data.system_status;
    } catch (e) {
        console.error("Dashboard error", e);
        systemStatus.value = 'ERROR';
    } finally {
        loading.value = false;
    }
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(val);
};

onMounted(loadDashboard);
</script>

<template>
    <AppLayout>
        <Head title="Dashboard Intel" />
        
        <!-- Header Section -->
        <div class="mb-10 flex justify-between items-end">
            <div class="flex flex-col">
                <span class="text-[10px] font-bold text-sky-400 uppercase tracking-[0.2em] block mb-2 font-mono">Real-time System Intelligence</span>
                <h1 class="text-3xl font-bold text-white tracking-tight m-0 mb-2">Dashboard Overview</h1>
                <p class="text-zinc-500 text-sm max-w-2xl leading-relaxed">
                    Key performance indicators, inventory valuations, and global system activity across the network.
                </p>
            </div>
            <div class="flex items-center gap-4">
                <Button @click="loadDashboard" 
                        class="!bg-zinc-900 !border-zinc-800 !text-zinc-400 hover:!text-white !px-6 !h-12 !font-bold !text-[11px] uppercase tracking-widest transition-all" 
                        icon="pi pi-refresh" :loading="loading" label="REFRESH_INTEL" />
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
            <!-- Global Valuation -->
            <div class="group relative bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-6 hover:border-sky-500/40 transition-all duration-500 overflow-hidden shadow-xl">
                <div class="absolute -right-10 -bottom-10 w-24 h-24 bg-sky-500/5 blur-3xl rounded-full group-hover:bg-sky-500/10 transition-colors"></div>
                
                <div class="relative z-10">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-10 h-10 rounded-xl bg-zinc-950 flex items-center justify-center border border-zinc-800 group-hover:border-sky-500/30 transition-colors">
                            <i class="pi pi-dollar text-zinc-500 group-hover:text-sky-400 text-sm transition-colors"></i>
                        </div>
                        <span class="text-[8px] font-bold text-zinc-600 font-mono tracking-widest uppercase line-clamp-1">VALUATION_CORE</span>
                    </div>
                    <div class="text-zinc-500 text-[10px] font-bold uppercase tracking-wider mb-1 font-mono">Total Inventory Value</div>
                    <div class="text-3xl font-bold leading-none text-white tracking-tighter">{{ formatCurrency(stats.inventory_value) }}</div>
                    <div class="text-zinc-600 text-[11px] mt-4 font-medium leading-relaxed">Total value of all on-hand stock assets.</div>
                </div>
            </div>

            <!-- Risk Profile -->
            <div class="group relative bg-zinc-900/40 border rounded-2xl p-6 transition-all duration-500 overflow-hidden shadow-xl"
                 :class="stats.low_stock_count > 0 ? 'border-red-500/40' : 'border-zinc-800/80 hover:border-sky-500/40'">
                <div class="absolute -right-10 -bottom-10 w-24 h-24 blur-3xl rounded-full transition-colors"
                     :class="stats.low_stock_count > 0 ? 'bg-red-500/5 group-hover:bg-red-500/10' : 'bg-sky-500/5 group-hover:bg-sky-500/10'"></div>
                
                <div class="relative z-10">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-10 h-10 rounded-xl bg-zinc-950 flex items-center justify-center border border-zinc-800 group-hover:border-sky-500/30 transition-colors">
                            <i class="pi pi-exclamation-triangle text-zinc-500 group-hover:text-red-400 text-sm transition-colors"></i>
                        </div>
                        <span class="text-[8px] font-bold text-zinc-600 font-mono tracking-widest uppercase">RISK_DETECTION</span>
                    </div>
                    <div class="text-zinc-500 text-[10px] font-bold uppercase tracking-wider mb-1 font-mono">Low Stock Items</div>
                    <div class="text-3xl font-bold leading-none tracking-tighter" :class="stats.low_stock_count > 0 ? 'text-red-400' : 'text-white'">
                        {{ stats.low_stock_count }}
                    </div>
                    <div class="text-zinc-600 text-[11px] mt-4 font-medium leading-relaxed">Active assets below safety threshold.</div>
                </div>
            </div>

            <!-- Stakeholders Summary -->
            <div class="group relative bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-6 hover:border-sky-500/40 transition-all duration-500 overflow-hidden shadow-xl">
                 <div class="absolute -right-10 -bottom-10 w-24 h-24 bg-sky-500/5 blur-3xl rounded-full group-hover:bg-sky-500/10 transition-colors"></div>
                 <div class="relative z-10">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-10 h-10 rounded-xl bg-zinc-950 flex items-center justify-center border border-zinc-800 group-hover:border-sky-500/30 transition-colors">
                            <i class="pi pi-server text-zinc-500 group-hover:text-emerald-400 text-sm transition-colors"></i>
                        </div>
                        <span class="text-[8px] font-bold text-zinc-600 font-mono tracking-widest uppercase">ENTITY_METRICS</span>
                    </div>
                    <div class="text-zinc-500 text-[10px] font-bold uppercase tracking-wider mb-2 font-mono">System Directory</div>
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center justify-between bg-zinc-950/50 p-2 rounded-lg border border-zinc-800/50">
                            <span class="text-[10px] font-bold text-zinc-500 font-mono uppercase">VENDORS</span>
                            <span class="text-sm font-bold text-white tracking-tighter">{{ stats.total_vendors }}</span>
                        </div>
                        <div class="flex items-center justify-between bg-zinc-950/50 p-2 rounded-lg border border-zinc-800/50">
                            <span class="text-[10px] font-bold text-zinc-500 font-mono uppercase">PRODUCTS</span>
                            <span class="text-sm font-bold text-white tracking-tighter">{{ stats.total_products }}</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Network Integrity -->
            <div class="group relative bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-6 hover:border-sky-500/40 transition-all duration-500 overflow-hidden shadow-xl">
                <div class="absolute -right-10 -bottom-10 w-24 h-24 bg-sky-500/5 blur-3xl rounded-full group-hover:bg-sky-500/10 transition-colors"></div>
                <div class="relative z-10">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-10 h-10 rounded-xl bg-zinc-950 flex items-center justify-center border border-zinc-800 group-hover:border-sky-500/30 transition-colors">
                            <i class="pi pi-wifi text-zinc-500 group-hover:text-sky-400 text-sm transition-colors"></i>
                        </div>
                        <span class="text-[8px] font-bold text-zinc-600 font-mono tracking-widest uppercase">SYNC_STATUS</span>
                    </div>
                    <div class="text-zinc-500 text-[10px] font-bold uppercase tracking-wider mb-1 font-mono">System Integrity</div>
                    <div class="text-3xl font-bold leading-none text-sky-400 tracking-tighter">{{ systemStatus }}</div>
                    <div class="text-zinc-600 text-[11px] mt-4 font-medium leading-relaxed">Global synchronization status.</div>
                </div>
            </div>
        </div>

        <!-- Dynamic Activity Feed Section -->
        <div class="grid grid-cols-12 gap-8">
            <div class="col-span-12 lg:col-span-8 bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-8 shadow-xl relative overflow-hidden group">
                 <div class="absolute top-0 right-0 w-64 h-64 bg-sky-500/5 blur-[100px] -mr-32 -mt-32 rounded-full opacity-50"></div>
                 
                 <div class="relative z-10">
                    <div class="flex items-center justify-between mb-8 pb-6 border-b border-zinc-800/60">
                        <div class="flex flex-col">
                            <span class="text-[9px] font-bold text-zinc-500 font-mono tracking-widest uppercase mb-1">LIVE_FEED // GLOBAL</span>
                            <h3 class="text-white font-bold text-xl tracking-tight">Recent Activity Log</h3>
                        </div>
                        <i class="pi pi-history text-zinc-700 text-xl"></i>
                    </div>

                    <div v-if="recentTransactions.length > 0" class="flex flex-col gap-2">
                        <div v-for="t in recentTransactions" :key="t.id" 
                             class="flex justify-between items-center bg-zinc-950/30 p-4 border border-zinc-800/50 rounded-xl hover:bg-zinc-800/20 transition-all group/item">
                            <div class="flex items-center gap-4">
                                <span class="bg-zinc-900 px-3 py-1 rounded text-[9px] font-bold text-zinc-400 border border-zinc-800 group-hover/item:text-sky-400 transition-colors uppercase font-mono">{{ t.type_name }}</span>
                                <div class="flex flex-col">
                                    <span class="text-sm font-bold text-zinc-200 group-hover/item:text-white transition-colors">{{ t.product_name }}</span>
                                    <span class="text-[9px] text-zinc-600 font-mono uppercase tracking-widest">{{ t.transaction_date }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-8">
                                <div class="flex flex-col items-end">
                                    <span class="text-[10px] font-bold text-zinc-500 uppercase font-mono tracking-widest">Quantity</span>
                                    <span class="text-sm font-bold text-sky-400">{{ t.quantity }} <span class="text-[9px] text-zinc-600">UNITS</span></span>
                                </div>
                                <div class="w-8 h-8 rounded-full border border-zinc-800 flex items-center justify-center opacity-0 group-hover/item:opacity-100 transition-all">
                                    <i class="pi pi-arrow-right text-[10px] text-zinc-500"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="py-12 flex flex-col items-center justify-center border border-dashed border-zinc-800/50 rounded-2xl bg-zinc-950/20">
                        <i class="pi pi-inbox text-zinc-800 text-3xl mb-4"></i>
                        <span class="text-zinc-600 font-mono text-[10px] uppercase tracking-[0.3em]">NO_LIVE_DATA_PACKETS</span>
                    </div>
                 </div>
            </div>

            <!-- Side Intelligence Panel (Placeholder) -->
            <div class="col-span-12 lg:col-span-4 flex flex-col gap-8">
                 <div class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-6 flex-1 shadow-xl">
                      <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono mb-6 block border-b border-zinc-800/50 pb-4">Network Node Status</span>
                      <div class="space-y-6">
                           <div v-for="i in 3" :key="i" class="flex items-center gap-4 opacity-40">
                                <div class="w-2 h-2 rounded-full bg-emerald-500/50"></div>
                                <div class="flex-1 flex flex-col gap-1">
                                     <div class="h-1.5 w-3/4 bg-zinc-800 rounded"></div>
                                     <div class="h-1 w-1/2 bg-zinc-900 rounded"></div>
                                </div>
                           </div>
                      </div>
                      <div class="mt-8 pt-6 border-t border-zinc-800/50 flex flex-col items-center justify-center py-4">
                           <i class="pi pi-cog text-zinc-800 text-2xl animate-spin-slow mb-4"></i>
                           <span class="text-[8px] font-bold text-zinc-700 uppercase tracking-[0.4em] font-mono">Process Visualization Pending</span>
                      </div>
                 </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Scoped styles migrated to Tailwind Utility Classes */
</style>
