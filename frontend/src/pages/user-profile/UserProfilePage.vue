<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { toast } from "vue-sonner";

import { useAuthStore } from "@/entities/auth/model/auth.store";
import { apiClient } from "@/shared/api/client";
import { mapApiPlant, unwrapApiCollection } from "@/shared/api/mappers";
import UiButton from "@/shared/ui/UiButton.vue";
import CalendarWidget from "@/widgets/calendar/CalendarWidget.vue";
import PlantListWidget from "@/widgets/plants/PlantListWidget.vue";

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

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

const profileUserId = computed(() => String(route.params.id || ""));
const isOwnProfile = computed(
    () => authStore.isAuthenticated && String(authStore.user?.id || "") === profileUserId.value,
);

const user = computed(() => {
    if (profile.value) return profile.value;
    const rawUser = plants.value[0]?.raw?.user?.data || plants.value[0]?.raw?.user || null;
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

const publicCareTasks = computed(() =>
    plants.value.flatMap((plant) =>
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
    ),
);

const load = async () => {
    loading.value = true;
    error.value = "";
    profile.value = null;
    isFollowing.value = false;
    theyFollowMe.value = false;
    followersCount.value = null;
    followingCount.value = null;

    try {
        const [
            feedPayload,
            profilePayload,
            relationshipPayload,
            followersPayload,
            followingPayload,
        ] = await Promise.all([
            apiClient.get(`/feed/user/${profileUserId.value}?per_page=50`),
            authStore.isAuthenticated
                ? apiClient.get(`/users/${profileUserId.value}`).catch(() => null)
                : Promise.resolve(null),
            authStore.isAuthenticated
                ? apiClient.get(`/users/${profileUserId.value}/relationship`).catch(() => null)
                : Promise.resolve(null),
            authStore.isAuthenticated
                ? apiClient.get(`/users/${profileUserId.value}/followers/count`).catch(() => null)
                : Promise.resolve(null),
            authStore.isAuthenticated
                ? apiClient.get(`/users/${profileUserId.value}/following/count`).catch(() => null)
                : Promise.resolve(null),
        ]);

        plants.value = unwrapApiCollection(feedPayload).map(mapApiPlant);
        pagination.value = feedPayload.pagination || null;
        profile.value = profilePayload?.data || profilePayload || null;
        isFollowing.value = Boolean(relationshipPayload?.i_follow_them);
        theyFollowMe.value = Boolean(relationshipPayload?.they_follow_me);
        followersCount.value = followersPayload?.followers_count ?? null;
        followingCount.value = followingPayload?.following_count ?? null;
    } catch (loadError) {
        plants.value = [];
        error.value = loadError.message;
    } finally {
        loading.value = false;
    }
};

const toggleFollow = async () => {
    if (!authStore.isAuthenticated) {
        router.push({ name: "profile", query: { redirect: `/users/${profileUserId.value}` } });
        return;
    }

    if (isOwnProfile.value || followLoading.value) return;

    followLoading.value = true;

    try {
        if (isFollowing.value) {
            await apiClient.delete(`/users/${profileUserId.value}/unfollow`);
            isFollowing.value = false;
            followersCount.value = Math.max(0, Number(followersCount.value || 0) - 1);
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

onMounted(load);
watch(() => route.params.id, load);
watch(() => authStore.token, load);
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
                    <h1 class="page-title">{{ user?.name || "Профиль пользователя" }}</h1>
                    <p class="page-subtitle">
                        Ранг: {{ user?.rank ?? "-" }}
                        <span v-if="theyFollowMe && !isOwnProfile"> · подписан на вас</span>
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
                <CalendarWidget v-if="publicCareTasks.length" :tasks="publicCareTasks" />
                <p v-else class="user-state">У публичных растений пока нет расписания ухода.</p>
            </section>

            <section>
                <PlantListWidget :plants="plants" />
                <div v-if="!plants.length" class="panel user-state">
                    У пользователя пока нет публичных растений.
                </div>
            </section>
        </div>
    </section>
</template>

<style scoped>
.user-header {
    align-items: center;
}

.user-head {
    display: flex;
    align-items: center;
    gap: 12px;
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

@media (min-width: 960px) {
    .profile-layout {
        grid-template-columns: minmax(320px, 0.95fr) minmax(0, 1.55fr);
    }
}

@media (max-width: 760px) {
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
