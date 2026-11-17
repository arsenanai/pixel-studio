<script setup lang="ts">
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const showingNavigationDropdown = ref(false);

const links = computed(() => [
    { label: 'Generate', name: 'generate' },
    { label: 'History', name: 'history' },
    { label: 'Credits', name: 'credits' },
    ...(page.props.auth.user.isAdmin
        ? [{ label: 'Spend report', name: 'admin.report' }]
        : []),
]);
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <nav class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-14 justify-between">
                    <div class="flex">
                        <Link
                            :href="route('generate')"
                            class="flex shrink-0 items-center text-sm font-semibold tracking-tight text-gray-900"
                        >
                            Pixel Studio
                        </Link>

                        <div
                            class="hidden space-x-6 sm:-my-px sm:ms-10 sm:flex"
                        >
                            <NavLink
                                v-for="link in links"
                                :key="link.name"
                                :href="route(link.name)"
                                :active="route().current(link.name)"
                            >
                                {{ link.label }}
                            </NavLink>
                        </div>
                    </div>

                    <div
                        class="hidden sm:ms-6 sm:flex sm:items-center sm:gap-2"
                    >
                        <Link
                            v-if="page.props.credits"
                            :href="route('credits')"
                            class="rounded-md px-3 py-2 text-sm tabular-nums text-gray-600 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                            <span class="sr-only">Available credits:</span>
                            {{ page.props.credits.available.display }}
                        </Link>
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                >
                                    {{ page.props.auth.user.name }}
                                    <svg
                                        class="-me-0.5 ms-2 h-4 w-4"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                        aria-hidden="true"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </button>
                            </template>

                            <template #content>
                                <DropdownLink :href="route('profile.edit')"
                                    >Profile</DropdownLink
                                >
                                <DropdownLink
                                    :href="route('logout')"
                                    method="post"
                                    as="button"
                                >
                                    Log out
                                </DropdownLink>
                            </template>
                        </Dropdown>
                    </div>

                    <div class="-me-2 flex items-center sm:hidden">
                        <button
                            type="button"
                            class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            :aria-expanded="showingNavigationDropdown"
                            aria-controls="mobile-menu"
                            @click="
                                showingNavigationDropdown =
                                    !showingNavigationDropdown
                            "
                        >
                            <span class="sr-only">Menu</span>
                            <svg
                                class="h-6 w-6"
                                stroke="currentColor"
                                fill="none"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    v-if="!showingNavigationDropdown"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 6h16M4 12h16M4 18h16"
                                />
                                <path
                                    v-else
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <div
                v-show="showingNavigationDropdown"
                id="mobile-menu"
                class="sm:hidden"
            >
                <div class="space-y-1 pb-3 pt-2">
                    <ResponsiveNavLink
                        v-for="link in links"
                        :key="link.name"
                        :href="route(link.name)"
                        :active="route().current(link.name)"
                    >
                        {{ link.label }}
                    </ResponsiveNavLink>
                </div>

                <div class="border-t border-gray-200 pb-1 pt-4">
                    <div class="px-4">
                        <div class="text-base font-medium text-gray-800">
                            {{ page.props.auth.user.name }}
                        </div>
                        <div class="text-sm text-gray-500">
                            {{ page.props.auth.user.email }}
                        </div>
                    </div>

                    <div class="mt-3 space-y-1">
                        <ResponsiveNavLink :href="route('profile.edit')"
                            >Profile</ResponsiveNavLink
                        >
                        <ResponsiveNavLink
                            :href="route('logout')"
                            method="post"
                            as="button"
                        >
                            Log out
                        </ResponsiveNavLink>
                    </div>
                </div>
            </div>
        </nav>

        <header v-if="$slots.header" class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
                <slot name="header" />
            </div>
        </header>

        <FlashMessage />

        <main>
            <slot />
        </main>
    </div>
</template>
