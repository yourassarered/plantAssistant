import { onBeforeUnmount, watch } from "vue";
import { toast } from "vue-sonner";

import { useAuthStore } from "@/entities/auth/model/auth.store";
import { useNotificationStore } from "@/entities/notification/model/notification.store";
import { usePlantStore } from "@/entities/plant/model/plant.store";
import { useTaskStore } from "@/entities/task/model/task.store";
import { careTypes } from "@/shared/lib/careTypes";
import { todayIsoDate } from "@/shared/lib/date/calendarGrid";

const refreshIntervalMs = 15_000;
let notificationAudioContext = null;

const getNotificationAudioContext = () => {
    const AudioContextConstructor =
        window.AudioContext || window.webkitAudioContext;

    if (!AudioContextConstructor) return null;

    if (!notificationAudioContext) {
        notificationAudioContext = new AudioContextConstructor();
    }

    return notificationAudioContext;
};

const unlockNotificationSound = () => {
    const audioContext = getNotificationAudioContext();

    if (!audioContext || audioContext.state !== "suspended") return;

    void audioContext.resume().catch(() => {});
};

const playNotificationSound = () => {
    const audioContext = getNotificationAudioContext();

    if (!audioContext) return;

    if (audioContext.state === "suspended") {
        void audioContext.resume().catch(() => {});
        return;
    }

    const startedAt = audioContext.currentTime;
    const oscillator = audioContext.createOscillator();
    const gain = audioContext.createGain();

    oscillator.type = "sine";
    oscillator.frequency.setValueAtTime(720, startedAt);
    oscillator.frequency.exponentialRampToValueAtTime(880, startedAt + 0.08);

    gain.gain.setValueAtTime(0.0001, startedAt);
    gain.gain.exponentialRampToValueAtTime(0.08, startedAt + 0.02);
    gain.gain.exponentialRampToValueAtTime(0.0001, startedAt + 0.24);

    oscillator.connect(gain);
    gain.connect(audioContext.destination);
    oscillator.start(startedAt);
    oscillator.stop(startedAt + 0.26);
};

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

    const bindSoundUnlock = () => {
        // Браузеры разрешают звук только после действия пользователя на странице.
        window.addEventListener("pointerdown", unlockNotificationSound, {
            passive: true,
            once: true,
        });
        window.addEventListener("touchstart", unlockNotificationSound, {
            passive: true,
            once: true,
        });
        window.addEventListener("keydown", unlockNotificationSound, {
            once: true,
        });
    };

    const unbindSoundUnlock = () => {
        window.removeEventListener("pointerdown", unlockNotificationSound);
        window.removeEventListener("touchstart", unlockNotificationSound);
        window.removeEventListener("keydown", unlockNotificationSound);
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
            const newNotifications = [];

            taskStore.dueNowTasks
                .filter((task) => !task.completed)
                .forEach((task) => {
                    const notification = notificationStore.notify({
                        key: taskNotificationKey(task),
                        title: taskNotificationTitle(task, today),
                        body: taskNotificationBody(task),
                        type: task.dueAt < today ? "warning" : "care",
                        actionTo: {
                            name: "tasks",
                            query: { task: task.id },
                        },
                        taskId: task.id,
                    });

                    if (notification) {
                        newNotifications.push(notification);
                        toast(notification.title, {
                            description: notification.body,
                        });
                    }
                });

            if (newNotifications.length) {
                playNotificationSound();
            }
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

    const handleWindowFocus = () => {
        refreshCareNotifications();
    };

    bindSoundUnlock();
    document.addEventListener("visibilitychange", handleVisibilityChange);
    window.addEventListener("focus", handleWindowFocus);

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
        window.removeEventListener("focus", handleWindowFocus);
        unbindSoundUnlock();
    });
};
