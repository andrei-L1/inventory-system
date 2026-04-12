<script setup>
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';

const stats = ref({
    total_products: 0,
    total_vendors: 0,
    inventory_value: 0,
    low_stock_count: 0,
    transactions_today: 0,
    pending_po_count: 0,
    pending_so_count: 0,
    stock_value_trend: []
});
const recentTransactions = ref([]);
const lowStockItems = ref([]);
const systemStatus = ref('FETCHING...');
const loading = ref(true);

const loadDashboard = async () => {
    loading.value = true;
    try {
        const [res, lowStockRes] = await Promise.all([
            axios.get('/api/dashboard/stats'),
            axios.get('/api/inventory/low-stock')
        ]);
        stats.value = res.data.stats;
        recentTransactions.value = res.data.recent_transactions;
        systemStatus.value = res.data.system_status;
        lowStockItems.value = lowStockRes.data.data.slice(0, 5); // top 5 critical
    } catch (e) {
        console.error("Dashboard error", e);
        systemStatus.value = 'ERROR';
    } finally {
        loading.value = false;
    }
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(val));
};

const generatePath = (data) => {
    if (!data || data.length < 2) return '';
    const points = data.map(d => Number(d.value));
    const min = Math.min(...points);
    const max = Math.max(...points);
    const range = max - min || 1;
    
    return points.map((val, i) => {
        const x = (i / (points.length - 1)) * 100;
        const y = 20 - ((val - min) / range) * 18 - 1; // 18 height, 1 padding
        return (i === 0 ? 'M' : 'L') + `${x},${y}`;
    }).join(' ');
};

const generateFillPath = (data) => {
    const path = generatePath(data);
    if (!path) return '';
    return `${path} L 100,20 L 0,20 Z`;
};

onMounted(loadDashboard);
</script>

<template>
    <AppLayout>
        <Head title="Dashboard" />
        
        <!-- Header Section -->
        <div class="mb-10 flex justify-between items-end">
            <div class="flex flex-col">
                <span class="text-[10px] font-bold text-sky-400 uppercase tracking-[0.2em] block mb-2 font-mono">Real-time Inventory Overview</span>
                <h1 class="text-3xl font-bold text-white tracking-tight m-0 mb-2">Dashboard Overview</h1>
                <p class="text-zinc-500 text-sm max-w-2xl leading-relaxed">
                    Key performance indicators, inventory valuations, and global system activity across the network.
                </p>
            </div>
            <div class="flex items-center gap-4">
                <button @click="loadDashboard" 
                        class="bg-zinc-900 border border-zinc-800 text-zinc-400 hover:text-white px-6 h-12 font-bold text-[10px] uppercase tracking-[0.2em] transition-all rounded-xl active:scale-95 flex items-center gap-3 shadow-lg hover:border-zinc-700"
                >
                    <i class="pi pi-refresh" :class="{ 'pi-spin': loading }"></i>
                    <span>REFRESH</span>
                </button>
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
                        <span class="text-[8px] font-bold text-zinc-600 font-mono tracking-widest uppercase line-clamp-1">Stock Value</span>
                    </div>
                    <div class="text-zinc-500 text-[10px] font-bold uppercase tracking-wider mb-1 font-mono">Total Inventory Value</div>
                    <div class="text-3xl font-bold leading-none text-white tracking-tighter">{{ formatCurrency(stats.inventory_value) }}</div>
                    
                    <!-- Trend Mini Chart -->
                    <div class="mt-4 flex items-end gap-1 h-8" v-if="stats.stock_value_trend && stats.stock_value_trend.length > 0">
                        <svg class="w-full h-full overflow-visible" viewBox="0 0 100 20" preserveAspectRatio="none">
                            <defs>
                                <linearGradient id="trendGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                                    <stop offset="0%" style="stop-color:rgb(14, 165, 233);stop-opacity:0.3" />
                                    <stop offset="100%" style="stop-color:rgb(14, 165, 233);stop-opacity:0" />
                                </linearGradient>
                            </defs>
                            <path 
                                :d="generatePath(stats.stock_value_trend)" 
                                fill="none" 
                                stroke="#0ea5e9" 
                                stroke-width="1.5" 
                                stroke-linecap="round"
                            />
                            <path 
                                :d="generateFillPath(stats.stock_value_trend)" 
                                fill="url(#trendGradient)"
                            />
                        </svg>
                    </div>
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
                        <span class="text-[8px] font-bold text-zinc-600 font-mono tracking-widest uppercase">Critical Stock</span>
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
                        <span class="text-[8px] font-bold text-zinc-600 font-mono tracking-widest uppercase">System Records</span>
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
            
            <!-- Operations Summary -->
            <div class="group relative bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-6 hover:border-sky-500/40 transition-all duration-500 overflow-hidden shadow-xl">
                 <div class="absolute -right-10 -bottom-10 w-24 h-24 bg-sky-500/5 blur-3xl rounded-full group-hover:bg-sky-500/10 transition-colors"></div>
                 <div class="relative z-10">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-10 h-10 rounded-xl bg-zinc-950 flex items-center justify-center border border-zinc-800 group-hover:border-sky-500/30 transition-colors">
                            <i class="pi pi-briefcase text-zinc-500 group-hover:text-amber-400 text-sm transition-colors"></i>
                        </div>
                        <span class="text-[8px] font-bold text-zinc-600 font-mono tracking-widest uppercase">Operations</span>
                    </div>
                    <div class="text-zinc-500 text-[10px] font-bold uppercase tracking-wider mb-2 font-mono">Pending Workflow</div>
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center justify-between bg-zinc-950/50 p-2 rounded-lg border border-zinc-800/50 hover:border-amber-500/30 transition-colors cursor-pointer" @click="$inertia.visit('/purchase-orders')">
                            <span class="text-[10px] font-bold text-zinc-500 font-mono uppercase">PURCHASE ORDERS</span>
                            <span class="text-sm font-bold text-white tracking-tighter">{{ stats.pending_po_count }}</span>
                        </div>
                        <div class="flex items-center justify-between bg-zinc-950/50 p-2 rounded-lg border border-zinc-800/50 hover:border-amber-500/30 transition-colors cursor-pointer">
                            <span class="text-[10px] font-bold text-zinc-500 font-mono uppercase">SALES ORDERS</span>
                            <span class="text-sm font-bold text-white tracking-tighter">{{ stats.pending_so_count }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Today's Activity -->
            <div class="group relative bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-6 hover:border-emerald-500/40 transition-all duration-500 overflow-hidden shadow-xl">
                <div class="absolute -right-10 -bottom-10 w-24 h-24 bg-emerald-500/5 blur-3xl rounded-full group-hover:bg-emerald-500/10 transition-colors"></div>
                <div class="relative z-10">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-10 h-10 rounded-xl bg-zinc-950 flex items-center justify-center border border-zinc-800 group-hover:border-emerald-500/30 transition-colors">
                            <i class="pi pi-calendar-clock text-zinc-500 group-hover:text-emerald-400 text-sm transition-colors"></i>
                        </div>
                        <span class="text-[8px] font-bold text-zinc-600 font-mono tracking-widest uppercase">Today</span>
                    </div>
                    <div class="text-zinc-500 text-[10px] font-bold uppercase tracking-wider mb-1 font-mono">Transactions Today</div>
                    <div class="text-3xl font-bold font-mono tracking-tighter" :class="stats.transactions_today > 0 ? 'text-emerald-400' : 'text-zinc-600'">
                        {{ stats.transactions_today }}
                    </div>
                    <div class="text-zinc-600 text-[11px] mt-4 font-medium leading-relaxed">Stock movements posted today.</div>
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
                            <span class="text-[9px] font-bold text-zinc-500 font-mono tracking-widest uppercase mb-1">Live Activity</span>
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
                                    <span class="text-[9px] text-zinc-600 font-mono uppercase tracking-widest">{{ t.transaction.reference_number }} • {{ t.transaction.transaction_date }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-8">
                                <div class="flex flex-col items-end">
                                    <span class="text-[10px] font-bold text-zinc-500 uppercase font-mono tracking-widest">Quantity</span>
                                    <span class="text-sm font-bold text-sky-400">{{ t.formatted_quantity }}</span>
                                </div>
                                <div class="w-8 h-8 rounded-full border border-zinc-800 flex items-center justify-center opacity-0 group-hover/item:opacity-100 transition-all">
                                    <i class="pi pi-arrow-right text-[10px] text-zinc-500"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="py-12 flex flex-col items-center justify-center border border-dashed border-zinc-800/50 rounded-2xl bg-zinc-950/20">
                        <i class="pi pi-inbox text-zinc-800 text-3xl mb-4"></i>
                        <span class="text-zinc-600 font-mono text-[10px] uppercase tracking-[0.3em]">No recent activity found</span>
                    </div>
                 </div>
            </div>

            <!-- Side Intelligence Panel: Low Stock Alerts -->
            <div class="col-span-12 lg:col-span-4 flex flex-col gap-8">
                 <div class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-6 flex-1 shadow-xl">
                      <div class="flex items-center justify-between mb-6 pb-4 border-b border-zinc-800/50">
                          <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Critical Low Stock Alerts</span>
                          <span class="bg-red-500/10 text-red-400 text-[9px] font-bold px-2 py-0.5 rounded border border-red-500/20 font-mono">{{ lowStockItems.length }} ITEMS</span>
                      </div>
                      <div class="space-y-4" v-if="lowStockItems.length > 0">
                           <div v-for="item in lowStockItems" :key="item.id" class="flex flex-col gap-2 p-4 bg-zinc-950/50 rounded-xl border border-red-500/20 hover:border-red-500/40 transition-colors cursor-pointer" @click="$inertia.visit('/inventory-center?product_id=' + item.id)">
                                <div class="flex justify-between items-start">
                                     <div class="flex flex-col">
                                         <span class="text-xs font-bold text-white tracking-tight line-clamp-1">{{ item.name }}</span>
                                         <span class="text-[9px] font-bold text-zinc-600 font-mono tracking-widest uppercase mt-1">Shortage: {{ item.formatted_shortage }}</span>
                                     </div>
                                     <div class="flex flex-col items-end">
                                         <span class="text-[10px] font-bold text-red-400 font-mono tracking-tighter">{{ item.formatted_quantity_on_hand }} / {{ item.reorder_point }}</span>
                                     </div>
                                </div>
                                <div class="w-full bg-zinc-800 rounded-full h-1 mt-1 overflow-hidden">
                                     <div class="bg-red-500 h-full rounded-full" :style="{ width: Math.max(5, (Number(item.quantity_on_hand) / Number(item.reorder_point)) * 100) + '%' }"></div>
                                </div>
                           </div>
                      </div>
                      <div v-else class="py-12 flex flex-col items-center justify-center border border-dashed border-emerald-500/20 rounded-xl bg-emerald-500/5">
                           <i class="pi pi-check-circle text-emerald-500 text-3xl mb-4 shadow-[0_0_15px_rgba(16,185,129,0.3)] rounded-full"></i>
                           <span class="text-[9px] font-bold text-emerald-400 uppercase tracking-[0.2em] font-mono">Stock levels nominal</span>
                      </div>
                 </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Scoped styles migrated to Tailwind Utility Classes */
</style>
