<script setup>
const model = defineModel({
    required: true,
});
</script>

<template>
    <select
        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
        v-model="model"
    >
        <slot />
    </select>
</template>
