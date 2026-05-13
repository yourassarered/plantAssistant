<script setup>
import { computed } from "vue";

import { useCalendarStore } from "@/entities/calendar/model/calendar.store";
import CalendarMonthGrid from "@/entities/calendar/ui/CalendarMonthGrid.vue";
import TaskItem from "@/entities/task/ui/TaskItem.vue";
import { groupTasksByDate } from "@/shared/lib/date/taskMarkers";

const props = defineProps({
    tasks: { type: Array, required: true },
});

const calendarStore = useCalendarStore();
const tasksByDate = computed(() => groupTasksByDate(props.tasks));
const selectedTasks = computed(() => tasksByDate.value[calendarStore.selectedDate] || []);
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
            <h3>{{ calendarStore.selectedDate }}</h3>
            <div v-if="selectedTasks.length" class="day-panel__list">
                <TaskItem v-for="task in selectedTasks" :key="task.id" :task="task" />
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
</style>
