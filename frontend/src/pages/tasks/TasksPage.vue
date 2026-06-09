<script setup>
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from "vue";
import { ChevronDown } from "lucide-vue-next";
import { useRoute, useRouter } from "vue-router";

import { usePlantStore } from "@/entities/plant/model/plant.store";
import { useTaskStore } from "@/entities/task/model/task.store";
import TaskItem from "@/entities/task/ui/TaskItem.vue";
import { plantReportIndicator } from "@/shared/lib/reports";
import { taskDateState } from "@/shared/lib/date/taskMarkers";
import UiBadge from "@/shared/ui/UiBadge.vue";

const plantStore = usePlantStore();
const taskStore = useTaskStore();
const route = useRoute();
const router = useRouter();
const mode = ref("active");
const expandedGroups = ref({});
const highlightedTaskId = ref("");
let highlightTimer = null;

const taskHighlightDurationMs = 2600;
const availableModes = new Set(["active", "overdue", "done"]);

const routeTaskId = () => {
    const task = route.query.task;

    return typeof task === "string" ? task : "";
};

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
                // Сводка жалоб уже относится ко всему растению и повторяется
                // в каждой задаче, поэтому здесь важно не суммировать её повторно.
                reportSummary: task.reportSummary || {
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

const clearFocusedTask = () => {
    if (!routeTaskId()) return;

    router.replace({ name: "tasks" });
};

const setMode = (nextMode) => {
    mode.value = nextMode;
    clearFocusedTask();
};

const expandOnlyPlant = (plantId) => {
    const targetKey = String(plantId);
    const next = {};

    groupedByPlant.value.forEach((group) => {
        next[String(group.plantId)] = String(group.plantId) === targetKey;
    });

    expandedGroups.value = next;
};

const escapedTaskSelector = (taskId) => {
    const escapedTaskId = window.CSS?.escape
        ? window.CSS.escape(taskId)
        : taskId.replace(/["\\]/g, "\\$&");

    return `[data-task-id="${escapedTaskId}"]`;
};

const modeForTask = (task) => {
    if (
        typeof route.query.mode === "string" &&
        availableModes.has(route.query.mode)
    ) {
        return route.query.mode;
    }

    if (task.completed) return "done";
    if (taskDateState(task) === "overdue") return "overdue";

    return "active";
};

const focusTaskById = async (taskId) => {
    if (!taskId) return;

    const task = taskStore.all.find((item) => item.id === taskId);
    if (!task) return;

    mode.value = modeForTask(task);
    await nextTick();

    expandOnlyPlant(task.plantId);

    await nextTick();

    const taskElement = document.querySelector(escapedTaskSelector(taskId));
    if (!taskElement) return;

    taskElement.scrollIntoView({ behavior: "smooth", block: "center" });

    window.clearTimeout(highlightTimer);
    highlightedTaskId.value = "";
    await nextTick();

    highlightedTaskId.value = taskId;
    highlightTimer = window.setTimeout(() => {
        if (highlightedTaskId.value === taskId) {
            highlightedTaskId.value = "";
        }
    }, taskHighlightDurationMs);
};

const focusRouteTask = async () => {
    await focusTaskById(routeTaskId());
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

const refreshAndFocusRouteTask = async () => {
    await refresh();
    await focusRouteTask();
};

const handleExternalTaskFocus = async (event) => {
    const taskId = event.detail?.taskId;
    if (!taskId) return;

    await focusTaskById(taskId);
};

onMounted(() => {
    window.addEventListener(
        "plant-assistant:focus-task",
        handleExternalTaskFocus,
    );
    refreshAndFocusRouteTask();
});
watch(
    () => route.fullPath,
    () => {
        if (route.name === "tasks") {
            refreshAndFocusRouteTask();
        }
    },
);

onBeforeUnmount(() => {
    window.clearTimeout(highlightTimer);
    window.removeEventListener(
        "plant-assistant:focus-task",
        handleExternalTaskFocus,
    );
});
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
                @click="setMode('active')"
            >
                Активные
            </button>
            <button
                :class="{ active: mode === 'overdue' }"
                @click="setMode('overdue')"
            >
                Просрочено
            </button>
            <button
                :class="{ active: mode === 'done' }"
                @click="setMode('done')"
            >
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

                <div
                    class="task-group__body"
                    :class="{
                        'task-group__body--open': isExpanded(group.plantId),
                    }"
                    :aria-hidden="!isExpanded(group.plantId)"
                >
                    <div class="task-group__body-inner">
                        <div class="task-group__list task-stack">
                            <TaskItem
                                v-for="task in group.items"
                                :key="task.id"
                                :task="task"
                                :show-plant-name="false"
                                :show-room="false"
                                :highlighted="highlightedTaskId === task.id"
                                :data-task-id="task.id"
                            />
                        </div>
                    </div>
                </div>
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

.task-group__body {
    display: grid;
    grid-template-rows: 0fr;
    opacity: 0;
    transition:
        grid-template-rows 0.26s cubic-bezier(0.22, 1, 0.36, 1),
        opacity 0.16s ease;
}

.task-group__body--open {
    grid-template-rows: 1fr;
    opacity: 1;
}

.task-group__body-inner {
    min-height: 0;
    overflow: hidden;
}

.task-group__list {
    display: grid;
    gap: 8px;
    padding: 10px;
    transform: translateY(-4px);
    transition: transform 0.22s cubic-bezier(0.22, 1, 0.36, 1);
}

.task-group__body--open .task-group__list {
    transform: translateY(0);
}

.task-stack {
    position: relative;
    overflow: hidden;
}
</style>
