<script setup>
import { computed, onMounted, ref } from "vue";

import { usePlantStore } from "@/entities/plant/model/plant.store";
import { useTaskStore } from "@/entities/task/model/task.store";
import TaskItem from "@/entities/task/ui/TaskItem.vue";
import UiBadge from "@/shared/ui/UiBadge.vue";

const plantStore = usePlantStore();
const taskStore = useTaskStore();
const mode = ref("active");

const tasks = computed(() => {
    if (mode.value === "done") return taskStore.all.filter((task) => task.completed);
    if (mode.value === "overdue") return taskStore.overdueTasks;
    return taskStore.pending;
});

onMounted(async () => {
    if (!plantStore.all.length) {
        await plantStore.loadPlants();
        taskStore.syncFromPlants(plantStore.all);
    }
});
</script>

<template>
    <section class="page">
        <header class="page-header">
            <div>
                <h1 class="page-title">Задачи ухода</h1>
                <p class="page-subtitle">Полив, удобрение, подрезка и поворот в одном списке.</p>
            </div>
        </header>

        <div class="task-tabs">
            <button :class="{ active: mode === 'active' }" @click="mode = 'active'">
                Активные
            </button>
            <button :class="{ active: mode === 'overdue' }" @click="mode = 'overdue'">
                Просрочено
            </button>
            <button :class="{ active: mode === 'done' }" @click="mode = 'done'">
                Готово
            </button>
        </div>

        <div class="task-list">
            <TaskItem v-for="task in tasks" :key="task.id" :task="task" />
            <UiBadge v-if="!tasks.length" tone="neutral">Нет задач в этом разделе</UiBadge>
        </div>
    </section>
</template>

<style scoped>
.task-tabs {
    display: flex;
    gap: 8px;
}

.task-tabs button {
    min-height: 34px;
    padding: 0 12px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    background: var(--color-surface);
    color: var(--color-muted);
    cursor: pointer;
    font-weight: 800;
}

.task-tabs button.active {
    color: #fff;
    border-color: var(--color-green);
    background: var(--color-green);
}

.task-list {
    display: grid;
    gap: 10px;
}
</style>
