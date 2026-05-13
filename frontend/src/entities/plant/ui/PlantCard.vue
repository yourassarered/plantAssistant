<script setup>
import { MapPin, MoreHorizontal } from "lucide-vue-next";
import { computed } from "vue";

import { summarizePlantCare } from "@/shared/lib/date/taskMarkers";
import UiBadge from "@/shared/ui/UiBadge.vue";
import UiCard from "@/shared/ui/UiCard.vue";
import PlantStatusMarkers from "./PlantStatusMarkers.vue";

const props = defineProps({
    plant: { type: Object, required: true },
});

const care = computed(() => summarizePlantCare(props.plant));
const badgeText = computed(() => {
    if (care.value.primaryState === "overdue") return "просрочено";
    if (care.value.primaryState === "today") return "сегодня";
    if (care.value.primaryState === "soon") return "скоро";
    return props.plant.isPublic ? "публично" : "без расписания";
});
</script>

<template>
    <RouterLink v-motion-pop-visible :to="`/plants/${plant.id}`" class="plant-card-link">
        <UiCard>
            <div class="plant-card">
                <div class="plant-card__image-wrap">
                    <img :src="plant.image" :alt="plant.name" class="plant-card__image" />
                    <UiBadge class="plant-card__badge" :tone="care.primaryState">
                        {{ badgeText }}
                    </UiBadge>
                </div>
                <div class="plant-card__body">
                    <div class="plant-card__head">
                        <div>
                            <h3>{{ plant.name }}</h3>
                            <p><MapPin :size="14" /> {{ plant.room }}</p>
                        </div>
                        <button class="plant-card__more" type="button" aria-label="Действия">
                            <MoreHorizontal :size="18" />
                        </button>
                    </div>
                    <PlantStatusMarkers :plant="plant" />
                    <div class="plant-card__stats">
                        <span>Высота {{ plant.height || "не указана" }}</span>
                        <span>Лайки {{ plant.likesCount }}</span>
                    </div>
                </div>
            </div>
        </UiCard>
    </RouterLink>
</template>

<style scoped>
.plant-card-link {
    display: block;
}

.plant-card {
    display: grid;
    grid-template-columns: 118px minmax(0, 1fr);
    min-height: 150px;
}

.plant-card__image-wrap {
    position: relative;
    min-height: 150px;
}

.plant-card__image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.plant-card__badge {
    position: absolute;
    top: 8px;
    left: 8px;
}

.plant-card__body {
    display: grid;
    gap: 10px;
    padding: 12px;
}

.plant-card__head {
    display: flex;
    justify-content: space-between;
    gap: 8px;
}

.plant-card h3 {
    margin: 0;
    font-size: 16px;
    line-height: 1.08;
}

.plant-card p {
    display: flex;
    align-items: center;
    gap: 4px;
    margin: 5px 0 0;
    color: var(--color-muted);
    font-size: 13px;
}

.plant-card__more {
    display: grid;
    width: 30px;
    height: 30px;
    place-items: center;
    border: 0;
    border-radius: var(--radius-xs);
    color: var(--color-muted);
    background: #edf1ea;
}

.plant-card__stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 6px;
    color: var(--color-muted);
    font-size: 12px;
    font-weight: 800;
}

@media (min-width: 920px) {
    .plant-card {
        grid-template-columns: 150px minmax(0, 1fr);
    }
}
</style>
