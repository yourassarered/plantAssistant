<script setup>
import { Droplets, Leaf, RotateCw, Scissors } from "lucide-vue-next";

import { formatIsoDate } from "@/shared/lib/date/calendarGrid";
import { summarizePlantCare } from "@/shared/lib/date/taskMarkers";

const props = defineProps({
    plant: { type: Object, required: true },
});

const icons = {
    water: Droplets,
    feed: Leaf,
    prune: Scissors,
    rotate: RotateCw,
};

const status = summarizePlantCare(props.plant);
</script>

<template>
    <div class="plant-markers" :data-state="status.primaryState">
        <span
            v-for="marker in status.markers"
            :key="marker.type"
            v-tooltip="`${marker.label}: ${formatIsoDate(marker.dueAt)}`"
            class="plant-markers__dot"
            :class="`plant-markers__dot--${marker.state}`"
            :style="{ '--marker-color': marker.color }"
        >
            <component :is="icons[marker.type]" :size="13" />
        </span>
    </div>
</template>

<style scoped>
.plant-markers {
    display: inline-flex;
    gap: 5px;
    align-items: center;
}

.plant-markers__dot {
    display: grid;
    width: 24px;
    height: 24px;
    place-items: center;
    border: 2px solid #fff;
    border-radius: 50%;
    color: #fff;
    background: var(--marker-color);
    box-shadow: 0 5px 12px rgba(23, 33, 24, 0.16);
}

.plant-markers__dot--soon {
    opacity: 0.55;
}

.plant-markers__dot--today {
    outline: 2px solid rgba(242, 112, 54, 0.42);
}

.plant-markers__dot--overdue {
    background: var(--color-red);
    outline: 2px solid rgba(224, 69, 50, 0.46);
}
</style>
