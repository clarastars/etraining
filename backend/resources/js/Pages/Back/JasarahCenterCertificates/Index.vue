<template>
    <app-layout>
        <div class="container px-6 mx-auto grid pt-6">
            <breadcrumb-container
                :crumbs="[
                    {title: 'dashboard', link: route('dashboard')},
                    {title: 'jasarah-center-certificates', link: route('back.jasarah-center-certificates.index')},
                ]"
            ></breadcrumb-container>

            <div class="bg-white rounded shadow p-6 mb-8">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold">{{ $t('words.jasarah-center-certificate-imports') }}</h2>
                    <inertia-link
                        :href="route('back.jasarah-center-certificates.create')"
                        class="btn-gray"
                    >
                        {{ $t('words.new') }}
                    </inertia-link>
                </div>

                <div v-if="imports.data && imports.data.length > 0" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $t('words.import-id') }}</th>
                                <th class="px-6 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $t('words.course') }}</th>
                                <th class="px-6 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $t('words.instructor') }}</th>
                                <th class="px-6 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $t('words.status') }}</th>
                                <th class="px-6 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $t('words.total-rows') }}</th>
                                <th class="px-6 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $t('words.matched') }}</th>
                                <th class="px-6 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $t('words.unmatched') }}</th>
                                <th class="px-6 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $t('words.failed') }}</th>
                                <th class="px-6 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $t('words.created-at') }}</th>
                                <th class="px-6 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $t('words.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="item in imports.data" :key="item.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#{{ item.id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.course?.name_ar || 'Unknown Course' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ item.course?.instructor?.name || 'No Instructor' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="getStatusClass(item.status)" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full">
                                        {{ getStatusText(item.status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.total_rows || 0 }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-medium">{{ item.matched_count || 0 }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-orange-600 font-medium">{{ item.unmatched_count || 0 }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 font-medium">{{ item.failed_count || 0 }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatDateTime(item.created_at) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <div class="flex flex-wrap items-center">
                                        <inertia-link
                                            :href="route('back.jasarah-center-certificates.processing', item.id)"
                                            class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 whitespace-nowrap ltr:mr-2 rtl:ml-2 mb-1"
                                        >
                                            {{ $t('words.view') }}
                                        </inertia-link>
                                        <a
                                            v-if="item.status === 'sent' || item.status === 'completed'"
                                            :href="route('back.jasarah-center-certificates.delivery-report', item.id)"
                                            class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-md border border-green-300 bg-green-50 text-green-700 hover:bg-green-100 whitespace-nowrap mb-1"
                                        >
                                            {{ $t('words.delivery-report') }}
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="imports.links && imports.links.length > 3" class="mt-6">
                        <nav class="flex justify-center">
                            <div class="flex flex-wrap justify-center">
                                <inertia-link
                                    v-for="(link, index) in imports.links"
                                    :key="index"
                                    :href="link.url"
                                    :class="[
                                        'px-3 py-2 text-sm font-medium rounded-md ltr:mr-1 rtl:ml-1 mb-1',
                                        link.url === null
                                            ? 'text-gray-400 cursor-not-allowed'
                                            : link.active
                                                ? 'bg-indigo-100 text-indigo-700'
                                                : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'
                                    ]"
                                    v-html="link.label"
                                ></inertia-link>
                            </div>
                        </nav>
                    </div>
                </div>

                <div v-else class="text-center py-8 text-gray-500">
                    {{ $t('words.no-jasarah-center-certificate-imports') }}
                </div>
            </div>
        </div>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout'
import BreadcrumbContainer from "@/Components/BreadcrumbContainer"

export default {
    metaInfo: { title: 'Jasarah Center Certificates' },
    components: {
        BreadcrumbContainer,
        AppLayout,
    },
    props: {
        imports: Object,
    },
    methods: {
        getStatusClass(status) {
            const classes = {
                processing: 'bg-blue-100 text-blue-800',
                sending: 'bg-blue-100 text-blue-800',
                completed: 'bg-green-100 text-green-800',
                ready_to_send: 'bg-indigo-100 text-indigo-800',
                sent: 'bg-green-100 text-green-800',
                failed: 'bg-red-100 text-red-800',
                cancelled: 'bg-gray-100 text-gray-800',
            };
            return classes[status] || 'bg-gray-100 text-gray-800';
        },

        getStatusText(status) {
            const labels = {
                processing: this.$t('words.processing'),
                sending: this.$t('words.sending'),
                completed: this.$t('words.completed'),
                ready_to_send: this.$t('words.pdfs-ready-to-send'),
                sent: this.$t('words.sent'),
                failed: this.$t('words.failed'),
                cancelled: this.$t('words.cancelled'),
            };
            return labels[status] || status;
        },

        formatDateTime(timestamp) {
            if (!timestamp) return '';
            const date = new Date(timestamp);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        },
    },
}
</script>
