<!--
  - Copyright (c) 2020 - Clarastars, LLC  - All Rights Reserved.
  -
  - Unauthorized copying of this file via any medium is strictly prohibited.
  - This file is a proprietary of Clarastars LLC and is confidential / educational purpose only.
  -
  - https://clarastars.com - info@clarastars.com
  - @author Shafiq al-Shaar <shafiqalshaar@gmail.com>
  -->

<template>
    <app-layout>
        <div class="container px-6 mx-auto grid pt-6">
            <breadcrumb-container
                :crumbs="[
                    {title: 'dashboard', link: route('dashboard')},
                    {title: 'finance', link: route('back.finance')},
                    {title: 'companies-without-payroll'},
                ]"
            ></breadcrumb-container>

            <form @submit.prevent="loadReport" class="flex flex-wrap items-end gap-3 mb-6">
                <div>
                    <jet-label class="mb-1" for="date_from" :value="$t('words.date-from')" />
                    <input
                        id="date_from"
                        type="date"
                        v-model="form.date_from"
                        class="form-input rounded-md shadow-sm"
                        required
                    >
                </div>
                <div>
                    <jet-label class="mb-1" for="date_to" :value="$t('words.date-to')" />
                    <input
                        id="date_to"
                        type="date"
                        v-model="form.date_to"
                        class="form-input rounded-md shadow-sm"
                        required
                    >
                </div>
                <button class="btn btn-gray" type="submit">{{ $t('words.search') }}</button>
                <a
                    v-if="rows.length"
                    class="btn btn-gray"
                    :href="exportUrl"
                >Excel</a>
            </form>

            <p class="text-sm text-gray-600 mb-4">{{ $t('words.companies-without-payroll-hint') }}</p>

            <div v-if="rows.length" class="bg-white shadow rounded overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                    <tr class="bg-gray-100 text-right">
                        <th class="border px-3 py-2">{{ $t('words.company') }}</th>
                        <th class="border px-3 py-2">{{ $t('words.code') }}</th>
                        <th class="border px-3 py-2">{{ $t('words.trainees-count') }}</th>
                        <th class="border px-3 py-2">{{ $t('words.earliest-start-date') }}</th>
                        <th class="border px-3 py-2">{{ $t('words.last-invoice-date') }}</th>
                        <th class="border px-3 py-2"></th>
                    </tr>
                    </thead>
                    <tbody>
                    <template v-for="row in rows">
                        <tr :key="row.company_id">
                            <td class="border px-3 py-2">
                                <div class="inline-flex items-center gap-2 whitespace-nowrap">
                                    {{ row.company_name }}
                                    <a
                                        :href="route('back.companies.show', row.company_id)"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-blue-600 hover:text-blue-800"
                                        :title="$t('words.open-in-new-tab')"
                                        :aria-label="$t('words.open-in-new-tab')"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </a>
                                </div>
                                <div v-if="row.months && row.months.length" class="mt-2 flex flex-wrap gap-2">
                                    <div
                                        v-for="month in row.months"
                                        :key="month.key"
                                        class="flex flex-col items-center"
                                        :title="month.has_invoice ? $t('words.payroll-month-issued') : $t('words.payroll-month-missing')"
                                    >
                                        <span
                                            class="inline-block w-3 h-3 rounded-full"
                                            :class="month.has_invoice ? 'bg-green-500' : 'bg-red-500'"
                                        ></span>
                                        <span class="mt-1 text-[10px] leading-none text-gray-500">{{ month.label }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="border px-3 py-2">{{ row.company_code }}</td>
                            <td class="border px-3 py-2">{{ row.trainees_count }}</td>
                            <td class="border px-3 py-2">{{ row.earliest_start_date || '—' }}</td>
                            <td class="border px-3 py-2">{{ row.last_invoice_at || '—' }}</td>
                            <td class="border px-3 py-2">
                                <button
                                    type="button"
                                    class="text-blue-600 hover:underline"
                                    @click="toggle(row.company_id)"
                                >
                                    {{ expanded[row.company_id] ? $t('words.hide') : $t('words.show') }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="expanded[row.company_id]" :key="row.company_id + '-detail'">
                            <td colspan="6" class="border px-3 py-2 bg-gray-50">
                                <table class="min-w-full text-xs">
                                    <thead>
                                    <tr class="text-right text-gray-600">
                                        <th class="px-2 py-1">{{ $t('words.trainee') }}</th>
                                        <th class="px-2 py-1">{{ $t('words.identity_number') }}</th>
                                        <th class="px-2 py-1">{{ $t('words.masdr-start-date') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-for="trainee in row.trainees" :key="trainee.trainee_id">
                                        <td class="px-2 py-1 whitespace-nowrap">
                                            <span class="inline-flex items-center gap-2">
                                                {{ trainee.trainee_name }}
                                                <a
                                                    :href="route('back.trainees.show', trainee.trainee_id)"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="text-blue-600 hover:text-blue-800"
                                                    :title="$t('words.open-in-new-tab')"
                                                    :aria-label="$t('words.open-in-new-tab')"
                                                >
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                    </svg>
                                                </a>
                                            </span>
                                        </td>
                                        <td class="px-2 py-1">{{ trainee.identity_number }}</td>
                                        <td class="px-2 py-1">{{ trainee.start_date || '—' }}</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    </template>
                    </tbody>
                </table>
            </div>

            <p v-else-if="searched" class="text-gray-500">{{ $t('words.no-results') }}</p>
        </div>
    </app-layout>
</template>

<script>
    import AppLayout from '@/Layouts/AppLayout'
    import BreadcrumbContainer from '@/Components/BreadcrumbContainer'
    import JetLabel from '@/Jetstream/Label'

    export default {
        metaInfo() {
            return { title: this.$t('words.companies-without-payroll') }
        },
        components: {
            AppLayout,
            BreadcrumbContainer,
            JetLabel,
        },
        props: {
            filters: Object,
            rows: {
                type: Array,
                default: () => [],
            },
        },
        data() {
            return {
                form: {
                    date_from: this.filters.date_from || '',
                    date_to: this.filters.date_to || '',
                },
                expanded: {},
            }
        },
        computed: {
            searched() {
                return !!(this.filters.date_from && this.filters.date_to)
            },
            exportUrl() {
                const params = new URLSearchParams({
                    date_from: this.form.date_from || '',
                    date_to: this.form.date_to || '',
                })
                return route('back.finance.companies-without-payroll.excel') + '?' + params.toString()
            },
        },
        methods: {
            loadReport() {
                this.$inertia.get(route('back.finance.companies-without-payroll'), {
                    date_from: this.form.date_from,
                    date_to: this.form.date_to,
                })
            },
            toggle(companyId) {
                this.$set(this.expanded, companyId, !this.expanded[companyId])
            },
        },
    }
</script>
