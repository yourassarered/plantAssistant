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

export const todayIsoDate = () => toIsoDate(new Date());

export const isSameDay = (left, right) => toIsoDate(left) === toIsoDate(right);

export const dateFromIsoDate = (value) => {
    if (!value) return null;

    const [year, month, day] = String(value)
        .slice(0, 10)
        .split("-")
        .map(Number);
    if (!year || !month || !day) return null;

    return new Date(year, month - 1, day);
};

export const formatIsoDate = (value) => {
    if (!value) return "";

    const stringValue = String(value);
    const date = /^\d{4}-\d{2}-\d{2}$/.test(stringValue)
        ? dateFromIsoDate(stringValue)
        : new Date(stringValue);

    if (!date || Number.isNaN(date.getTime())) return stringValue;

    const day = String(date.getDate()).padStart(2, "0");
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const year = date.getFullYear();

    return `${day}-${month}-${year}`;
};

export const formatIsoDateTime = (value) => {
    if (!value) return "";

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return formatIsoDate(value);

    const hours = String(date.getHours()).padStart(2, "0");
    const minutes = String(date.getMinutes()).padStart(2, "0");

    return `${formatIsoDate(value)} ${hours}:${minutes}`;
};

export const localIsoDateTimeWithOffset = (date = new Date()) => {
    const offsetMinutes = -date.getTimezoneOffset();
    const sign = offsetMinutes >= 0 ? "+" : "-";
    const absOffsetMinutes = Math.abs(offsetMinutes);
    const offsetHours = String(Math.floor(absOffsetMinutes / 60)).padStart(
        2,
        "0",
    );
    const offsetRestMinutes = String(absOffsetMinutes % 60).padStart(2, "0");
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    const hours = String(date.getHours()).padStart(2, "0");
    const minutes = String(date.getMinutes()).padStart(2, "0");
    const seconds = String(date.getSeconds()).padStart(2, "0");

    return `${year}-${month}-${day}T${hours}:${minutes}:${seconds}${sign}${offsetHours}:${offsetRestMinutes}`;
};

export const daysUntilIsoDate = (value) => {
    const date = dateFromIsoDate(value);
    const today = dateFromIsoDate(todayIsoDate());

    if (!date || !today) return null;

    return Math.round((date.getTime() - today.getTime()) / dayMs);
};

const pluralizeDays = (count) => {
    const mod10 = count % 10;
    const mod100 = count % 100;

    if (mod10 === 1 && mod100 !== 11) return "день";
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) {
        return "дня";
    }

    return "дней";
};

export const formatTaskDueDate = (value) => {
    const daysUntil = daysUntilIsoDate(value);

    if (daysUntil === 0) return "сегодня";
    if (daysUntil === 1) return "завтра";
    if (daysUntil !== null && daysUntil > 0 && daysUntil < 7) {
        return `через ${daysUntil} ${pluralizeDays(daysUntil)}`;
    }

    return formatIsoDate(value);
};

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
