import { defineStore } from "pinia";

import { shiftMonth, toIsoDate } from "@/shared/lib/date/calendarGrid";

export const useCalendarStore = defineStore("calendar", {
    state: () => ({
        activeDate: toIsoDate(new Date()),
        selectedDate: toIsoDate(new Date()),
    }),
    getters: {
        activeDateObject: (state) => new Date(`${state.activeDate}T00:00:00`),
    },
    actions: {
        selectDate(isoDate) {
            this.selectedDate = isoDate;
        },
        nextMonth() {
            this.activeDate = toIsoDate(shiftMonth(this.activeDateObject, 1));
        },
        prevMonth() {
            this.activeDate = toIsoDate(shiftMonth(this.activeDateObject, -1));
        },
    },
    persist: {
        key: "plant-assistant-calendar",
    },
});
