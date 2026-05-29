<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { ChevronDown } from "lucide-vue-next";
import { useRoute } from "vue-router";

import { usePlantStore } from "@/entities/plant/model/plant.store";
import { useTaskStore } from "@/entities/task/model/task.store";
import TaskItem from "@/entities/task/ui/TaskItem.vue";
import {
    plantReportIndicator,
    sumPlantReportSummaries,
} from "@/shared/lib/reports";
import { taskDateState } from "@/shared/lib/date/taskMarkers";
import UiBadge from "@/shared/ui/UiBadge.vue";

const plantStore = usePlantStore();
const taskStore = useTaskStore();
const route = useRoute();
const mode = ref("active");
const expandedGroups = ref({});

const tasks = computed(() => {
    if (mode.value === "done")
        return taskStore.all.filter((task) => task.completed);
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
                reportSummary: {
                    total: 0,
                    pending: 0,
                    accepted: 0,
                    rejected: 0,
                },
                todayCount: 0,
                items: [],
            });
        }
        const group = groups.get(task.plantId);
        group.items.push(task);
        group.reportSummary = sumPlantReportSummaries([
            group.reportSummary,
            task.reportSummary,
        ]);
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
                expandedGroups.value[String(group.plantId)] ??
                mode.value === "active";
        });
        expandedGroups.value = next;
    },
    { immediate: true },
);

const refresh = async () => {
    const ownPlants = await plantStore.loadOwnPlantsForCare();
    taskStore.syncFromPlants(ownPlants);
};

onMounted(refresh);
watch(
    () => route.fullPath,
    () => {
        if (route.name === "tasks") {
            refresh();
        }
    },
);
</script>

<template>
    <section class="page">
        <header class="page-header">
            <div>
                <h1 class="page-title">Задачи ухода</h1>
                <p class="page-subtitle">
                    Сгруппированы по растениям, с быстрым доступом к действиям.
                </p>
            </div>
        </header>

        <div class="task-tabs">
            <button
                :class="{ active: mode === 'active' }"
                @click="mode = 'active'"
            >
                Активные
            </button>
            <button
                :class="{ active: mode === 'overdue' }"
                @click="mode = 'overdue'"
            >
                Просрочено
            </button>
            <button :class="{ active: mode === 'done' }" @click="mode = 'done'">
                Готово
            </button>
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
                        <span
                            >{{ group.room }} ·
                            {{ group.items.length }} задач</span
                        >
                        <span
                            v-if="
                                plantReportIndicator(group.reportSummary)
                                    .visible
                            "
                            class="task-group__report-indicator"
                            :data-tone="
                                plantReportIndicator(group.reportSummary).tone
                            "
                        >
                            {{ plantReportIndicator(group.reportSummary).text }}
                        </span>
                    </div>
                    <div v-if="group.todayCount > 0" class="task-group__today">
                        <UiBadge tone="today"
                            >Сегодня: {{ group.todayCount }}</UiBadge
                        >
                    </div>
                    <span
                        class="task-group__chevron"
                        :class="{ open: isExpanded(group.plantId) }"
                    >
                        <ChevronDown :size="18" />
                    </span>
                </button>

                <TransitionGroup
                    v-show="isExpanded(group.plantId)"
                    name="task-replace"
                    tag="div"
                    class="task-group__list task-stack"
                >
                    <TaskItem
                        v-for="task in group.items"
                        :key="task.id"
                        :task="task"
                        :show-plant-name="false"
                        :show-room="false"
                    />
                </TransitionGroup>
            </article>

            <UiBadge v-if="!groupedByPlant.length" tone="neutral"
                >Нет задач в этом разделе</UiBadge
            >
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

.task-group__report-indicator {
    display: inline-flex;
    width: fit-content;
    align-items: center;
    min-height: 22px;
    padding: 0 7px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 900;
}

.task-group__report-indicator[data-tone="warning"] {
    color: #815b00;
    background: #fff0b8;
}

.task-group__report-indicator[data-tone="danger"] {
    color: #8f1f10;
    background: #ffd8d2;
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

.task-stack {
    position: relative;
    overflow: hidden;
}

.task-replace-enter-active,
.task-replace-leave-active,
.task-replace-move {
    transition:
        opacity 0.42s cubic-bezier(0.2, 0.72, 0.18, 1),
        transform 0.42s cubic-bezier(0.2, 0.72, 0.18, 1),
        box-shadow 0.42s ease;
}

.task-replace-enter-active {
    animation: task-replace-highlight 0.7s ease;
}

.task-replace-leave-active {
    position: absolute;
    right: 0;
    left: 0;
    z-index: 1;
}

.task-replace-enter-from,
.task-replace-leave-to {
    opacity: 0;
}

.task-replace-enter-from {
    transform: translateX(-36px) scale(0.985);
}

.task-replace-leave-to {
    transform: translateX(48px) scale(0.985);
}

@keyframes task-replace-highlight {
    0% {
        box-shadow: 0 0 0 0 rgba(22, 132, 58, 0);
    }

    34% {
        box-shadow:
            0 0 0 2px rgba(22, 132, 58, 0.22),
            0 12px 28px rgba(22, 132, 58, 0.16);
    }

    100% {
        box-shadow: 0 0 0 0 rgba(22, 132, 58, 0);
    }
}
</style>
