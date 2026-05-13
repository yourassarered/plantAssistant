<script setup>
defineProps({
    label: { type: String, required: true },
    error: { type: String, default: "" },
});
</script>

<template>
    <label class="ui-field">
        <span>{{ label }}</span>
        <slot />
        <small v-if="error">{{ error }}</small>
    </label>
</template>

<style scoped>
.ui-field {
    display: grid;
    gap: 6px;
    color: var(--color-muted);
    font-size: 13px;
    font-weight: 800;
}

.ui-field :deep(input),
.ui-field :deep(textarea),
.ui-field :deep(select) {
    width: 100%;
    min-height: 40px;
    padding: 9px 10px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    outline: 0;
    background: var(--color-surface);
    color: var(--color-ink);
}

.ui-field small {
    color: var(--color-red);
}
</style>
