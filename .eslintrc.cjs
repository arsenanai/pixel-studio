/* eslint-env node */
require("@rushstack/eslint-patch/modern-module-resolution")

module.exports = {
    root: true,
    extends: [
        "plugin:vue/vue3-essential",
        "eslint:recommended",
        "@vue/eslint-config-typescript",
        "@vue/eslint-config-prettier"
    ],
    parserOptions: {
        ecmaVersion: "latest"
    },
    rules: {
        "vue/multi-word-component-names": "off",
        "no-undef": "off",

        // Money and provider payloads are the risk surface; nothing here gets `any`.
        "@typescript-eslint/no-explicit-any": "error",
        "@typescript-eslint/no-non-null-assertion": "off"
    }
}
