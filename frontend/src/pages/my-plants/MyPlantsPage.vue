<script setup>
import { onMounted } from "vue";
import { Plus, RefreshCw, Search } from "lucide-vue-next";

import { usePlantStore } from "@/entities/plant/model/plant.store";
import { useTaskStore } from "@/entities/task/model/task.store";
import PlantFilterBar from "@/features/plant-filters/ui/PlantFilterBar.vue";
import UiButton from "@/shared/ui/UiButton.vue";
import CalendarWidget from "@/widgets/calendar/CalendarWidget.vue";
import PlantListWidget from "@/widgets/plants/PlantListWidget.vue";

const plantStore = usePlantStore();
const taskStore = useTaskStore();

const refresh = async () => {
    await plantStore.loadPlants("private");
    taskStore.syncFromPlants(plantStore.all);
};

onMounted(refresh);
</script>

<template>
    <section class="page">
        <header class="page-header">
            <div>
                <h1 class="page-title">Мои растения</h1>
                <p class="page-subtitle">
                    {{ plantStore.attentionCount }} требуют ухода сейчас
                </p>
            </div>
            <div class="my-actions">
                <UiButton variant="ghost" @click="refresh">
                    <RefreshCw :size="17" />
                    Обновить
                </UiButton>
                <RouterLink to="/add-plant">
                    <UiButton>
                        <Plus :size="17" />
                        Добавить растение
                    </UiButton>
                </RouterLink>
            </div>
        </header>

        <div class="my-toolbar panel">
            <label class="my-search">
                <Search :size="17" />
                <input
                    :value="plantStore.search"
                    placeholder="Поиск по названию"
                    @input="plantStore.setSearch($event.target.value)"
                    @change="refresh"
                />
            </label>

            <select
                :value="plantStore.sortBy"
                @change="
                    plantStore.setSort($event.target.value);
                    refresh();
                "
            >
                <option value="created_at">Сначала новые</option>
                <option value="name">По названию</option>
                <option value="planted_at">По посадке</option>
            </select>
        </div>

        <div v-if="plantStore.error" class="panel my-state">
            <p>{{ plantStore.error }}</p>
            <UiButton variant="ghost" @click="refresh"
                >Повторить запрос</UiButton
            >
        </div>

        <div v-else-if="plantStore.loading" class="panel my-state">
            Загружаем ваши растения...
        </div>

        <div v-else class="desktop-grid">
            <div class="page">
                <PlantFilterBar
                    :model-value="plantStore.activeFilter"
                    @update:model-value="plantStore.setFilter"
                />
                <PlantListWidget :plants="plantStore.filteredPlants" />
                <div
                    v-if="!plantStore.filteredPlants.length"
                    class="panel my-state"
                >
                    <p>Растений по текущему фильтру пока нет.</p>
                    <RouterLink to="/add-plant">
                        <UiButton>Добавить растение</UiButton>
                    </RouterLink>
                </div>
            </div>

            <aside class="panel">
                <h2 class="panel__title">Календарь ухода</h2>
                <CalendarWidget :tasks="taskStore.all" />
            </aside>
        </div>
    </section>
</template>

<style scoped>
.my-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-end;
}

.my-toolbar {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 170px;
    gap: 10px;
    align-items: center;
}

.my-search {
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 38px;
    padding: 0 10px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    background: var(--color-surface);
}

.my-search input,
.my-toolbar select {
    width: 100%;
    border: 0;
    outline: 0;
    background: transparent;
}

.my-toolbar select {
    min-height: 38px;
    padding: 0 10px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    background: var(--color-surface);
}

.my-state {
    color: var(--color-muted);
    font-weight: 800;
}

@media (max-width: 760px) {
    .my-toolbar {
        grid-template-columns: 1fr;
    }
}
</style>
