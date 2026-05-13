<script setup>
import { computed, onMounted, watch } from "vue";
import { Plus, RefreshCw, Search } from "lucide-vue-next";

import { useAuthStore } from "@/entities/auth/model/auth.store";
import { usePlantStore } from "@/entities/plant/model/plant.store";
import { useTaskStore } from "@/entities/task/model/task.store";
import PlantFilterBar from "@/features/plant-filters/ui/PlantFilterBar.vue";
import UiButton from "@/shared/ui/UiButton.vue";
import CalendarWidget from "@/widgets/calendar/CalendarWidget.vue";
import PlantListWidget from "@/widgets/plants/PlantListWidget.vue";
import TodayTasksWidget from "@/widgets/tasks/TodayTasksWidget.vue";

const authStore = useAuthStore();
const plantStore = usePlantStore();
const taskStore = useTaskStore();

const priorityTasks = computed(() => [...taskStore.overdueTasks, ...taskStore.todayTasks]);
const isPrivateMode = computed(() => plantStore.source === "private");
const visiblePlants = computed(() => (isPrivateMode.value ? plantStore.filteredPlants : plantStore.all));

const modes = computed(() => [
    { value: "public", label: "Публичная" },
    ...(authStore.isAuthenticated
        ? [
              { value: "private", label: "Мои" },
              { value: "personal", label: "Подписки" },
              { value: "liked", label: "Лайкнутые" },
              { value: "recommendations", label: "Рекомендации" },
          ]
        : []),
]);

const refresh = async (mode = plantStore.feedMode) => {
    await plantStore.loadPlants(mode);
    taskStore.syncFromPlants(isPrivateMode.value ? plantStore.all : []);
};

const changeMode = (mode) => {
    plantStore.setFeedMode(mode);
    refresh(mode);
};

onMounted(() => refresh());
watch(() => authStore.token, () => refresh(authStore.isAuthenticated ? "private" : "public"));
</script>

<template>
    <section class="page">
        <header class="page-header">
            <div>
                <h1 class="page-title">{{ isPrivateMode ? "Мои растения" : "Лента растений" }}</h1>
                <p class="page-subtitle">
                    <template v-if="isPrivateMode">
                        {{ plantStore.attentionCount }} требуют ухода сейчас
                    </template>
                    <template v-else>
                        Публичный просмотр доступен без входа. Действия доступны после авторизации.
                    </template>
                </p>
            </div>
            <div class="feed-actions">
                <UiButton variant="ghost" @click="refresh()">
                    <RefreshCw :size="17" />
                    Обновить
                </UiButton>
                <RouterLink v-if="authStore.isAuthenticated" to="/add-plant">
                    <UiButton>
                        <Plus :size="17" />
                        Добавить
                    </UiButton>
                </RouterLink>
            </div>
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
                :value="plantStore.sortBy"
                @change="plantStore.setSort($event.target.value); refresh()"
            >
                <option value="created_at">Сначала новые</option>
                <option value="likes">По лайкам</option>
                <option value="name">По названию</option>
                <option value="planted_at">По посадке</option>
            </select>
        </div>

        <div v-if="plantStore.error" class="panel feed-state">
            <p>{{ plantStore.error }}</p>
            <UiButton variant="ghost" @click="refresh()">Повторить запрос</UiButton>
        </div>

        <div v-else-if="plantStore.loading" class="panel feed-state">
            Загружаем растения из API...
        </div>

        <div v-else class="desktop-grid">
            <div class="page">
                <TodayTasksWidget v-if="isPrivateMode" :tasks="priorityTasks" />
                <PlantFilterBar
                    v-if="isPrivateMode"
                    :model-value="plantStore.activeFilter"
                    @update:model-value="plantStore.setFilter"
                />
                <PlantListWidget :plants="visiblePlants" />
                <div v-if="!visiblePlants.length" class="panel feed-state">
                    <p>
                        {{
                            isPrivateMode
                                ? "Растений в этом режиме пока нет."
                                : "В публичной ленте пока нет растений."
                        }}
                    </p>
                    <RouterLink v-if="authStore.isAuthenticated" to="/add-plant">
                        <UiButton>Добавить первое растение</UiButton>
                    </RouterLink>
                </div>
            </div>

            <aside class="panel">
                <h2 class="panel__title">
                    {{ isPrivateMode ? "Календарь ухода" : "Публичный просмотр" }}
                </h2>
                <CalendarWidget v-if="isPrivateMode" :tasks="taskStore.all" />
                <p v-else class="feed-note">
                    Календарь формируется только для ваших растений после входа.
                </p>
            </aside>
        </div>
    </section>
</template>

<style scoped>
.feed-actions,
.feed-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.feed-actions {
    justify-content: flex-end;
}

.feed-toolbar {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(220px, 0.7fr) 170px;
    gap: 10px;
    align-items: center;
}

.feed-tabs button {
    min-height: 34px;
    padding: 0 10px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    background: var(--color-surface);
    color: var(--color-muted);
    cursor: pointer;
    font-weight: 800;
}

.feed-tabs button.active {
    color: #fff;
    border-color: var(--color-green);
    background: var(--color-green);
}

.feed-search {
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 38px;
    padding: 0 10px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    background: var(--color-surface);
}

.feed-search input,
.feed-toolbar select {
    width: 100%;
    border: 0;
    outline: 0;
    background: transparent;
}

.feed-toolbar select {
    min-height: 38px;
    padding: 0 10px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    background: var(--color-surface);
}

.feed-state,
.feed-note {
    color: var(--color-muted);
    font-weight: 800;
}

.feed-note {
    margin: 0;
}

@media (max-width: 760px) {
    .feed-toolbar {
        grid-template-columns: 1fr;
    }
}
</style>
