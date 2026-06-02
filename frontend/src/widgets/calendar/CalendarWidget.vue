<script setup>
import { computed } from "vue";
import { Droplets, Leaf, RotateCw, Scissors } from "lucide-vue-next";

import { useCalendarStore } from "@/entities/calendar/model/calendar.store";
import CalendarMonthGrid from "@/entities/calendar/ui/CalendarMonthGrid.vue";
import { careTypes } from "@/shared/lib/careTypes";
import {
    calendarRangeForDate,
    expandTasksForRange,
} from "@/shared/lib/date/taskOccurrences";
import { groupTasksByDate } from "@/shared/lib/date/taskMarkers";

const props = defineProps({
    tasks: { type: Array, required: true },
});

const calendarStore = useCalendarStore();
const visibleRange = computed(() => {
    return calendarRangeForDate(
        calendarStore.activeDateObject,
        calendarStore.selectedDate,
    );
});
const visibleTasks = computed(() =>
    expandTasksForRange(
        props.tasks,
        visibleRange.value.start,
        visibleRange.value.end,
    ),
);
const tasksByDate = computed(() => groupTasksByDate(visibleTasks.value));
const selectedTasks = computed(
    () =>
        groupTasksByDate(
            expandTasksForRange(
                props.tasks,
                calendarStore.selectedDate,
                calendarStore.selectedDate,
            ),
        )[calendarStore.selectedDate] || [],
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
            <TransitionGroup
                v-if="selectedTasks.length"
                name="calendar-task"
                tag="div"
                class="day-panel__list"
            >
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
                </article>
            </TransitionGroup>

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

.day-panel p {
    margin: 0;
}

.day-panel p {
    color: var(--color-muted);
}

.day-panel__list {
    position: relative;
    display: grid;
    gap: 8px;
}

.calendar-task {
    display: grid;
    grid-template-columns: 44px 30px minmax(0, 1fr);
    align-items: center;
    gap: 8px;
    padding: 8px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    background: #f7faf5;
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease,
        border-color 0.2s ease;
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

.calendar-task-enter-active,
.calendar-task-leave-active,
.calendar-task-move {
    transition:
        opacity 0.18s ease,
        transform 0.18s ease;
}

.calendar-task-enter-from,
.calendar-task-leave-to {
    opacity: 0;
    transform: translateY(4px);
}

.calendar-task-leave-active {
    position: absolute;
    right: 0;
    left: 0;
}

@media (hover: hover) and (pointer: fine) {
    .calendar-task:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 20px rgba(24, 45, 28, 0.08);
    }
}

@media (max-width: 680px) {
    .calendar-task {
        grid-template-columns: 40px 28px minmax(0, 1fr);
    }

    .calendar-task__image {
        width: 40px;
        height: 40px;
    }
}
</style>
