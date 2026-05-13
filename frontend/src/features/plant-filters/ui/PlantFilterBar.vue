<script setup>
import { AlertTriangle, CalendarClock, Leaf, ListFilter } from "lucide-vue-next";

defineProps({
    modelValue: { type: String, required: true },
});

const emit = defineEmits(["update:modelValue"]);

const filters = [
    { value: "attention", label: "Требуют ухода", icon: AlertTriangle },
    { value: "overdue", label: "Просрочено", icon: CalendarClock },
    { value: "today", label: "Сегодня", icon: Leaf },
    { value: "all", label: "Все", icon: ListFilter },
];
</script>

<template>
    <div class="plant-filter-bar">
        <button
            v-for="filter in filters"
            :key="filter.value"
            type="button"
            class="plant-filter-bar__item"
            :class="{ 'plant-filter-bar__item--active': modelValue === filter.value }"
            @click="emit('update:modelValue', filter.value)"
        >
            <component :is="filter.icon" :size="15" />
            <span>{{ filter.label }}</span>
        </button>
    </div>
</template>

<style scoped>
.plant-filter-bar {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 2px;
}

.plant-filter-bar__item {
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    gap: 6px;
    min-height: 34px;
    padding: 0 10px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    background: var(--color-surface);
    color: var(--color-muted);
    cursor: pointer;
    font-size: 13px;
    font-weight: 800;
}

.plant-filter-bar__item--active {
    color: #fff;
    border-color: var(--color-green);
    background: var(--color-green);
}
</style>
