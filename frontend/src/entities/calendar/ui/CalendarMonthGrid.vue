<script setup>
import { computed } from "vue";

import { careTypes } from "@/shared/lib/careTypes";
import { createMonthGrid, getMonthTitle } from "@/shared/lib/date/calendarGrid";

const props = defineProps({
    activeDate: { type: Date, required: true },
    selectedDate: { type: String, required: true },
    tasksByDate: { type: Object, required: true },
});

const emit = defineEmits(["select", "next", "prev"]);

const weekdays = ["ПН", "ВТ", "СР", "ЧТ", "ПТ", "СБ", "ВС"];
const days = computed(() => createMonthGrid(props.activeDate));
const title = computed(() => getMonthTitle(props.activeDate));
</script>

<template>
    <section class="calendar-grid">
        <header class="calendar-grid__header">
            <button type="button" aria-label="Предыдущий месяц" @click="emit('prev')">
                ‹
            </button>
            <strong>{{ title }}</strong>
            <button type="button" aria-label="Следующий месяц" @click="emit('next')">
                ›
            </button>
        </header>

        <div class="calendar-grid__weekdays">
            <span v-for="weekday in weekdays" :key="weekday">{{ weekday }}</span>
        </div>

        <div class="calendar-grid__days">
            <button
                v-for="day in days"
                :key="day.iso"
                type="button"
                class="calendar-day"
                :class="{
                    'calendar-day--muted': !day.isCurrentMonth,
                    'calendar-day--today': day.isToday,
                    'calendar-day--selected': day.iso === selectedDate,
                }"
                @click="emit('select', day.iso)"
            >
                <span>{{ day.day }}</span>
                <span class="calendar-day__markers">
                    <i
                        v-for="task in tasksByDate[day.iso] || []"
                        :key="task.id"
                        :style="{ backgroundColor: careTypes[task.type].color }"
                    />
                </span>
            </button>
        </div>
    </section>
</template>

<style scoped>
.calendar-grid {
    display: grid;
    gap: 10px;
}

.calendar-grid__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    text-transform: capitalize;
}

.calendar-grid__header button {
    display: grid;
    width: 34px;
    height: 34px;
    place-items: center;
    border: 0;
    border-radius: var(--radius-xs);
    background: var(--color-green-soft);
    color: var(--color-green-dark);
    cursor: pointer;
    font-size: 22px;
    font-weight: 800;
}

.calendar-grid__weekdays,
.calendar-grid__days {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 6px;
}

.calendar-grid__weekdays span {
    color: var(--color-muted);
    text-align: center;
    font-size: 11px;
    font-weight: 900;
}

.calendar-day {
    position: relative;
    display: grid;
    min-height: 48px;
    place-items: center;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-xs);
    background: var(--color-surface);
    cursor: pointer;
}

.calendar-day--muted {
    color: #a8b0a3;
    background: #f3f5f0;
}

.calendar-day--today {
    border-color: var(--color-orange);
}

.calendar-day--selected {
    color: #fff;
    background: var(--color-green);
}

.calendar-day__markers {
    position: absolute;
    right: 5px;
    bottom: 5px;
    left: 5px;
    display: flex;
    justify-content: center;
    gap: 3px;
}

.calendar-day__markers i {
    width: 5px;
    height: 5px;
    border-radius: 50%;
}
</style>
