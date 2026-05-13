<script setup>
defineProps({
    tone: { type: String, default: "neutral" },
});
</script>

<template>
    <span class="ui-badge" :class="`ui-badge--${tone}`">
        <slot />
    </span>
</template>

<style scoped>
.ui-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    min-height: 22px;
    padding: 0 8px;
    border-radius: var(--radius-xs);
    font-size: 12px;
    font-weight: 800;
}

.ui-badge--neutral {
    color: var(--color-muted);
    background: #edf1ea;
}

.ui-badge--today {
    color: #87360d;
    background: #ffe0cd;
}

.ui-badge--overdue {
    color: #9b2013;
    background: #ffe0dc;
}

.ui-badge--soon {
    color: var(--color-green-dark);
    background: var(--color-green-soft);
}

.ui-badge--none {
    color: var(--color-muted);
    background: #edf1ea;
}
</style>
