import { onBeforeUnmount, watch } from "vue";
import { toast } from "vue-sonner";

import { useAuthStore } from "@/entities/auth/model/auth.store";
import { useNotificationStore } from "@/entities/notification/model/notification.store";
import { usePlantStore } from "@/entities/plant/model/plant.store";
import { useTaskStore } from "@/entities/task/model/task.store";
import { careTypes } from "@/shared/lib/careTypes";
import { todayIsoDate } from "@/shared/lib/date/calendarGrid";

const refreshIntervalMs = 60_000;

const taskNotificationKey = (task) =>
    `care:${task.plantId}:${task.type}:${task.dueAt}`;

const taskNotificationTitle = (task, today) =>
    task.dueAt < today ? "Просрочен уход" : "Уход сегодня";

const taskNotificationBody = (task) => {
    const careLabel = careTypes[task.type]?.label || "Уход";
    const room = task.room ? `, ${task.room}` : "";

    return `${careLabel}: ${task.plantName}${room}`;
};

export const useInAppNotifications = () => {
    const authStore = useAuthStore();
    const plantStore = usePlantStore();
    const taskStore = useTaskStore();
    const notificationStore = useNotificationStore();
    let refreshTimer = null;
    let isRefreshing = false;

    const stopRefresh = () => {
        window.clearInterval(refreshTimer);
        refreshTimer = null;
    };

    const refreshCareNotifications = async () => {
        if (
            !authStore.isAuthenticated ||
            document.visibilityState !== "visible" ||
            isRefreshing
        ) {
            return;
        }

        isRefreshing = true;

        try {
            const plants = await plantStore.loadOwnPlantsForCare();
            taskStore.syncFromPlants(plants);

            const today = todayIsoDate();
            taskStore.dueNowTasks
                .filter((task) => !task.completed)
                .forEach((task) => {
                    const notification = notificationStore.notify({
                        key: taskNotificationKey(task),
                        title: taskNotificationTitle(task, today),
                        body: taskNotificationBody(task),
                        type: task.dueAt < today ? "warning" : "care",
                        actionTo: "/tasks",
                    });

                    if (notification) {
                        toast(notification.title, {
                            description: notification.body,
                        });
                    }
                });
        } catch {
            // Уведомления не должны ломать основной экран, если API временно недоступен.
        } finally {
            isRefreshing = false;
        }
    };

    const startRefresh = () => {
        if (refreshTimer || !authStore.isAuthenticated) return;

        refreshCareNotifications();
        refreshTimer = window.setInterval(
            refreshCareNotifications,
            refreshIntervalMs,
        );
    };

    const handleVisibilityChange = () => {
        if (document.visibilityState === "visible") {
            refreshCareNotifications();
        }
    };

    document.addEventListener("visibilitychange", handleVisibilityChange);

    watch(
        () => authStore.isAuthenticated,
        (isAuthenticated) => {
            if (isAuthenticated) {
                startRefresh();
                return;
            }

            stopRefresh();
            notificationStore.clear(true);
        },
        { immediate: true },
    );

    onBeforeUnmount(() => {
        stopRefresh();
        document.removeEventListener(
            "visibilitychange",
            handleVisibilityChange,
        );
    });
};
