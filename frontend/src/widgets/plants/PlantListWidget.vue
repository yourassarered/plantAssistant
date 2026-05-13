<script setup>
import PlantCard from "@/entities/plant/ui/PlantCard.vue";

defineProps({
    plants: { type: Array, required: true },
    variant: { type: String, default: "default" },
    showActions: { type: Boolean, default: false },
    showCare: { type: Boolean, default: true },
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
}

@media (min-width: 1180px) {
    .plant-list {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
