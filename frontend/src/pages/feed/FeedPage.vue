<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { RefreshCw, Search, X } from "lucide-vue-next";
import { useRouter } from "vue-router";
import { toast } from "vue-sonner";

import { useAuthStore } from "@/entities/auth/model/auth.store";
import { usePlantStore } from "@/entities/plant/model/plant.store";
import { useSocialStore } from "@/entities/social/model/social.store";
import UiButton from "@/shared/ui/UiButton.vue";
import PlantListWidget from "@/widgets/plants/PlantListWidget.vue";

const router = useRouter();
const authStore = useAuthStore();
const plantStore = usePlantStore();
const socialStore = useSocialStore();

const suggestPlant = ref(null);
const suggestText = ref("");
const sendingTip = ref(false);

const modes = computed(() => [
    { value: "public", label: "Публичная" },
    ...(authStore.isAuthenticated
        ? [
              { value: "personal", label: "Подписки" },
              { value: "liked", label: "Лайкнутые" },
              { value: "recommendations", label: "Рекомендации" },
          ]
        : []),
]);

const resolveFeedMode = (mode = plantStore.feedMode) =>
    modes.value.some((item) => item.value === mode) ? mode : "public";

const refresh = async (mode = plantStore.feedMode) => {
    await plantStore.loadPlants(resolveFeedMode(mode));
    plantStore.all.forEach((plant) => socialStore.applyPlantSnapshot(plant));
};

const changeMode = (mode) => {
    plantStore.setFeedMode(mode);
    refresh(mode);
};

const openSuggest = (plant) => {
    if (!authStore.isAuthenticated) {
        router.push({
            name: "profile",
            query: { redirect: `/plants/${plant.id}` },
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
        toast.error("Совет должен содержать минимум 6 символов.");
        return;
    }

    try {
        sendingTip.value = true;
        await socialStore.createTip(suggestPlant.value.apiId, content);
        toast.success("Совет отправлен");
        closeSuggest();
    } catch (error) {
        toast.error(error.message);
    } finally {
        sendingTip.value = false;
    }
};

const toggleLike = async (plant) => {
    if (!authStore.isAuthenticated) {
        router.push({
            name: "profile",
            query: { redirect: `/plants/${plant.id}` },
        });
        return;
    }

    try {
        await socialStore.toggleLike(plant.apiId);
        const liked = socialStore.isLiked(plant.apiId);
        plant.userLiked = liked;
        plant.likesCount = socialStore.likeCountFor(plant.apiId);
    } catch (error) {
        toast.error(error.message);
    }
};

const openOwner = (ownerId) => {
    if (!ownerId) return;
    router.push(`/users/${ownerId}`);
};

onMounted(() =>
    refresh(
        authStore.isAuthenticated
            ? resolveFeedMode(plantStore.feedMode)
            : "public",
    ),
);
watch(
    () => authStore.token,
    () =>
        refresh(
            authStore.isAuthenticated
                ? resolveFeedMode(plantStore.feedMode)
                : "public",
        ),
);
</script>

<template>
    <section class="page">
        <header class="page-header">
            <div>
                <h1 class="page-title">Лента</h1>
            </div>
            <UiButton variant="ghost" @click="refresh()">
                <RefreshCw :size="17" />
                Обновить
            </UiButton>
        </header>

        <div class="feed-toolbar panel">
            <div class="feed-tabs">
                <button
                    v-for="mode in modes"
                    :key="mode.value"
                    :class="{ active: plantStore.feedMode === mode.value }"
                    type="button"
                    @click="changeMode(mode.value)"
                >
                    {{ mode.label }}
                </button>
            </div>

            <div class="feed-toolbar__controls">
                <label class="feed-search">
                    <Search :size="17" />
                    <input
                        :value="plantStore.search"
                        placeholder="Поиск по названию"
                        @input="plantStore.setSearch($event.target.value)"
                        @change="refresh()"
                    />
                </label>

                <select
                    class="feed-sort"
                    :value="plantStore.sortBy"
                    @change="
                        plantStore.setSort($event.target.value);
                        refresh();
                    "
                >
                    <option value="created_at">Сначала новые</option>
                    <option value="likes">По лайкам</option>
                    <option value="name">По названию</option>
                    <option value="planted_at">По посадке</option>
                </select>
            </div>
        </div>

        <div v-if="plantStore.error" class="panel feed-state">
            <p>{{ plantStore.error }}</p>
            <UiButton variant="ghost" @click="refresh()"
                >Повторить запрос</UiButton
            >
        </div>

        <div v-else-if="plantStore.loading" class="panel feed-state">
            Загружаем растения из API...
        </div>

        <div v-else>
            <PlantListWidget
                :plants="plantStore.all"
                variant="feed"
                :show-actions="true"
                :show-care="false"
                :can-like="true"
                :can-suggest="true"
                @toggle-like="toggleLike"
                @suggest="openSuggest"
                @open-owner="openOwner"
            />

            <div v-if="!plantStore.all.length" class="panel feed-state">
                Растений в ленте пока нет.
            </div>
        </div>

        <div v-if="suggestPlant" class="tip-modal">
            <section class="tip-modal__card">
                <header>
                    <h2>Совет для {{ suggestPlant.name }}</h2>
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
                    placeholder="Напишите практический совет по уходу"
                />

                <div class="tip-modal__actions">
                    <UiButton variant="ghost" @click="closeSuggest"
                        >Отмена</UiButton
                    >
                    <UiButton :disabled="sendingTip" @click="sendSuggest">
                        {{ sendingTip ? "Отправляем..." : "Отправить совет" }}
                    </UiButton>
                </div>
            </section>
        </div>
    </section>
</template>

<style scoped>
.feed-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.feed-toolbar {
    display: grid;
    gap: 14px;
    padding: 16px;
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.94), rgba(245, 249, 243, 0.98));
}

.feed-toolbar__controls {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 220px;
    gap: 12px;
    align-items: center;
}

.feed-tabs button {
    min-height: 40px;
    padding: 0 14px;
    border: 1px solid var(--color-border);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.86);
    color: var(--color-muted);
    cursor: pointer;
    font-weight: 800;
    min-width: 0;
    transition:
        border-color 0.18s ease,
        background 0.18s ease,
        color 0.18s ease,
        box-shadow 0.18s ease;
}

.feed-tabs button.active {
    color: #fff;
    border-color: var(--color-green);
    background: var(--color-green);
    box-shadow: 0 10px 20px rgba(22, 132, 58, 0.2);
}

.feed-search {
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 44px;
    padding: 0 14px;
    border: 1px solid var(--color-border);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.92);
}

.feed-search svg {
    color: var(--color-muted);
}

.feed-search input,
.feed-sort {
    width: 100%;
    border: 0;
    outline: 0;
    background: transparent;
}

.feed-sort {
    min-height: 44px;
    padding: 0 14px;
    border: 1px solid var(--color-border);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.92);
    color: var(--color-ink);
    font-weight: 800;
}

.feed-state {
    color: var(--color-muted);
    font-weight: 800;
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
    place-items: center;
    width: 30px;
    height: 30px;
    border: 0;
    border-radius: var(--radius-xs);
    color: var(--color-muted);
    background: #edf1ea;
    cursor: pointer;
}

@media (max-width: 760px) {
    .feed-toolbar {
        gap: 10px;
        padding: 14px;
    }

    .feed-tabs {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .feed-tabs button:nth-child(4) {
        grid-column: 1 / -1;
    }

    .feed-toolbar__controls {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .feed-tabs button {
        padding: 0 10px;
        border-radius: var(--radius-sm);
        font-size: 13px;
    }

    .feed-search,
    .feed-sort {
        min-height: 40px;
        border-radius: var(--radius-sm);
    }
}
</style>
