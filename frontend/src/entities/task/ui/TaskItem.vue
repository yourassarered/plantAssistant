<script setup>
import { CalendarClock, Droplets, Leaf, RotateCw, Scissors } from "lucide-vue-next";

import CompleteTaskToggle from "@/features/complete-task/ui/CompleteTaskToggle.vue";
import { careTypes } from "@/shared/lib/careTypes";
import { taskDateState } from "@/shared/lib/date/taskMarkers";
import UiBadge from "@/shared/ui/UiBadge.vue";

const props = defineProps({
    task: { type: Object, required: true },
});

const icons = {
    water: Droplets,
    feed: Leaf,
    prune: Scissors,
    rotate: RotateCw,
};

const state = taskDateState(props.task);
</script>

<template>
    <article class="task-item" :class="{ 'task-item--done': task.completed }">
        <span class="task-item__icon" :style="{ '--task-color': careTypes[task.type].color }">
            <component :is="icons[task.type]" :size="18" />
        </span>

        <div class="task-item__content">
            <strong>{{ careTypes[task.type].label }}</strong>
            <span>{{ task.plantName }} · {{ task.room }}</span>
        </div>

        <UiBadge :tone="state">
            <CalendarClock :size="13" />
            {{ task.dueAt }}
        </UiBadge>

        <CompleteTaskToggle :task="task" />
    </article>
</template>

<style scoped>
.task-item {
    display: grid;
    grid-template-columns: 38px minmax(0, 1fr) auto auto;
    gap: 10px;
    align-items: center;
    padding: 10px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    background: var(--color-surface);
}

.task-item--done {
    opacity: 0.58;
}

.task-item__icon {
    display: grid;
    width: 38px;
    height: 38px;
    place-items: center;
    border-radius: var(--radius-sm);
    color: #fff;
    background: var(--task-color);
}

.task-item__content {
    display: grid;
    gap: 3px;
    min-width: 0;
}

.task-item__content strong,
.task-item__content span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.task-item__content span {
    color: var(--color-muted);
    font-size: 13px;
}

@media (max-width: 480px) {
    .task-item {
        grid-template-columns: 38px minmax(0, 1fr) auto;
    }

    .task-item :deep(.ui-badge) {
        display: none;
    }
}
</style>
