<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { ChevronDown } from "lucide-vue-next";

import { usePlantStore } from "@/entities/plant/model/plant.store";
import { useTaskStore } from "@/entities/task/model/task.store";
import TaskItem from "@/entities/task/ui/TaskItem.vue";
import { taskDateState } from "@/shared/lib/date/taskMarkers";
import UiBadge from "@/shared/ui/UiBadge.vue";

const plantStore = usePlantStore();
const taskStore = useTaskStore();
const mode = ref("active");
const expandedGroups = ref({});

const tasks = computed(() => {
    if (mode.value === "done") return taskStore.all.filter((task) => task.completed);
    if (mode.value === "overdue") return taskStore.overdueTasks;
    return taskStore.pending;
});

const groupedByPlant = computed(() => {
    const groups = new Map();

    for (const task of tasks.value) {
        if (!groups.has(task.plantId)) {
            groups.set(task.plantId, {
                plantId: task.plantId,
                plantName: task.plantName,
                room: task.room,
                image: task.plantImage,
                todayCount: 0,
                items: [],
            });
        }
        const group = groups.get(task.plantId);
        group.items.push(task);
        if (taskDateState(task) === "today") {
            group.todayCount += 1;
        }
    }

    return Array.from(groups.values());
});

const isExpanded = (plantId) => Boolean(expandedGroups.value[String(plantId)]);

const toggleGroup = (plantId) => {
    const key = String(plantId);
    expandedGroups.value[key] = !expandedGroups.value[key];
};

watch(
    groupedByPlant,
    (groups) => {
        const next = {};
        groups.forEach((group) => {
            next[String(group.plantId)] =
                expandedGroups.value[String(group.plantId)] ?? mode.value === "active";
        });
        expandedGroups.value = next;
    },
    { immediate: true },
);

onMounted(async () => {
    const ownPlants = await plantStore.loadOwnPlantsForCare();
    taskStore.syncFromPlants(ownPlants);
});
</script>

<template>
    <section class="page">
        <header class="page-header">
            <div>
                <h1 class="page-title">Задачи ухода</h1>
                <p class="page-subtitle">Сгруппированы по растениям, с быстрым доступом к действиям.</p>
            </div>
        </header>

        <div class="task-tabs">
            <button :class="{ active: mode === 'active' }" @click="mode = 'active'">Активные</button>
            <button :class="{ active: mode === 'overdue' }" @click="mode = 'overdue'">Просрочено</button>
            <button :class="{ active: mode === 'done' }" @click="mode = 'done'">Готово</button>
        </div>

        <div class="task-groups">
            <article
                v-for="group in groupedByPlant"
                :key="group.plantId"
                class="task-group"
                :class="{ 'task-group--today': group.todayCount > 0 }"
            >
                <button
                    type="button"
                    class="task-group__head"
                    :aria-expanded="isExpanded(group.plantId)"
                    @click="toggleGroup(group.plantId)"
                >
                    <img :src="group.image" :alt="group.plantName" />
                    <div class="task-group__meta">
                        <strong>{{ group.plantName }}</strong>
                        <span>{{ group.room }} · {{ group.items.length }} задач</span>
                    </div>
                    <div v-if="group.todayCount > 0" class="task-group__today">
                        <UiBadge tone="today">Сегодня: {{ group.todayCount }}</UiBadge>
                    </div>
                    <span class="task-group__chevron" :class="{ open: isExpanded(group.plantId) }">
                        <ChevronDown :size="18" />
                    </span>
                </button>

                <div v-show="isExpanded(group.plantId)" class="task-group__list">
                    <TaskItem v-for="task in group.items" :key="task.id" :task="task" />
                </div>
            </article>

            <UiBadge v-if="!groupedByPlant.length" tone="neutral">Нет задач в этом разделе</UiBadge>
        </div>
    </section>
</template>

<style scoped>
.task-tabs {
    display: flex;
    flex-wrap: wrap;
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

.task-groups {
    display: grid;
    gap: 10px;
}

.task-group {
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    overflow: hidden;
    background: var(--color-surface);
}

.task-group--today {
    border-color: #f1bd95;
}

.task-group__head {
    width: 100%;
    border: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    cursor: pointer;
    text-align: left;
    background: #f5f8f2;
}

.task-group__head img {
    width: 34px;
    height: 34px;
    flex-shrink: 0;
    border-radius: 8px;
    object-fit: cover;
}

.task-group__meta {
    display: grid;
    gap: 2px;
}

.task-group__meta span {
    color: var(--color-muted);
    font-size: 12px;
}

.task-group__today {
    margin-left: auto;
}

.task-group__chevron {
    display: inline-flex;
    margin-left: 8px;
    color: var(--color-muted);
    transition: transform 0.18s ease;
}

.task-group__chevron.open {
    transform: rotate(180deg);
}

.task-group__list {
    display: grid;
    gap: 8px;
    padding: 10px;
}
</style>
