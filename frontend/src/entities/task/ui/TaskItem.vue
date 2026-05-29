<script setup>
import {
    CalendarClock,
    Droplets,
    Leaf,
    RotateCw,
    Scissors,
} from "lucide-vue-next";
import { computed } from "vue";

import CompleteTaskToggle from "@/features/complete-task/ui/CompleteTaskToggle.vue";
import { plantReportIndicator } from "@/shared/lib/reports";
import { careTypes } from "@/shared/lib/careTypes";
import { formatTaskDueDate } from "@/shared/lib/date/calendarGrid";
import { taskDateState } from "@/shared/lib/date/taskMarkers";
import UiBadge from "@/shared/ui/UiBadge.vue";

const props = defineProps({
    task: { type: Object, required: true },
    readonly: { type: Boolean, default: false },
    showPlantName: { type: Boolean, default: true },
    showRoom: { type: Boolean, default: true },
    hideMobileDueBadge: { type: Boolean, default: false },
});

const icons = {
    water: Droplets,
    feed: Leaf,
    prune: Scissors,
    rotate: RotateCw,
};

const state = computed(() => taskDateState(props.task));
const dueLabel = computed(() => formatTaskDueDate(props.task.dueAt));
const reportIndicator = computed(() =>
    plantReportIndicator(props.task.reportSummary),
);
</script>

<template>
    <article
        class="task-item"
        :class="{
            'task-item--done': task.completed,
            'task-item--completing': task.isCompleting,
            'task-item--today': state === 'today' && !task.completed,
            'task-item--hide-mobile-badge': hideMobileDueBadge,
        }"
    >
        <span
            class="task-item__icon"
            :style="{ '--task-color': careTypes[task.type].color }"
        >
            <component :is="icons[task.type]" :size="18" />
        </span>

        <div class="task-item__content">
            <strong>{{ careTypes[task.type].label }}</strong>
            <span>
                <template v-if="showPlantName"
                    >{{ task.plantName }} ·
                </template>
                <template v-if="showRoom">{{ task.room }}</template>
                <template v-if="task.everyDays">
                    <template v-if="showPlantName || showRoom"> · </template>
                    каждые {{ task.everyDays }} дн.
                </template>
            </span>
            <span
                v-if="reportIndicator.visible"
                class="task-item__report-indicator"
                :data-tone="reportIndicator.tone"
            >
                {{ reportIndicator.text }}
            </span>
        </div>

        <UiBadge :tone="state">
            <CalendarClock :size="13" />
            {{ dueLabel }}
        </UiBadge>

        <CompleteTaskToggle v-if="!readonly && task.canComplete" :task="task" />
    </article>
</template>

<style scoped>
.task-item {
    position: relative;
    display: grid;
    grid-template-columns: 38px minmax(0, 1fr) auto auto;
    gap: 10px;
    align-items: center;
    overflow: hidden;
    padding: 10px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    background: var(--color-surface);
    transition:
        border-color 0.24s ease,
        box-shadow 0.24s ease,
        color 0.24s ease;
}

.task-item :deep(.ui-badge),
.task-item :deep(.task-toggle) {
    min-width: 0;
}

.task-item::before {
    position: absolute;
    inset: 0;
    z-index: 0;
    background: linear-gradient(90deg, var(--color-green), #24a653);
    content: "";
    transform: scaleX(0);
    transform-origin: left center;
    transition: transform 0.62s cubic-bezier(0.2, 0.72, 0.18, 1);
}

.task-item > * {
    position: relative;
    z-index: 1;
}

.task-item--done {
    opacity: 0.58;
}

.task-item--today {
    border-color: #f3c49f;
    background: #fff6ef;
}

.task-item--completing {
    border-color: var(--color-green);
    color: #fff;
    box-shadow: 0 14px 32px rgba(22, 132, 58, 0.24);
}

.task-item--completing::before {
    transform: scaleX(1);
}

.task-item--completing .task-item__content span,
.task-item--completing :deep(.ui-badge) {
    color: rgba(255, 255, 255, 0.9);
}

.task-item--completing :deep(.ui-badge) {
    border-color: rgba(255, 255, 255, 0.38);
    background: rgba(255, 255, 255, 0.14);
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

.task-item--completing .task-item__icon {
    color: var(--color-green);
    background: #fff;
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

.task-item__report-indicator {
    display: inline-flex;
    width: fit-content;
    align-items: center;
    min-height: 22px;
    padding: 0 7px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 900;
}

.task-item__report-indicator[data-tone="warning"] {
    color: #815b00;
    background: #fff0b8;
}

.task-item__report-indicator[data-tone="danger"] {
    color: #8f1f10;
    background: #ffd8d2;
}

@media (max-width: 720px) {
    .task-item {
        grid-template-columns: 38px minmax(0, 1fr) auto auto;
        gap: 8px;
    }

    .task-item--hide-mobile-badge {
        grid-template-columns: 38px minmax(0, 1fr) auto;
    }

    .task-item--hide-mobile-badge :deep(.ui-badge) {
        display: none;
    }

    .task-item:not(.task-item--hide-mobile-badge) :deep(.ui-badge) {
        justify-self: end;
        padding: 0 6px;
        font-size: 11px;
        white-space: nowrap;
    }

    .task-item:not(.task-item--hide-mobile-badge) :deep(.ui-badge svg) {
        width: 12px;
        height: 12px;
    }

    .task-item :deep(.task-toggle) {
        justify-self: end;
    }
}
</style>
