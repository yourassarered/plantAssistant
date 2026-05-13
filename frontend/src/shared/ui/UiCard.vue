<template>
    <article class="ui-card">
        <slot />
    </article>
</template>

<style scoped>
.ui-card {
    border: 1px solid rgba(219, 226, 214, 0.92);
    border-radius: var(--radius-sm);
    background: var(--color-surface);
    box-shadow: var(--shadow-card);
    overflow: hidden;
}
</style>
