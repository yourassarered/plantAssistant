<script setup>
import { X } from "lucide-vue-next";
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { toast } from "vue-sonner";

import { useAuthStore } from "@/entities/auth/model/auth.store";
import { useCalendarStore } from "@/entities/calendar/model/calendar.store";
import { useSocialStore } from "@/entities/social/model/social.store";
import { apiClient } from "@/shared/api/client";
import { mapApiPlant, unwrapApiCollection } from "@/shared/api/mappers";
import { todayIsoDate } from "@/shared/lib/date/calendarGrid";
import UiButton from "@/shared/ui/UiButton.vue";
import CalendarWidget from "@/widgets/calendar/CalendarWidget.vue";
import PlantListWidget from "@/widgets/plants/PlantListWidget.vue";

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const calendarStore = useCalendarStore();
const socialStore = useSocialStore();

const loading = ref(false);
const error = ref("");
const profile = ref(null);
const plants = ref([]);
const pagination = ref(null);
const isFollowing = ref(false);
const theyFollowMe = ref(false);
const followersCount = ref(null);
const followingCount = ref(null);
const followLoading = ref(false);
const suggestPlant = ref(null);
const suggestText = ref("");
const sendingTip = ref(false);
const calendarRevision = ref(0);
let loadRequestId = 0;

const profileUserId = computed(() => String(route.params.id || ""));
const isOwnProfile = computed(
    () =>
        authStore.isAuthenticated &&
        String(authStore.user?.id || "") === profileUserId.value,
);

const user = computed(() => {
    if (profile.value) return profile.value;
    const rawUser =
        plants.value[0]?.raw?.user?.data || plants.value[0]?.raw?.user || null;
    if (!rawUser) return null;

    return {
        id: rawUser.id,
        name: rawUser.name || "Пользователь",
        rank: rawUser.rank ?? 0,
        avatar_url: rawUser.avatar_url || "",
    };
});

const totalLikes = computed(() =>
    plants.value.reduce((sum, plant) => sum + Number(plant.likesCount || 0), 0),
);
const sendTipButtonLabel = computed(() =>
    sendingTip.value
        ? "\u041e\u0442\u043f\u0440\u0430\u0432\u043b\u044f\u0435\u043c..."
        : "\u041e\u0442\u043f\u0440\u0430\u0432\u0438\u0442\u044c \u0441\u043e\u0432\u0435\u0442",
);

const buildPublicCareTasks = (sourcePlants) =>
    sourcePlants.flatMap((plant) =>
        Object.entries(plant.care || {}).map(([type, schedule]) => ({
            id: `public-${plant.id}-${type}-${schedule.nextAt}`,
            plantId: plant.apiId || plant.id,
            plantName: plant.name,
            room: plant.room,
            plantImage: plant.image,
            type,
            dueAt: schedule.nextAt,
            completed: false,
        })),
    );

const publicCareTasks = computed(() => buildPublicCareTasks(plants.value));
const calendarKey = computed(() =>
    [
        profileUserId.value,
        calendarRevision.value,
        ...publicCareTasks.value.map((task) => task.id),
    ].join("|"),
);

const loadUserFeedPlants = async ({ refresh = false } = {}) => {
    const loadedPlants = [];
    let page = 1;
    let lastPage = 1;
    const seen = new Set();
    const refreshKey = refresh ? `${Date.now()}-${Math.random()}` : "";

    do {
        const params = new URLSearchParams({
            per_page: "100",
            page: String(page),
        });
        if (refreshKey) {
            params.set("_profile_refresh", refreshKey);
        }

        const payload = await apiClient.get(
            `/feed/user/${profileUserId.value}?${params.toString()}`,
        );
        unwrapApiCollection(payload).forEach((plant) => {
            const key = String(plant.id);
            if (seen.has(key)) return;
            seen.add(key);
            loadedPlants.push(mapApiPlant(plant));
        });
        lastPage = Number(payload.pagination?.last_page || page);
        pagination.value = payload.pagination || null;
        page += 1;
    } while (page <= lastPage);

    return loadedPlants;
};

const focusCalendar = (tasks) => {
    const today = todayIsoDate();
    const focusDate =
        tasks
            .map((task) => task.dueAt)
            .filter(Boolean)
            .sort((left, right) => {
                const leftPast = left < today;
                const rightPast = right < today;
                if (leftPast !== rightPast) return leftPast ? 1 : -1;
                return left.localeCompare(right);
            })[0] || today;

    calendarStore.focusDate(focusDate);
};

const load = async ({ refresh = false } = {}) => {
    const requestId = ++loadRequestId;
    loading.value = true;
    error.value = "";
    profile.value = null;
    isFollowing.value = false;
    theyFollowMe.value = false;
    followersCount.value = null;
    followingCount.value = null;

    try {
        const [
            loadedPlants,
            profilePayload,
            relationshipPayload,
            followersPayload,
            followingPayload,
        ] = await Promise.all([
            loadUserFeedPlants({ refresh }),
            authStore.isAuthenticated
                ? apiClient
                      .get(`/users/${profileUserId.value}`)
                      .catch(() => null)
                : Promise.resolve(null),
            authStore.isAuthenticated
                ? apiClient
                      .get(`/users/${profileUserId.value}/relationship`)
                      .catch(() => null)
                : Promise.resolve(null),
            authStore.isAuthenticated
                ? apiClient
                      .get(`/users/${profileUserId.value}/followers/count`)
                      .catch(() => null)
                : Promise.resolve(null),
            authStore.isAuthenticated
                ? apiClient
                      .get(`/users/${profileUserId.value}/following/count`)
                      .catch(() => null)
                : Promise.resolve(null),
        ]);

        if (requestId !== loadRequestId) return;

        plants.value = loadedPlants;
        loadedPlants.forEach((plant) => socialStore.applyPlantSnapshot(plant));
        calendarRevision.value += 1;
        focusCalendar(buildPublicCareTasks(loadedPlants));
        profile.value = profilePayload?.data || profilePayload || null;
        isFollowing.value = Boolean(relationshipPayload?.i_follow_them);
        theyFollowMe.value = Boolean(relationshipPayload?.they_follow_me);
        followersCount.value = followersPayload?.followers_count ?? null;
        followingCount.value = followingPayload?.following_count ?? null;
    } catch (loadError) {
        if (requestId !== loadRequestId) return;

        plants.value = [];
        error.value = loadError.message;
    } finally {
        if (requestId === loadRequestId) {
            loading.value = false;
        }
    }
};

const restoreProfileView = ({ refresh = false } = {}) => {
    if (route.name !== "user-profile") return;

    if (refresh || !plants.value.length || !publicCareTasks.value.length) {
        load({ refresh });
        return;
    }

    calendarRevision.value += 1;
    focusCalendar(publicCareTasks.value);
};

const handlePageShow = () => restoreProfileView({ refresh: true });
const handleVisibilityChange = () => {
    if (document.visibilityState === "visible") {
        restoreProfileView();
    }
};

const openSuggest = (plant) => {
    if (!authStore.isAuthenticated) {
        router.push({
            name: "profile",
            query: { redirect: route.fullPath },
        });
        return;
    }

    suggestPlant.value = plant;
    suggestText.value = "";
};

const closeSuggest = () => {
    suggestPlant.value = null;
    suggestText.value = "";
};

const sendSuggest = async () => {
    const content = suggestText.value.trim();
    if (!content || content.length < 6) {
        toast.error(
            "\u0421\u043e\u0432\u0435\u0442 \u0434\u043e\u043b\u0436\u0435\u043d \u0441\u043e\u0434\u0435\u0440\u0436\u0430\u0442\u044c \u043c\u0438\u043d\u0438\u043c\u0443\u043c 6 \u0441\u0438\u043c\u0432\u043e\u043b\u043e\u0432.",
        );
        return;
    }

    try {
        sendingTip.value = true;
        await socialStore.createTip(suggestPlant.value.apiId, content);
        toast.success(
            "\u0421\u043e\u0432\u0435\u0442 \u043e\u0442\u043f\u0440\u0430\u0432\u043b\u0435\u043d",
        );
        closeSuggest();
    } catch (tipError) {
        toast.error(tipError.message);
    } finally {
        sendingTip.value = false;
    }
};

const toggleLike = async (plant) => {
    if (!authStore.isAuthenticated) {
        router.push({
            name: "profile",
            query: { redirect: route.fullPath },
        });
        return;
    }

    try {
        await socialStore.toggleLike(plant.apiId);
        plant.userLiked = socialStore.isLiked(plant.apiId);
        plant.likesCount = socialStore.likeCountFor(plant.apiId);
    } catch (likeError) {
        toast.error(likeError.message);
    }
};

const openOwner = (ownerId) => {
    if (!ownerId) return;
    router.push(`/users/${ownerId}`);
};

const toggleFollow = async () => {
    if (!authStore.isAuthenticated) {
        router.push({
            name: "profile",
            query: { redirect: `/users/${profileUserId.value}` },
        });
        return;
    }

    if (isOwnProfile.value || followLoading.value) return;

    followLoading.value = true;

    try {
        if (isFollowing.value) {
            await apiClient.delete(`/users/${profileUserId.value}/unfollow`);
            isFollowing.value = false;
            followersCount.value = Math.max(
                0,
                Number(followersCount.value || 0) - 1,
            );
            toast.success("Вы отписались от пользователя");
        } else {
            await apiClient.post(`/users/${profileUserId.value}/follow`);
            isFollowing.value = true;
            followersCount.value = Number(followersCount.value || 0) + 1;
            toast.success("Вы подписались на пользователя");
        }
    } catch (followError) {
        toast.error(followError.message);
    } finally {
        followLoading.value = false;
    }
};

onMounted(() => {
    window.addEventListener("pageshow", handlePageShow);
    document.addEventListener("visibilitychange", handleVisibilityChange);
    load({ refresh: true });
});

onBeforeUnmount(() => {
    loadRequestId += 1;
    window.removeEventListener("pageshow", handlePageShow);
    document.removeEventListener("visibilitychange", handleVisibilityChange);
});

watch(
    () => route.fullPath,
    () => load({ refresh: true }),
);
watch(
    () => authStore.token,
    () => load({ refresh: true }),
);
watch(publicCareTasks, (tasks) => {
    if (tasks.length) {
        focusCalendar(tasks);
    }
});
</script>

<template>
    <section class="page">
        <header class="page-header user-header">
            <div class="user-head">
                <img
                    v-if="user?.avatar_url"
                    :src="user.avatar_url"
                    :alt="user.name"
                    class="user-avatar"
                />
                <div>
                    <h1 class="page-title">
                        {{ user?.name || "Профиль пользователя" }}
                    </h1>
                    <p class="page-subtitle">
                        Ранг: {{ user?.rank ?? "-" }}
                        <span v-if="theyFollowMe && !isOwnProfile">
                            · подписан на вас</span
                        >
                    </p>
                </div>
            </div>

            <UiButton
                v-if="!isOwnProfile"
                :variant="isFollowing ? 'ghost' : 'primary'"
                :disabled="followLoading"
                @click="toggleFollow"
            >
                {{ isFollowing ? "Отписаться" : "Подписаться" }}
            </UiButton>
        </header>

        <div class="user-stats">
            <article class="panel">
                <strong>{{ pagination?.total ?? plants.length }}</strong>
                <span>публичных растений</span>
            </article>
            <article class="panel">
                <strong>{{ totalLikes }}</strong>
                <span>всего лайков</span>
            </article>
            <article class="panel">
                <strong>{{ followersCount ?? "-" }}</strong>
                <span>подписчиков</span>
            </article>
            <article class="panel">
                <strong>{{ followingCount ?? "-" }}</strong>
                <span>подписок</span>
            </article>
        </div>

        <div v-if="error" class="panel user-state">
            <p>{{ error }}</p>
            <UiButton variant="ghost" @click="load">Повторить</UiButton>
        </div>

        <div v-else-if="loading" class="panel user-state">
            Загружаем профиль и ленту пользователя...
        </div>

        <div v-else class="profile-layout">
            <section class="panel">
                <h2 class="panel__title">Календарь ухода</h2>
                <CalendarWidget
                    v-if="publicCareTasks.length"
                    :key="calendarKey"
                    :tasks="publicCareTasks"
                />
                <p v-else class="user-state">
                    У публичных растений пока нет расписания ухода.
                </p>
            </section>

            <section class="profile-plants">
                <PlantListWidget
                    :plants="plants"
                    variant="profile"
                    :show-actions="true"
                    :show-care="true"
                    :show-care-badge="false"
                    :can-like="true"
                    :can-suggest="true"
                    @toggle-like="toggleLike"
                    @suggest="openSuggest"
                    @open-owner="openOwner"
                />
                <div v-if="!plants.length" class="panel user-state">
                    У пользователя пока нет публичных растений.
                </div>
            </section>
        </div>

        <div v-if="suggestPlant" class="tip-modal">
            <section class="tip-modal__card">
                <header>
                    <h2>
                        &#1057;&#1086;&#1074;&#1077;&#1090;
                        &#1076;&#1083;&#1103; {{ suggestPlant.name }}
                    </h2>
                    <button
                        type="button"
                        class="tip-modal__close"
                        @click="closeSuggest"
                    >
                        <X :size="18" />
                    </button>
                </header>

                <textarea
                    v-model="suggestText"
                    rows="4"
                    placeholder="&#1053;&#1072;&#1087;&#1080;&#1096;&#1080;&#1090;&#1077; &#1087;&#1088;&#1072;&#1082;&#1090;&#1080;&#1095;&#1077;&#1089;&#1082;&#1080;&#1081; &#1089;&#1086;&#1074;&#1077;&#1090; &#1087;&#1086; &#1091;&#1093;&#1086;&#1076;&#1091;"
                />

                <div class="tip-modal__actions">
                    <UiButton variant="ghost" @click="closeSuggest">
                        &#1054;&#1090;&#1084;&#1077;&#1085;&#1072;
                    </UiButton>
                    <UiButton :disabled="sendingTip" @click="sendSuggest">
                        {{ sendTipButtonLabel }}
                    </UiButton>
                </div>
            </section>
        </div>
    </section>
</template>

<style scoped>
.user-header {
    align-items: center;
    flex-wrap: wrap;
}

.user-head {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.user-head > div {
    min-width: 0;
}

.user-head .page-title {
    overflow-wrap: anywhere;
}

.user-avatar {
    width: 62px;
    height: 62px;
    border-radius: 50%;
    object-fit: cover;
}

.user-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}

.user-stats article {
    display: grid;
    gap: 4px;
}

.user-stats strong {
    font-size: 28px;
}

.user-stats span,
.user-state {
    color: var(--color-muted);
    font-weight: 800;
}

.profile-layout {
    display: grid;
    gap: 12px;
}

.profile-plants :deep(.plant-list) {
    grid-template-columns: 1fr;
}

.tip-modal {
    position: fixed;
    inset: 0;
    z-index: 100;
    display: grid;
    place-items: center;
    padding: 18px;
    background: rgba(7, 30, 15, 0.56);
}

.tip-modal__card {
    display: grid;
    gap: 12px;
    width: min(540px, 100%);
    padding: 16px;
    border-radius: var(--radius-md);
    background: var(--color-surface);
}

.tip-modal__card header {
    display: flex;
    justify-content: space-between;
    gap: 10px;
}

.tip-modal__card h2 {
    margin: 0;
    font-size: 20px;
}

.tip-modal__card textarea {
    width: 100%;
    resize: vertical;
    padding: 10px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
}

.tip-modal__actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.tip-modal__close {
    display: grid;
    width: 30px;
    height: 30px;
    place-items: center;
    border: 0;
    border-radius: var(--radius-xs);
    color: var(--color-muted);
    background: #edf1ea;
    cursor: pointer;
}

@media (min-width: 1220px) {
    .profile-layout {
        grid-template-columns: minmax(360px, 0.9fr) minmax(0, 1.6fr);
    }
}

@media (min-width: 1120px) {
    .user-stats {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}

@media (max-width: 1119px) {
    .user-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 520px) {
    .user-header,
    .user-head {
        align-items: flex-start;
    }

    .user-head {
        width: 100%;
    }

    .user-stats {
        grid-template-columns: 1fr;
    }
}
</style>
