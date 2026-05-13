<script setup>
import { computed } from "vue";
import { clsx } from "clsx";

const props = defineProps({
    variant: { type: String, default: "primary" },
    type: { type: String, default: "button" },
});

const classes = computed(() =>
    clsx("ui-button", `ui-button--${props.variant}`),
);
</script>

<template>
    <button :type="type" :class="classes">
        <slot />
    </button>
</template>

<style scoped>
.ui-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 38px;
    padding: 0 14px;
    border: 0;
    border-radius: var(--radius-sm);
    cursor: pointer;
    font-weight: 800;
}

.ui-button--primary {
    color: #fff;
    background: var(--color-green);
}

.ui-button--ghost {
    color: var(--color-green-dark);
    background: var(--color-green-soft);
}

.ui-button--danger {
    color: #fff;
    background: var(--color-red);
}
</style>
