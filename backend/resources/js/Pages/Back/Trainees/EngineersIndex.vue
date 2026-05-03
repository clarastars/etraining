<template>
    <app-layout>
        <div class="container px-6 mx-auto grid pt-6">
            <breadcrumb-container
                :crumbs="[
                    { title: 'dashboard', link: route('dashboard') },
                    { title: 'trainees', link: route('back.trainees.index') },
                    { title: 'engineers' },
                ]"
            />
            <div class="flex justify-between">
                <h1 class="mb-8 font-bold text-3xl">{{ $t('words.engineers') }}</h1>
            </div>

            <div v-if="isSaraView" class="bg-white rounded shadow overflow-x-auto">
                <table class="w-full whitespace-no-wrap">
                    <thead>
                        <tr class="text-left font-bold">
                            <th class="px-6 pt-6 pb-4">{{ $t('words.name') }}</th>
                            <th class="px-6 pt-6 pb-4">{{ $t('words.identity_number') }}</th>
                            <th class="px-6 pt-6 pb-4">{{ $t('words.company') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="trainee in trainees.data"
                            :key="trainee.id"
                            class="hover:bg-gray-100 focus-within:bg-gray-100 cursor-pointer"
                            @click="goToTrainee(trainee.id)"
                        >
                            <td class="border-t px-6 py-4">{{ trainee.name }}</td>
                            <td class="border-t px-6 py-4">{{ trainee.identity_number || '-' }}</td>
                            <td class="border-t px-6 py-4">
                                <span v-if="trainee.company">{{ trainee.company.name_ar }}</span>
                                <span v-else class="italic text-gray-500 text-xs">{{ $t('words.not-assigned-to-a-company') }}</span>
                            </td>
                        </tr>
                        <tr v-if="trainees.data.length === 0">
                            <td class="border-t px-6 py-4" colspan="3">
                                <empty-slate />
                            </td>
                        </tr>
                    </tbody>
                </table>
                <pagination :links="trainees.links" />
            </div>

            <div v-else class="bg-white rounded shadow overflow-x-auto">
                <table class="w-full whitespace-no-wrap">
                    <thead>
                        <tr class="text-left font-bold">
                            <th class="px-6 pt-6 pb-4">{{ $t('words.name') }}</th>
                            <th class="px-6 pt-6 pb-4">{{ $t('words.identity_number') }}</th>
                            <th class="px-6 pt-6 pb-4">{{ $t('words.phone') }}</th>
                            <th class="px-6 pt-6 pb-4">{{ $t('words.company') }}</th>
                            <th class="px-6 pt-6 pb-4 w-px" aria-hidden="true"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in trainees.data"
                            :key="row.id"
                            class="hover:bg-gray-100 focus-within:bg-gray-100"
                        >
                            <td class="border-t">
                                <div class="px-6 py-4 flex items-center">
                                    <inertia-link :href="route('back.trainees.show', row.id)" class="focus:text-indigo-500">
                                        {{ row.name }}
                                        <br />
                                        <span
                                            v-if="row.is_pending_uploading_files"
                                            class="text-sm inline-block mt-2 p-1 px-2 bg-blue-300 rounded-lg"
                                        >
                                            {{ $t('words.incomplete-application') }}
                                        </span>
                                        <span
                                            v-if="row.is_pending_approval"
                                            class="text-sm inline-block mt-2 p-1 px-2 bg-yellow-200 rounded-lg"
                                        >
                                            {{ $t('words.nominated-instructor') }}
                                        </span>
                                        <span
                                            v-if="row.is_approved"
                                            class="text-sm inline-block mt-2 p-1 px-2 bg-green-300 rounded-lg"
                                        >
                                            {{ $t('words.approved') }}
                                        </span>
                                    </inertia-link>
                                </div>
                            </td>
                            <td class="border-t">
                                <inertia-link class="px-6 py-4 flex items-center" :href="route('back.trainees.show', row.id)" tabindex="-1">
                                    {{ row.identity_number || '-' }}
                                </inertia-link>
                            </td>
                            <td class="border-t">
                                <inertia-link class="px-6 py-4 flex items-center" :href="route('back.trainees.show', row.id)" tabindex="-1">
                                    {{ row.phone || '-' }}
                                </inertia-link>
                            </td>
                            <td class="border-t">
                                <inertia-link class="px-6 py-4 flex items-center" :href="route('back.trainees.show', row.id)" tabindex="-1">
                                    <span v-if="row.company">{{ row.company.name_ar }}</span>
                                    <span v-else class="italic text-gray-500 text-xs">{{ $t('words.not-assigned-to-a-company') }}</span>
                                </inertia-link>
                                <p v-if="row.trainee_group" class="px-6 pb-4 text-xs text-gray-500">
                                    {{ row.trainee_group.name }}
                                </p>
                            </td>
                            <td class="border-t w-px">
                                <inertia-link class="px-4 flex items-center" :href="route('back.trainees.show', row.id)" tabindex="-1">
                                    <ion-icon name="arrow-forward-outline" class="block w-6 h-6 fill-gray-400" />
                                </inertia-link>
                            </td>
                        </tr>
                        <tr v-if="trainees.data.length === 0">
                            <td class="border-t px-6 py-4" colspan="5">
                                <empty-slate />
                            </td>
                        </tr>
                    </tbody>
                </table>
                <pagination :links="trainees.links" />
            </div>
        </div>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout';
import BreadcrumbContainer from '@/Components/BreadcrumbContainer';
import EmptySlate from '@/Components/EmptySlate';
import Pagination from '@/Shared/Pagination';

export default {
    metaInfo: { title: 'Engineers' },
    components: {
        AppLayout,
        BreadcrumbContainer,
        EmptySlate,
        Pagination,
    },
    props: {
        trainees: { type: Object, required: true },
        isSaraView: {
            type: Boolean,
            default: false,
        },
    },
    methods: {
        goToTrainee(traineeId) {
            this.$inertia.visit(this.route('back.trainees.show', traineeId));
        },
    },
};
</script>
