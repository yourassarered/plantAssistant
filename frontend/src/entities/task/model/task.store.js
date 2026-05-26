import { defineStore } from "pinia";

import { apiClient } from "@/shared/api/client";
import { careTypes, uiCareTypeToApi } from "@/shared/lib/careTypes";
import {
    dateFromIsoDate,
    localIsoDateTimeWithOffset,
    todayIsoDate,
    toIsoDate,
} from "@/shared/lib/date/calendarGrid";
import { taskDateState } from "@/shared/lib/date/taskMarkers";

const careCompletionReplaceDelayMs = 920;

const wait = (delayMs) =>
    new Promise((resolve) => {
        window.setTimeout(resolve, delayMs);
    });

const buildTasksFromPlants = (plants) =>
    plants.flatMap((plant) => {
        const scheduledTasks = Object.entries(plant.care || {}).map(
            ([type, schedule]) => ({
                id: `${plant.id}-${type}-${schedule.nextAt}`,
                careSettingId: schedule.id,
                plantId: plant.apiId || plant.id,
                plantName: plant.name,
                room: plant.room,
                plantImage: plant.image,
                type,
                dueAt: schedule.nextAt,
                everyDays: schedule.everyDays,
                completed: false,
                canComplete: Boolean(plant.canCompleteCare),
            }),
        );
        const todayIso = todayIsoDate();
        const completedTodayTasks = (plant.careLogs || [])
            .filter(
                (log) => log.performedAt === todayIso && careTypes[log.type],
            )
            .map((log) => ({
                id: `${plant.id}-${log.type}-completed-${log.id}`,
                careSettingId: null,
                plantId: plant.apiId || plant.id,
                plantName: plant.name,
                room: plant.room,
                plantImage: plant.image,
                type: log.type,
                dueAt: todayIso,
                everyDays: plant.care?.[log.type]?.everyDays,
                completed: true,
                canComplete: Boolean(plant.canCompleteCare),
            }));

        return [
            ...scheduledTasks.filter(
                (task) =>
                    !completedTodayTasks.some(
                        (completedTask) =>
                            completedTask.plantId === task.plantId &&
                            completedTask.type === task.type &&
                            task.dueAt <= todayIso,
                    ),
            ),
            ...completedTodayTasks,
        ];
    });

export const useTaskStore = defineStore("tasks", {
    state: () => ({
        tasks: [],
        logsByPlant: {},
        loading: false,
        error: "",
    }),
    getters: {
        all: (state) => state.tasks,
        pending: (state) => state.tasks.filter((task) => !task.completed),
        byDate: (state) => (isoDate) =>
            state.tasks.filter((task) => task.dueAt === isoDate),
        todayTasks: (state) =>
            state.tasks.filter((task) => taskDateState(task) === "today"),
        overdueTasks: (state) =>
            state.tasks.filter((task) => taskDateState(task) === "overdue"),
        upcomingTasks: (state) =>
            state.tasks
                .filter((task) => taskDateState(task) === "soon")
                .sort((a, b) => a.dueAt.localeCompare(b.dueAt)),
        dueNowTasks: (state) => {
            const todayIso = todayIsoDate();

            return state.tasks.filter((task) => task.dueAt <= todayIso);
        },
        completedCount: (state) =>
            state.tasks.filter((task) => task.completed).length,
        dueNowCompletedCount: (state) => {
            const todayIso = todayIsoDate();

            return state.tasks.filter(
                (task) => task.dueAt <= todayIso && task.completed,
            ).length;
        },
    },
    actions: {
        syncFromPlants(plants) {
            this.tasks = buildTasksFromPlants(plants);
        },
        async completeTask(task) {
            if (task.completed) return;

            if (!apiClient.token) {
                throw new Error("Нужно войти, чтобы отмечать уход.");
            }

            if (!task.canComplete) {
                throw new Error("Нет доступа к уходу за этим растением.");
            }

            this.markTaskCompleting(task.id, true);

            try {
                const payload = await apiClient.post(
                    `/plants/${task.plantId}/care-logs`,
                    {
                        type: uiCareTypeToApi[task.type],
                        performed_at: localIsoDateTimeWithOffset(),
                    },
                );

                const nextDueDate = payload?.data?.care_setting?.next_due_date;

                await wait(careCompletionReplaceDelayMs);
                this.completeTaskLocally(task, nextDueDate);
                await this.loadCareLogs(task.plantId);
            } catch (error) {
                this.markTaskCompleting(task.id, false);
                throw error;
            }
        },
        async loadCareLogs(plantId) {
            if (!apiClient.token) return [];

            this.loading = true;
            this.error = "";

            try {
                const payload = await apiClient.get(
                    `/plants/${plantId}/care-logs?per_page=50`,
                );
                const logs = payload.data || [];
                this.logsByPlant[String(plantId)] = logs;
                return logs;
            } catch (error) {
                this.error = error.message;
                return [];
            } finally {
                this.loading = false;
            }
        },
        completeTaskLocally(task, serverNextDueDate = null) {
            const taskIndex = this.tasks.findIndex(
                (item) => item.id === task.id,
            );
            const storedTask = this.tasks[taskIndex];
            if (!storedTask) return;

            const everyDays = Number(storedTask.everyDays || task.everyDays);
            if (!Number.isFinite(everyDays) || everyDays <= 0) {
                this.tasks.splice(taskIndex, 1, {
                    ...storedTask,
                    isCompleting: false,
                    completed: true,
                });
                return;
            }

            const nextAt =
                serverNextDueDate ||
                (() => {
                    const today = dateFromIsoDate(todayIsoDate());

                    return toIsoDate(
                        new Date(
                            today.getFullYear(),
                            today.getMonth(),
                            today.getDate() + everyDays,
                        ),
                    );
                })();

            this.tasks.splice(taskIndex, 1, {
                ...storedTask,
                id: `${storedTask.plantId}-${storedTask.type}-${nextAt}`,
                dueAt: nextAt,
                isCompleting: false,
                completed: false,
            });
        },
        markTaskCompleting(taskId, isCompleting) {
            const taskIndex = this.tasks.findIndex(
                (item) => item.id === taskId,
            );

            if (taskIndex === -1) return;

            this.tasks.splice(taskIndex, 1, {
                ...this.tasks[taskIndex],
                isCompleting,
            });
        },
    },
    persist: {
        key: "plant-assistant-tasks",
        pick: [],
    },
});
