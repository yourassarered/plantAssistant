import FloatingVue from "floating-vue";
import { createPinia } from "pinia";
import persistedState from "pinia-plugin-persistedstate";
import { createApp } from "vue";
import { MotionPlugin } from "@vueuse/motion";

import App from "./App.vue";
import { router } from "./app/router";
import "./app/styles/tokens.css";
import "./app/styles/base.css";
import "floating-vue/dist/style.css";

const pinia = createPinia();
pinia.use(persistedState);

createApp(App)
    .use(pinia)
    .use(router)
    .use(FloatingVue)
    .use(MotionPlugin)
    .mount("#app");
