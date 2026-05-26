<script setup>
import { Check } from "lucide-vue-next";
import { ref } from "vue";

import { useCompleteTask } from "../model/useCompleteTask";

const props = defineProps({
    task: { type: Object, required: true },
});

const { toggle } = useCompleteTask();
const completing = ref(false);

const complete = async () => {
    if (props.task.completed || completing.value) return;

    completing.value = true;
    const completed = await toggle(props.task);

    if (!completed) {
        completing.value = false;
        return;
    }

    window.setTimeout(() => {
        completing.value = false;
    }, 280);
};
</script>

<template>
    <button
        class="task-toggle"
        :class="{
            'task-toggle--done': task.completed,
            'task-toggle--completing': completing,
        }"
        type="button"
        :disabled="task.completed || completing"
        @click.stop="complete"
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
    background:
        linear-gradient(var(--color-green), var(--color-green)) 0 100% / 100% 0
            no-repeat,
        #fff;
    cursor: pointer;
    transition:
        border-color 0.2s ease,
        color 0.2s ease,
        background-size 0.28s ease;
}

.task-toggle--done,
.task-toggle--completing {
    color: #fff;
    border-color: var(--color-green);
    background-size:
        100% 100%,
        auto;
}

.task-toggle:disabled {
    cursor: default;
}
</style>
