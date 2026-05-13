import { defineStore } from "pinia";

import { apiClient } from "@/shared/api/client";
import { uiCareTypeToApi } from "@/shared/lib/careTypes";
import { taskDateState } from "@/shared/lib/date/taskMarkers";

const buildTasksFromPlants = (plants) =>
    plants.flatMap((plant) =>
        Object.entries(plant.care || {}).map(([type, schedule]) => ({
            id: `${plant.id}-${type}-${schedule.nextAt}`,
            careSettingId: schedule.id,
            plantId: plant.apiId || plant.id,
            plantName: plant.name,
            room: plant.room,
            plantImage: plant.image,
            type,
            dueAt: schedule.nextAt,
            completed: false,
        })),
    );

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
        byDate: (state) => (isoDate) => state.tasks.filter((task) => task.dueAt === isoDate),
        todayTasks: (state) => state.tasks.filter((task) => taskDateState(task) === "today"),
        overdueTasks: (state) => state.tasks.filter((task) => taskDateState(task) === "overdue"),
        upcomingTasks: (state) =>
            state.tasks
                .filter((task) => taskDateState(task) === "soon")
                .sort((a, b) => a.dueAt.localeCompare(b.dueAt)),
        completedCount: (state) => state.tasks.filter((task) => task.completed).length,
    },
    actions: {
        syncFromPlants(plants) {
            this.tasks = buildTasksFromPlants(plants);
        },
        async completeTask(task) {
            if (!apiClient.token) {
                throw new Error("Нужно войти, чтобы отмечать уход.");
            }

            await apiClient.post(`/plants/${task.plantId}/care-logs`, {
                type: uiCareTypeToApi[task.type],
                performed_at: new Date().toISOString(),
                comment: "Отмечено из фронтенда",
            });

            this.toggleTask(task.id);
            await this.loadCareLogs(task.plantId);
        },
        async loadCareLogs(plantId) {
            if (!apiClient.token) return [];

            this.loading = true;
            this.error = "";

            try {
                const payload = await apiClient.get(`/plants/${plantId}/care-logs?per_page=50`);
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
        toggleTask(taskId) {
            const task = this.tasks.find((item) => item.id === taskId);
            if (task) task.completed = !task.completed;
        },
    },
    persist: {
        key: "plant-assistant-tasks",
        pick: [],
    },
});
