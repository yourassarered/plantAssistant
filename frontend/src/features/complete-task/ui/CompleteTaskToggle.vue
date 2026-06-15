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
        :disabled="task.completed"
        @click.stop="complete"
    >
        <Check :size="16" />
    </button>
</template>

<style scoped>
.task-toggle {
    position: relative;
    display: grid;
    overflow: hidden;
    width: 32px;
    height: 32px;
    place-items: center;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    color: transparent;
    background: #fff;
    cursor: pointer;
    transition:
        border-color 0.2s ease,
        color 0.2s ease,
        transform 0.2s ease;
    -webkit-tap-highlight-color: transparent;
}

.task-toggle::before {
    content: "";
    position: absolute;
    inset: 0;
    z-index: 0;
    background: var(--color-green);
    transform: translateY(101%);
    transition: transform 0.32s cubic-bezier(0.2, 0.8, 0.2, 1);
}

.task-toggle svg {
    position: relative;
    z-index: 1;
}

.task-toggle--done,
.task-toggle--completing {
    color: #fff;
    border-color: var(--color-green);
}

.task-toggle--done::before,
.task-toggle--completing::before {
    transform: translateY(0);
}

.task-toggle--completing {
    animation: task-toggle-pop 0.36s ease both;
}

.task-toggle:disabled {
    cursor: default;
}

@keyframes task-toggle-pop {
    0% {
        transform: scale(1);
    }

    45% {
        transform: scale(1.08);
    }

    100% {
        transform: scale(1);
    }
}
</style>
