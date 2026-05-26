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
    showCareBadge: { type: Boolean, default: true },
    canLike: { type: Boolean, default: false },
    canSuggest: { type: Boolean, default: false },
});

const emit = defineEmits(["toggle-like", "suggest", "open-owner"]);

const care = computed(() => summarizePlantCare(props.plant));
const isFeedVariant = computed(() => props.variant === "feed");
const isProfileVariant = computed(() => props.variant === "profile");
const isWideVariant = computed(
    () => isFeedVariant.value || isProfileVariant.value,
);
const ownerName = computed(
    () =>
        props.plant.ownerName ||
        "\u041f\u043e\u043b\u044c\u0437\u043e\u0432\u0430\u0442\u0435\u043b\u044c",
);
const ownerMeta = computed(() => {
    if (props.plant.ownerRank === null || props.plant.ownerRank === undefined) {
        return ownerName.value;
    }
    return `${ownerName.value} \u00b7 \u0440\u0430\u043d\u0433 ${props.plant.ownerRank}`;
});
const heightText = computed(
    () =>
        props.plant.height ||
        "\u043d\u0435 \u0443\u043a\u0430\u0437\u0430\u043d\u0430",
);
const likeButtonLabel = computed(() =>
    props.plant.userLiked
        ? "\u0423\u0431\u0440\u0430\u0442\u044c \u043b\u0430\u0439\u043a"
        : "\u041f\u043e\u0441\u0442\u0430\u0432\u0438\u0442\u044c \u043b\u0430\u0439\u043a",
);

const badgeText = computed(() => {
    if (care.value.primaryState === "overdue")
        return "\u043f\u0440\u043e\u0441\u0440\u043e\u0447\u0435\u043d\u043e";
    if (care.value.primaryState === "today")
        return "\u0441\u0435\u0433\u043e\u0434\u043d\u044f";
    if (care.value.primaryState === "soon")
        return "\u0441\u043a\u043e\u0440\u043e";
    return props.plant.isPublic
        ? "\u043f\u0443\u0431\u043b\u0438\u0447\u043d\u043e"
        : "\u0431\u0435\u0437 \u0440\u0430\u0441\u043f\u0438\u0441\u0430\u043d\u0438\u044f";
});
</script>

<template>
    <UiCard>
        <article
            class="plant-card"
            :class="{
                'plant-card--wide': isWideVariant,
                'plant-card--feed': isFeedVariant,
                'plant-card--profile': isProfileVariant,
            }"
        >
            <div class="plant-card__media">
                <RouterLink
                    :to="`/plants/${plant.id}`"
                    class="plant-card__image-wrap"
                >
                    <img
                        :src="plant.image"
                        :alt="plant.name"
                        class="plant-card__image"
                    />
                    <UiBadge
                        v-if="showCare && showCareBadge"
                        class="plant-card__badge"
                        :tone="care.primaryState"
                    >
                        {{ badgeText }}
                    </UiBadge>
                </RouterLink>
            </div>

            <div class="plant-card__body">
                <div class="plant-card__head">
                    <RouterLink
                        :to="`/plants/${plant.id}`"
                        class="plant-card__title-link"
                    >
                        <h3>{{ plant.name }}</h3>
                    </RouterLink>
                    <p><MapPin :size="14" /> {{ plant.room }}</p>
                </div>

                <button
                    v-if="plant.ownerId && !isProfileVariant"
                    type="button"
                    class="owner-button"
                    @click="emit('open-owner', plant.ownerId)"
                >
                    <UserRound :size="14" />
                    {{ ownerMeta }}
                </button>

                <PlantStatusMarkers v-if="showCare" :plant="plant" />

                <div class="plant-card__stats">
                    <span>
                        <span class="plant-card__stat-label"
                            >&#1042;&#1099;&#1089;&#1086;&#1090;&#1072;</span
                        >
                        {{ heightText }}
                    </span>
                </div>

                <div v-if="showActions" class="plant-actions">
                    <button
                        v-if="canLike"
                        type="button"
                        class="action-button"
                        :class="{ 'action-button--liked': plant.userLiked }"
                        @click="emit('toggle-like')"
                    >
                        <Heart
                            :size="16"
                            :fill="plant.userLiked ? 'currentColor' : 'none'"
                        />
                        <span class="action-button__count">{{
                            plant.likesCount
                        }}</span>
                        {{ likeButtonLabel }}
                    </button>
                    <button
                        v-if="canSuggest"
                        type="button"
                        class="action-button action-button--ghost"
                        @click="emit('suggest')"
                    >
                        <MessageCircle :size="16" />
                        &#1044;&#1072;&#1090;&#1100;
                        &#1089;&#1086;&#1074;&#1077;&#1090;
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
    align-items: start;
    min-width: 0;
}

.plant-card--wide {
    grid-template-columns: 1fr;
    align-items: stretch;
}

.plant-card__media {
    display: grid;
    gap: 8px;
    min-width: 0;
}

.plant-card__image-wrap {
    position: relative;
    display: block;
    aspect-ratio: 1 / 1;
    min-height: 0;
    overflow: hidden;
    background: var(--color-green-soft);
}

.plant-card--wide .plant-card__image-wrap {
    aspect-ratio: 1 / 1;
    min-height: 0;
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
    min-width: 0;
    padding: 12px;
}

.plant-card--wide .plant-card__body {
    display: flex;
    flex-direction: column;
    min-height: 100%;
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
    grid-template-columns: minmax(0, 1fr);
    gap: 6px;
    color: var(--color-muted);
    font-size: 12px;
    font-weight: 800;
}

.plant-card--wide .plant-card__stats {
    grid-template-columns: 1fr;
    align-self: stretch;
    justify-items: start;
    text-align: left;
}

.plant-card__stats span {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.plant-card__stat-label {
    margin-right: 4px;
}

.plant-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.plant-card--wide .plant-actions {
    display: grid;
    grid-template-columns: 1fr;
    align-self: stretch;
    margin-top: auto;
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
    white-space: nowrap;
}

.plant-card--wide .action-button {
    width: 100%;
    min-height: 38px;
    justify-content: center;
}

.action-button__count {
    display: inline-grid;
    min-width: 20px;
    height: 20px;
    place-items: center;
    padding: 0 6px;
    border-radius: 999px;
    color: inherit;
    background: rgba(255, 255, 255, 0.18);
    font-size: 12px;
    line-height: 1;
}

.action-button--ghost .action-button__count {
    background: rgba(12, 94, 42, 0.1);
}

.action-button--ghost {
    color: var(--color-green-dark);
    background: var(--color-green-soft);
}

.action-button--liked {
    color: #0c5e2a;
    background: #d4f0df;
}

@media (min-width: 620px) {
    .plant-card--wide {
        grid-template-columns: minmax(220px, 48%) minmax(0, 1fr);
    }
}

@media (min-width: 920px) {
    .plant-card {
        grid-template-columns: 150px minmax(0, 1fr);
    }

    .plant-card--wide {
        grid-template-columns: minmax(240px, 42%) minmax(0, 1fr);
    }
}

@media (max-width: 560px) {
    .plant-card--wide .plant-actions {
        grid-template-columns: repeat(2, 46px);
        justify-content: start;
    }

    .plant-card--wide .action-button {
        display: grid;
        grid-auto-flow: column;
        place-content: center;
        align-items: center;
        width: 46px;
        height: 46px;
        min-height: 46px;
        column-gap: 3px;
        padding: 0;
        font-size: 0;
    }

    .plant-card--wide .action-button svg {
        flex: 0 0 auto;
        margin: 0;
    }

    .plant-card--wide .action-button__count {
        min-width: 0;
        height: auto;
        padding: 0;
        background: transparent;
        font-size: 11px;
        font-weight: 900;
    }
}
</style>
