<script setup>
import { Heart, MapPin, MessageCircle, UserRound } from "lucide-vue-next";
import { computed } from "vue";

import { summarizePlantCare } from "@/shared/lib/date/taskMarkers";
import UiBadge from "@/shared/ui/UiBadge.vue";
import UiCard from "@/shared/ui/UiCard.vue";
import PlantStatusMarkers from "./PlantStatusMarkers.vue";

const props = defineProps({
    plant: { type: Object, required: true },
    variant: { type: String, default: "default" },
    showActions: { type: Boolean, default: false },
    showCare: { type: Boolean, default: true },
    canLike: { type: Boolean, default: false },
    canSuggest: { type: Boolean, default: false },
});

const emit = defineEmits(["toggle-like", "suggest", "open-owner"]);

const care = computed(() => summarizePlantCare(props.plant));
const isFeedVariant = computed(() => props.variant === "feed");
const ownerName = computed(() => props.plant.ownerName || "Пользователь");
const ownerMeta = computed(() => {
    if (props.plant.ownerRank === null || props.plant.ownerRank === undefined) {
        return ownerName.value;
    }
    return `${ownerName.value} · rank ${props.plant.ownerRank}`;
});

const badgeText = computed(() => {
    if (care.value.primaryState === "overdue") return "просрочено";
    if (care.value.primaryState === "today") return "сегодня";
    if (care.value.primaryState === "soon") return "скоро";
    return props.plant.isPublic ? "публично" : "без расписания";
});
</script>

<template>
    <UiCard>
        <article class="plant-card" :class="{ 'plant-card--feed': isFeedVariant }">
            <RouterLink :to="`/plants/${plant.id}`" class="plant-card__image-wrap">
                <img :src="plant.image" :alt="plant.name" class="plant-card__image" />
                <UiBadge v-if="showCare" class="plant-card__badge" :tone="care.primaryState">
                    {{ badgeText }}
                </UiBadge>
            </RouterLink>

            <div class="plant-card__body">
                <div class="plant-card__head">
                    <RouterLink :to="`/plants/${plant.id}`" class="plant-card__title-link">
                        <h3>{{ plant.name }}</h3>
                    </RouterLink>
                    <p><MapPin :size="14" /> {{ plant.room }}</p>
                </div>

                <button
                    v-if="plant.ownerId"
                    type="button"
                    class="owner-button"
                    @click="emit('open-owner', plant.ownerId)"
                >
                    <UserRound :size="14" />
                    {{ ownerMeta }}
                </button>

                <PlantStatusMarkers v-if="showCare" :plant="plant" />

                <div class="plant-card__stats">
                    <span>Высота {{ plant.height || "не указана" }}</span>
                    <span>Лайки {{ plant.likesCount }}</span>
                </div>

                <div v-if="showActions" class="plant-actions">
                    <button
                        v-if="canLike"
                        type="button"
                        class="action-button"
                        :class="{ 'action-button--liked': plant.userLiked }"
                        @click="emit('toggle-like')"
                    >
                        <Heart :size="16" :fill="plant.userLiked ? 'currentColor' : 'none'" />
                        {{ plant.userLiked ? "Убрать лайк" : "Поставить лайк" }}
                    </button>
                    <button
                        v-if="canSuggest"
                        type="button"
                        class="action-button action-button--ghost"
                        @click="emit('suggest')"
                    >
                        <MessageCircle :size="16" />
                        Дать совет
                    </button>
                </div>
            </div>
        </article>
    </UiCard>
</template>

<style scoped>
.plant-card {
    display: grid;
    grid-template-columns: 118px minmax(0, 1fr);
    min-height: 150px;
}

.plant-card--feed {
    grid-template-columns: 1fr;
}

.plant-card__image-wrap {
    position: relative;
    min-height: 150px;
}

.plant-card--feed .plant-card__image-wrap {
    min-height: 230px;
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

.plant-card__head h3 {
    margin: 0;
    font-size: 16px;
    line-height: 1.08;
}

.plant-card__title-link {
    color: inherit;
}

.plant-card__head p {
    display: flex;
    align-items: center;
    gap: 4px;
    margin: 5px 0 0;
    color: var(--color-muted);
    font-size: 13px;
}

.owner-button {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    width: fit-content;
    border: 0;
    color: var(--color-green-dark);
    background: transparent;
    cursor: pointer;
    font-weight: 800;
}

.plant-card__stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 6px;
    color: var(--color-muted);
    font-size: 12px;
    font-weight: 800;
}

.plant-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.action-button {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 34px;
    padding: 0 12px;
    border: 0;
    border-radius: var(--radius-sm);
    color: #fff;
    background: var(--color-green);
    cursor: pointer;
    font-weight: 800;
}

.action-button--ghost {
    color: var(--color-green-dark);
    background: var(--color-green-soft);
}

.action-button--liked {
    color: #0c5e2a;
    background: #d4f0df;
}

@media (min-width: 920px) {
    .plant-card {
        grid-template-columns: 150px minmax(0, 1fr);
    }

    .plant-card--feed {
        grid-template-columns: minmax(280px, 1fr) minmax(0, 1fr);
    }

    .plant-card--feed .plant-card__image-wrap {
        min-height: 260px;
    }
}
</style>
