<script setup>
import { computed } from "vue";
import {
    CalendarClock,
    Droplets,
    Leaf,
    RotateCw,
    Scissors,
} from "lucide-vue-next";

import { useCalendarStore } from "@/entities/calendar/model/calendar.store";
import CalendarMonthGrid from "@/entities/calendar/ui/CalendarMonthGrid.vue";
import { careTypes } from "@/shared/lib/careTypes";
import { formatIsoDate } from "@/shared/lib/date/calendarGrid";
import { groupTasksByDate, taskDateState } from "@/shared/lib/date/taskMarkers";
import UiBadge from "@/shared/ui/UiBadge.vue";

const props = defineProps({
    tasks: { type: Array, required: true },
});

const calendarStore = useCalendarStore();
const tasksByDate = computed(() => groupTasksByDate(props.tasks));
const selectedTasks = computed(
    () => tasksByDate.value[calendarStore.selectedDate] || [],
);
const selectedDateLabel = computed(() =>
    formatIsoDate(calendarStore.selectedDate),
);

const icons = {
    water: Droplets,
    feed: Leaf,
    prune: Scissors,
    rotate: RotateCw,
};
</script>

<template>
    <div class="calendar-widget">
        <CalendarMonthGrid
            :active-date="calendarStore.activeDateObject"
            :selected-date="calendarStore.selectedDate"
            :tasks-by-date="tasksByDate"
            @select="calendarStore.selectDate"
            @next="calendarStore.nextMonth"
            @prev="calendarStore.prevMonth"
        />

        <section class="day-panel">
            <h3>{{ selectedDateLabel }}</h3>

            <div v-if="selectedTasks.length" class="day-panel__list">
                <article
                    v-for="task in selectedTasks"
                    :key="task.id"
                    class="calendar-task"
                >
                    <img
                        :src="task.plantImage"
                        :alt="task.plantName"
                        class="calendar-task__image"
                    />

                    <span
                        class="calendar-task__icon"
                        :style="{ '--task-color': careTypes[task.type].color }"
                    >
                        <component :is="icons[task.type]" :size="14" />
                    </span>

                    <div class="calendar-task__content">
                        <strong>{{ task.plantName }}</strong>
                        <span
                            >{{ careTypes[task.type].label }} ·
                            {{ task.room }}</span
                        >
                    </div>

                    <UiBadge :tone="taskDateState(task)">
                        <CalendarClock :size="12" />
                        {{ formatIsoDate(task.dueAt) }}
                    </UiBadge>
                </article>
            </div>

            <p v-else>На выбранный день задач нет.</p>
        </section>
    </div>
</template>

<style scoped>
.calendar-widget {
    display: grid;
    gap: 14px;
}

.day-panel {
    display: grid;
    gap: 10px;
}

.day-panel h3,
.day-panel p {
    margin: 0;
}

.day-panel p {
    color: var(--color-muted);
}

.day-panel__list {
    display: grid;
    gap: 8px;
}

.calendar-task {
    display: grid;
    grid-template-columns: 44px 30px minmax(0, 1fr) auto;
    align-items: center;
    gap: 8px;
    padding: 8px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    background: #f7faf5;
}

.calendar-task__image {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    object-fit: cover;
}

.calendar-task__icon {
    display: grid;
    width: 30px;
    height: 30px;
    place-items: center;
    border-radius: 8px;
    color: #fff;
    background: var(--task-color);
}

.calendar-task__content {
    display: grid;
    gap: 2px;
    min-width: 0;
}

.calendar-task__content strong,
.calendar-task__content span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.calendar-task__content span {
    color: var(--color-muted);
    font-size: 12px;
}

@media (max-width: 680px) {
    .calendar-task {
        grid-template-columns: 40px 28px minmax(0, 1fr);
    }

    .calendar-task :deep(.ui-badge) {
        grid-column: 1 / -1;
        justify-self: start;
    }

    .calendar-task__image {
        width: 40px;
        height: 40px;
    }
}
</style>
