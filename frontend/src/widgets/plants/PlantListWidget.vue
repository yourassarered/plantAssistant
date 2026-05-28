<script setup>
import PlantCard from "@/entities/plant/ui/PlantCard.vue";

defineProps({
    plants: { type: Array, required: true },
    variant: { type: String, default: "default" },
    showActions: { type: Boolean, default: false },
    showCare: { type: Boolean, default: true },
    showCareBadge: { type: Boolean, default: true },
    showOwner: { type: Boolean, default: true },
    canLike: { type: Boolean, default: false },
    canSuggest: { type: Boolean, default: false },
});

const emit = defineEmits(["toggle-like", "suggest", "open-owner"]);
</script>

<template>
    <div class="plant-list">
        <PlantCard
            v-for="plant in plants"
            :key="plant.id"
            :plant="plant"
            :variant="variant"
            :show-actions="showActions"
            :show-care="showCare"
            :show-care-badge="showCareBadge"
            :show-owner="showOwner"
            :can-like="canLike"
            :can-suggest="canSuggest"
            @toggle-like="emit('toggle-like', plant)"
            @suggest="emit('suggest', plant)"
            @open-owner="emit('open-owner', $event)"
        />
    </div>
</template>

<style scoped>
.plant-list {
    display: grid;
    gap: 12px;
    align-items: start;
}

.plant-list > * {
    align-self: start;
    animation: plant-card-rise 0.34s ease both;
}

.plant-list > :nth-child(2) {
    animation-delay: 0.04s;
}

.plant-list > :nth-child(3) {
    animation-delay: 0.08s;
}

.plant-list > :nth-child(4) {
    animation-delay: 0.12s;
}

@keyframes plant-card-rise {
    from {
        opacity: 0;
        transform: translateY(10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (min-width: 1180px) {
    .plant-list {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
