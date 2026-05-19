<template>
    <app-layout>
        <div class="container px-6 mx-auto grid pt-6">
            <breadcrumb-container
                :crumbs="[
                    {title: 'dashboard', link: route('dashboard')},
                    {title: 'jasarah-center-certificates', link: route('back.jasarah-center-certificates.index')},
                    {title: 'processing', link: '#'},
                ]"
            ></breadcrumb-container>

            <div class="flex justify-between items-start mb-8">
                <div>
                    <h1 class="font-bold text-3xl">{{ $t('words.processing-jasarah-center-certificates') }}</h1>
                    <p class="text-gray-600 mt-2">{{ $t('words.course') }}: {{ importData.course?.name_ar || 'Unknown Course' }}</p>
                </div>
                <div class="flex flex-col items-end gap-3">
                    <div class="relative">
                        <button
                            type="button"
                            class="btn-gray px-4 py-2 text-sm inline-flex items-center gap-2"
                            @click="showActionsMenu = !showActionsMenu"
                        >
                            {{ $t('words.actions') }}
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>

                        <div
                            v-if="showActionsMenu"
                            class="absolute right-0 mt-2 w-40 bg-white rounded-md shadow-lg border border-gray-100 z-10"
                        >
                            <button
                                type="button"
                                @click="handleDeleteAction"
                                class="block w-full text-right px-4 py-2 text-sm text-red-600 hover:bg-red-50 disabled:opacity-50"
                                :disabled="isDeleting"
                            >
                                <span v-if="isDeleting">{{ $t('words.deleting') }}...</span>
                                <span v-else>{{ $t('words.delete') }}</span>
                            </button>
                        </div>
                    </div>
                    <div class="text-right bg-white rounded shadow-sm border border-gray-100 px-4 py-3">
                        <div class="text-sm text-gray-500">{{ $t('words.import-id') }}: {{ importData.id }}</div>
                        <div class="text-sm text-gray-500">
                            {{ $t('words.started-at') }}:
                            <span dir="ltr" class="inline-block">{{ formatDateTime(importData.started_at) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded shadow p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4">{{ getStatusTitle() }}</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-gray-50 p-4 rounded">
                        <div class="text-2xl font-bold text-gray-900">{{ status.total_rows || 0 }}</div>
                        <div class="text-sm text-gray-600">{{ $t('words.total-rows') }}</div>
                    </div>
                    <div class="bg-green-50 p-4 rounded">
                        <div class="text-2xl font-bold text-green-600">{{ status.matched_count || 0 }}</div>
                        <div class="text-sm text-gray-600">{{ $t('words.matched') }}</div>
                    </div>
                    <div class="bg-orange-50 p-4 rounded">
                        <div class="text-2xl font-bold text-orange-600">{{ status.unmatched_count || 0 }}</div>
                        <div class="text-sm text-gray-600">{{ $t('words.unmatched') }}</div>
                    </div>
                    <div class="bg-red-50 p-4 rounded">
                        <div class="text-2xl font-bold text-red-600">{{ status.failed_count || 0 }}</div>
                        <div class="text-sm text-gray-600">{{ $t('words.failed') }}</div>
                    </div>
                </div>
            </div>

            <div v-if="status.matched && status.matched.length > 0" class="bg-white rounded shadow p-6 mb-6">
                <h3 class="text-lg font-semibold text-green-700 mb-4">{{ $t('words.matched-trainees') }} ({{ status.matched.length }})</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('words.name-english') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('words.trainee-name') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('words.identity-number') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('words.email') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('words.status') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('words.pdf') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="(row, index) in status.matched" :key="`matched-${index}-${row.row_key}`">
                                <td class="px-6 py-4 text-sm">{{ row.trainee_name_en }}</td>
                                <td class="px-6 py-4 text-sm font-medium">{{ row.name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ row.identity_number }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ row.email }}</td>
                                <td class="px-6 py-4 text-sm">{{ getRowStatusText(row.status) }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <a
                                        v-if="row.has_pdf"
                                        :href="route('back.jasarah-center-certificates.download', row.row_id)"
                                        target="_blank"
                                        class="text-indigo-600 hover:text-indigo-900"
                                    >
                                        {{ $t('words.view-pdf') }}
                                    </a>
                                    <span v-else class="text-gray-400">-</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="status.unmatched && status.unmatched.length > 0" class="bg-white rounded shadow p-6 mb-6">
                <h3 class="text-lg font-semibold text-orange-700 mb-4">{{ $t('words.unmatched-trainees') }} ({{ status.unmatched.length }})</h3>
                <div class="space-y-4">
                    <div v-for="(trainee, index) in status.unmatched" :key="trainee.row_key" class="border border-orange-200 rounded-lg p-4">
                        <div class="mb-3">
                            <div class="font-medium">{{ trainee.trainee_name_en }}</div>
                            <div class="text-sm text-gray-500">{{ trainee.identity_number }}</div>
                        </div>
                        <input v-model="trainee.searchQuery" @input="searchTrainees(index)" :placeholder="$t('words.search-trainee')" class="w-full form-input text-sm mb-3" />
                        <div v-if="trainee.searchResults && trainee.searchResults.length" class="mb-3 max-h-32 overflow-y-auto border rounded">
                            <div v-for="result in trainee.searchResults" :key="result.id" @click="selectTraineeForUnmatched(index, result)" class="p-2 hover:bg-gray-100 cursor-pointer border-b last:border-b-0">
                                {{ result.name }} ({{ result.identity_number }}) - {{ result.email }}
                            </div>
                        </div>
                        <div v-if="trainee.selectedTrainee" class="p-2 bg-green-100 rounded text-sm">
                            {{ $t('words.selected') }}: {{ trainee.selectedTrainee.name }}
                            <button @click="removeLinkedTrainee(index)" class="ml-2 text-red-600">{{ $t('words.remove') }}</button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="status.failed && status.failed.length > 0" class="bg-white rounded shadow p-6 mb-6">
                <h3 class="text-lg font-semibold text-red-700 mb-4">{{ $t('words.failed-rows') }} ({{ status.failed.length }})</h3>
                <div v-for="(row, index) in status.failed" :key="`failed-${index}`" class="border border-red-200 rounded p-4 mb-2">
                    <div class="font-medium">{{ row.trainee_name_en }} ({{ row.identity_number }})</div>
                    <div class="text-sm text-red-600">{{ row.error_message }}</div>
                </div>
            </div>

            <div v-if="canSubmit" class="bg-white rounded shadow p-6 mb-6">
                <button @click="submitCertificatesImport" :disabled="isSubmitting" class="btn-primary">
                    {{ isSubmitting ? $t('words.processing') + '...' : $t('words.process-pdfs') }}
                </button>
            </div>

            <div v-if="canSendEmails" class="bg-white rounded shadow p-6 mb-6">
                <h3 class="text-lg font-semibold text-green-700 mb-2">{{ $t('words.pdfs-ready-to-send') }}</h3>
                <p class="text-sm text-gray-600 mb-4">{{ $t('words.pdfs-ready-to-send-help') }}</p>
                <button @click="sendCertificatesEmails" :disabled="isSendingEmails" class="btn-primary">
                    {{ isSendingEmails ? $t('words.sending') + '...' : $t('words.send-emails') }}
                </button>
            </div>

            <div v-if="isBackgroundProcessing" class="bg-white rounded shadow p-6 mb-6">
                <div class="flex items-center text-blue-600 mb-4">
                    <span class="ltr:mr-4 rtl:ml-4">{{ getStatusTitle() }}...</span>
                    <svg class="animate-spin h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <p class="text-sm text-gray-600">{{ $t('words.jasarah-center-certificates-processing-help') }}</p>
            </div>

            <div v-if="status.status === 'sent' || status.status === 'failed'" class="bg-white rounded shadow p-6">
                <a v-if="status.status === 'sent'" :href="route('back.jasarah-center-certificates.delivery-report', importData.id)" class="btn-gray bg-green-500 hover:bg-green-600 text-white mr-4">{{ $t('words.delivery-report') }}</a>
                <button @click="goBack" class="btn-gray bg-gray-500 hover:bg-gray-600">{{ $t('words.back-to-imports') }}</button>
            </div>
        </div>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout'
import BreadcrumbContainer from "@/Components/BreadcrumbContainer"
import axios from 'axios'

export default {
    metaInfo: { title: 'Processing Jasarah Center Certificates' },
    components: {
        BreadcrumbContainer,
        AppLayout,
    },
    props: { import: Object },
    computed: {
        importData() { return this.import },
        canSubmit() {
            const hasUnmatched = this.status.unmatched && this.status.unmatched.length > 0
            const allMapped = !hasUnmatched || this.status.unmatched.every(t => t.selectedTrainee)
            return this.status.status === 'completed' && allMapped && !this.isSubmitting
        },
        canSendEmails() {
            return this.status.status === 'ready_to_send'
        },
        isBackgroundProcessing() {
            return ['processing', 'sending'].includes(this.status.status)
        },
    },
    data() {
        return {
            status: {
                import_id: this.import?.id || null,
                status: this.import?.status || 'unknown',
                total_rows: this.import?.total_rows || 0,
                matched_count: this.import?.matched_count || 0,
                unmatched_count: this.import?.unmatched_count || 0,
                failed_count: this.import?.failed_count || 0,
                matched: [],
                unmatched: [],
                failed: [],
            },
            statusCheckInterval: null,
            searchTimeouts: {},
            isDeleting: false,
            isSubmitting: false,
            isSendingEmails: false,
            showActionsMenu: false,
        }
    },
    mounted() {
        this.loadStatus().then(() => {
            if (this.shouldPollStatus(this.status.status)) {
                this.startStatusChecking()
            }
        })
    },
    beforeDestroy() {
        if (this.statusCheckInterval) clearInterval(this.statusCheckInterval)
    },
    methods: {
        shouldPollStatus(status) {
            return ['processing', 'sending'].includes(status)
        },

        mergeUnmatchedWithLocalState(apiUnmatched) {
            const existingByKey = {}
            ;(this.status.unmatched || []).forEach((row) => {
                existingByKey[row.row_key] = row
            })

            return (apiUnmatched || []).map((row) => {
                const existing = existingByKey[row.row_key] || {}

                return {
                    ...row,
                    searchQuery: existing.searchQuery || '',
                    searchResults: existing.searchResults || [],
                    selectedTrainee: existing.selectedTrainee || null,
                }
            })
        },

        async loadStatus() {
            try {
                const { data } = await axios.get(`/back/jasarah-center-certificates/${this.importData.id}/status`)
                const previousStatus = this.status.status

                this.status = {
                    ...this.status,
                    ...data,
                    unmatched: this.mergeUnmatchedWithLocalState(data.unmatched),
                }

                if (this.statusCheckInterval && !this.shouldPollStatus(this.status.status)) {
                    clearInterval(this.statusCheckInterval)
                    this.statusCheckInterval = null
                } else if (!this.statusCheckInterval && this.shouldPollStatus(this.status.status)) {
                    this.startStatusChecking()
                }

                if (previousStatus !== 'sending' && this.status.status === 'sending') {
                    this.startStatusChecking()
                }
            } catch (err) {
                console.error('Failed to load status', err)
            }
        },

        startStatusChecking() {
            if (this.statusCheckInterval) {
                return
            }

            this.statusCheckInterval = setInterval(async () => {
                if (!this.shouldPollStatus(this.status.status)) {
                    return
                }

                await this.loadStatus()

                if (!this.shouldPollStatus(this.status.status)) {
                    clearInterval(this.statusCheckInterval)
                    this.statusCheckInterval = null
                }
            }, 2000)
        },
        getStatusTitle() {
            const titles = {
                processing: this.$t('words.generating-notices'),
                completed: this.$t('words.processing-completed'),
                ready_to_send: this.$t('words.pdfs-ready-to-send'),
                sending: this.$t('words.sending-certificates'),
                sent: this.$t('words.certificates-sent'),
                failed: this.$t('words.failed'),
            }
            return titles[this.status.status] || this.status.status
        },

        getRowStatusText(status) {
            const labels = {
                pending: this.$t('words.pending'),
                sent: this.$t('words.sent'),
                failed: this.$t('words.failed'),
            }
            return labels[status] || status
        },
        formatDateTime(dateTime) {
            return dateTime ? new Date(dateTime).toLocaleString() : '-'
        },
        async searchTrainees(index) {
            const trainee = this.status.unmatched[index]
            const rowKey = trainee.row_key
            const query = trainee.searchQuery.trim()

            if (query.length < 2) {
                this.$set(trainee, 'searchResults', [])
                return
            }

            if (this.searchTimeouts[rowKey]) {
                clearTimeout(this.searchTimeouts[rowKey])
            }

            this.searchTimeouts[rowKey] = setTimeout(async () => {
                try {
                    const { data } = await axios.get('/back/search', { params: { search: query } })
                    const current = this.status.unmatched.find((row) => row.row_key === rowKey)

                    if (current) {
                        this.$set(current, 'searchResults', data.slice(0, 10))
                    }
                } catch {
                    const current = this.status.unmatched.find((row) => row.row_key === rowKey)

                    if (current) {
                        this.$set(current, 'searchResults', [])
                    }
                }
            }, 300)
        },

        selectTraineeForUnmatched(index, trainee) {
            const row = this.status.unmatched[index]
            this.$set(row, 'selectedTrainee', trainee)
            this.$set(row, 'searchResults', [])
            this.$set(row, 'searchQuery', '')
        },

        removeLinkedTrainee(index) {
            this.$set(this.status.unmatched[index], 'selectedTrainee', null)
        },
        async submitCertificatesImport() {
            const mappings = (this.status.unmatched || []).filter(t => t.selectedTrainee).map(t => ({
                row_key: t.row_key,
                trainee_id: t.selectedTrainee.id,
            }))

            this.isSubmitting = true

            try {
                await axios.post('/back/jasarah-center-certificates/finalize', { import_id: this.status.import_id, mappings })
                await this.loadStatus()
                this.startStatusChecking()
            } catch {
                alert(this.$t('words.submission-failed'))
            } finally {
                this.isSubmitting = false
            }
        },
        async sendCertificatesEmails() {
            this.isSendingEmails = true

            try {
                await axios.post('/back/jasarah-center-certificates/send-emails', { import_id: this.status.import_id })
                await this.loadStatus()
                this.startStatusChecking()
            } catch {
                alert(this.$t('words.submission-failed'))
            } finally {
                this.isSendingEmails = false
            }
        },
        goBack() {
            this.$inertia.visit(route('back.jasarah-center-certificates.index'))
        },
        confirmDelete() {
            if (confirm(this.$t('words.confirm-delete-jasarah-center-certificate'))) this.deleteImport()
        },
        handleDeleteAction() {
            this.showActionsMenu = false
            this.confirmDelete()
        },
        async deleteImport() {
            this.isDeleting = true
            try {
                const { data } = await axios.delete(`/back/jasarah-center-certificates/${this.importData.id}`)
                if (data.success) this.goBack()
            } catch {
                alert(this.$t('words.failed-to-delete-jasarah-center-certificate'))
            } finally {
                this.isDeleting = false
            }
        },
    },
}
</script>
