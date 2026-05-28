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
    transition:
        transform 0.18s ease,
        box-shadow 0.18s ease,
        background-color 0.18s ease,
        color 0.18s ease,
        opacity 0.18s ease;
}

.ui-button:disabled {
    cursor: not-allowed;
    opacity: 0.58;
}

.ui-button:focus-visible {
    outline: 2px solid rgba(15, 112, 46, 0.26);
    outline-offset: 2px;
}

.ui-button--primary {
    color: #fff;
    background: var(--color-green);
    box-shadow: 0 12px 22px rgba(15, 112, 46, 0.18);
}

.ui-button--ghost {
    color: var(--color-green-dark);
    background: var(--color-green-soft);
}

.ui-button--danger {
    color: #fff;
    background: var(--color-red);
}

@media (hover: hover) and (pointer: fine) {
    .ui-button:hover:not(:disabled) {
        transform: translateY(-1px);
    }

    .ui-button--primary:hover:not(:disabled) {
        box-shadow: 0 14px 26px rgba(15, 112, 46, 0.22);
        background: #0c5e2a;
    }

    .ui-button--ghost:hover:not(:disabled) {
        background: #dff0df;
    }

    .ui-button--danger:hover:not(:disabled) {
        background: #c43c2d;
    }
}

.ui-button:active:not(:disabled) {
    transform: translateY(0);
}
</style>
