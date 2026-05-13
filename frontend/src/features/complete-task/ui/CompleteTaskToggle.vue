<script setup>
import { Check } from "lucide-vue-next";

import { useCompleteTask } from "../model/useCompleteTask";

defineProps({
    task: { type: Object, required: true },
});

const { toggle } = useCompleteTask();
</script>

<template>
    <button
        class="task-toggle"
        :class="{ 'task-toggle--done': task.completed }"
        type="button"
        @click.stop="toggle(task)"
    >
        <Check :size="16" />
    </button>
</template>

<style scoped>
.task-toggle {
    display: grid;
    width: 32px;
    height: 32px;
    place-items: center;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    color: transparent;
    background: #fff;
    cursor: pointer;
}

.task-toggle--done {
    color: #fff;
    border-color: var(--color-green);
    background: var(--color-green);
}
</style>
