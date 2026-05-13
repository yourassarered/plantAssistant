const dayMs = 24 * 60 * 60 * 1000;

export const toIsoDate = (date) => {
    const normalized = new Date(
        date.getFullYear(),
        date.getMonth(),
        date.getDate(),
    );
    const year = normalized.getFullYear();
    const month = String(normalized.getMonth() + 1).padStart(2, "0");
    const day = String(normalized.getDate()).padStart(2, "0");

    return `${year}-${month}-${day}`;
};

export const isSameDay = (left, right) => toIsoDate(left) === toIsoDate(right);

export const getMonthTitle = (date, locale = "ru-RU") =>
    new Intl.DateTimeFormat(locale, { month: "long", year: "numeric" }).format(
        date,
    );

export const createMonthGrid = (activeDate = new Date(), weekStartsOn = 1) => {
    const year = activeDate.getFullYear();
    const month = activeDate.getMonth();
    const firstOfMonth = new Date(year, month, 1);
    const lastOfMonth = new Date(year, month + 1, 0);
    const firstWeekday = (firstOfMonth.getDay() - weekStartsOn + 7) % 7;
    const start = new Date(firstOfMonth.getTime() - firstWeekday * dayMs);
    const lastWeekday = (lastOfMonth.getDay() - weekStartsOn + 7) % 7;
    const end = new Date(lastOfMonth.getTime() + (6 - lastWeekday) * dayMs);
    const days = [];

    for (
        let cursor = new Date(start);
        cursor <= end;
        cursor = new Date(cursor.getTime() + dayMs)
    ) {
        days.push({
            date: new Date(cursor),
            iso: toIsoDate(cursor),
            day: cursor.getDate(),
            isCurrentMonth: cursor.getMonth() === month,
            isToday: isSameDay(cursor, new Date()),
        });
    }

    return days;
};

export const shiftMonth = (date, offset) =>
    new Date(date.getFullYear(), date.getMonth() + offset, 1);
