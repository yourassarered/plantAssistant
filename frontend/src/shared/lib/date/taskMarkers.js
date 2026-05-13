import { careTypes } from "@/shared/lib/careTypes";

export const taskDateState = (task) => {
    const todayIso = new Date().toISOString().slice(0, 10);

    if (task.completed) return "done";
    if (task.dueAt < todayIso) return "overdue";
    if (task.dueAt === todayIso) return "today";
    return "soon";
};

export const groupTasksByDate = (tasks) =>
    tasks.reduce((acc, task) => {
        acc[task.dueAt] = acc[task.dueAt] || [];
        acc[task.dueAt].push(task);
        return acc;
    }, {});

export const summarizePlantCare = (plant) => {
    const todayIso = new Date().toISOString().slice(0, 10);
    const markers = Object.entries(plant.care || {})
        .filter(([, schedule]) => schedule.nextAt)
        .map(([type, schedule]) => ({
            type,
            label: careTypes[type].label,
            color: careTypes[type].color,
            dueAt: schedule.nextAt,
            state:
                schedule.nextAt < todayIso
                    ? "overdue"
                    : schedule.nextAt === todayIso
                      ? "today"
                      : "soon",
        }))
        .sort((a, b) => a.dueAt.localeCompare(b.dueAt));

    return {
        markers,
        primaryState:
            markers.find((marker) => marker.state === "overdue")?.state ||
            markers.find((marker) => marker.state === "today")?.state ||
            (markers.length ? "soon" : "none"),
    };
};
