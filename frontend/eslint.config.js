import prettier from "@vue/eslint-config-prettier";
import pluginVue from "eslint-plugin-vue";

export default [
    {
        ignores: ["dist", "coverage", "node_modules"],
    },
    ...pluginVue.configs["flat/recommended"],
    prettier,
];
