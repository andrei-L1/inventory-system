<template>
    <div class="min-h-screen bg-gray-50 flex items-center justify-center">
        <div class="text-center">
            <h1 class="text-4xl font-bold text-gray-800">Inventory System</h1>
            <p class="mt-2 text-gray-500">Laravel {{ laravelVersion }} + Vue 3 + Inertia.js</p>
            <p class="mt-4 text-sm text-green-600 font-medium">✅ Setup complete! Your stack is ready.</p>
        </div>
    </div>
</template>

<script setup>
defineProps({
    laravelVersion: String,
});
</script>
