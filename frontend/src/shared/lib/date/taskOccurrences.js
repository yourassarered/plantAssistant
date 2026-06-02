import {
    createMonthGrid,
    dateFromIsoDate,
    toIsoDate,
} from "@/shared/lib/date/calendarGrid";

const dayMs = 24 * 60 * 60 * 1000;

const addDays = (isoDate, days) => {
    const date = dateFromIsoDate(isoDate);
    if (!date) return null;

    date.setDate(date.getDate() + days);
    return toIsoDate(date);
};

const daysBetween = (startIso, endIso) => {
    const startDate = dateFromIsoDate(startIso);
    const endDate = dateFromIsoDate(endIso);

    if (!startDate || !endDate) return 0;

    return Math.round((endDate.getTime() - startDate.getTime()) / dayMs);
};

export const calendarRangeForDate = (activeDate, fallbackIsoDate) => {
    const visibleDays = createMonthGrid(activeDate);

    return {
        start: visibleDays[0]?.iso || fallbackIsoDate,
        end: visibleDays[visibleDays.length - 1]?.iso || fallbackIsoDate,
    };
};

export const expandTaskOccurrences = (task, startIso, endIso) => {
    const dueAt = String(task.dueAt || "").slice(0, 10);
    if (!dueAt || !dateFromIsoDate(dueAt)) return [];

    const everyDays = Number(task.everyDays);
    if (task.completed || !Number.isFinite(everyDays) || everyDays <= 0) {
        return dueAt >= startIso && dueAt <= endIso ? [{ ...task, dueAt }] : [];
    }

    let occurrence = dueAt;

    // Календарю нужен полный ряд повторов, а не только ближайшая дата из API.
    if (occurrence < startIso) {
        const skippedIntervals = Math.max(
            0,
            Math.floor(daysBetween(occurrence, startIso) / everyDays),
        );
        occurrence = addDays(occurrence, skippedIntervals * everyDays);
    }

    while (occurrence && occurrence < startIso) {
        occurrence = addDays(occurrence, everyDays);
    }

    const occurrences = [];
    let guard = 0;

    while (occurrence && occurrence <= endIso && guard < 370) {
        occurrences.push({
            ...task,
            id: `${task.id}-repeat-${occurrence}`,
            dueAt: occurrence,
        });
        occurrence = addDays(occurrence, everyDays);
        guard += 1;
    }

    return occurrences;
};

export const expandTasksForRange = (tasks, startIso, endIso) =>
    tasks.flatMap((task) => expandTaskOccurrences(task, startIso, endIso));
