<template>
    <app-layout>
        <div class="invoice-details-page">
            <breadcrumb-container
                :crumbs="[
                    {title: 'dashboard', link: route('dashboard')},
                    {title: 'finance', link: route('back.finance')},
                    {title: 'invoices-details-report'},
                ]"
            ></breadcrumb-container>

            <form @submit.prevent="loadReport" class="invoice-details-toolbar">
                <company-search-select
                    class="toolbar-company"
                    v-model="selectedCompany"
                    compact
                    search-route="back.finance.invoices.details.companies"
                    :placeholder="$t('words.company')"
                />
                <input id="date_from" type="date" v-model="form.date_from" class="toolbar-field" :aria-label="$t('words.date-from')" required>
                <input id="date_to" type="date" v-model="form.date_to" class="toolbar-field" :aria-label="$t('words.date-to')" required>
                <button class="toolbar-action" type="submit">{{ $t('words.search') }}</button>
                <a v-if="localRows.length" class="toolbar-action" :href="exportUrl('back.finance.invoices.details.excel')">Excel</a>
                <a v-if="localRows.length" class="toolbar-action" :href="exportUrl('back.finance.invoices.details.pdf')">PDF</a>
            </form>

            <div v-if="localRows.length" class="invoice-details-scroll bg-white shadow rounded">
                <table class="min-w-max text-xs border-collapse">
                    <thead>
                    <tr class="bg-gray-100">
                        <th class="border px-2 py-2 sticky right-0 bg-gray-100">{{ $t('words.trainee') }}</th>
                        <th class="border px-2 py-2">{{ $t('words.identity_number') }}</th>
                        <th class="border px-2 py-2">{{ $t('words.subtotal') }}</th>
                        <th class="border px-2 py-2">{{ $t('words.tax') }}</th>
                        <th class="border px-2 py-2">{{ $t('words.grand-total') }}</th>
                        <th class="border px-2 py-2">{{ $t('words.status') }}</th>
                        <th class="border px-2 py-2">{{ $t('words.masdr-start-date') }}</th>
                        <th class="border px-2 py-2">{{ $t('words.invoice-date') }}</th>
                        <th class="border px-2 py-2">{{ $t('words.manual-start-date') }}</th>
                        <th class="border px-2 py-2">{{ $t('words.invoice-details-end-date') }}</th>
                        <th class="border px-2 py-2">{{ $t('words.day-count') }}</th>
                        <th class="border px-2 py-2">{{ $t('words.full-salary') }}</th>
                        <th class="border px-2 py-2">{{ $t('words.daily-salary-cost') }}</th>
                        <th class="border px-2 py-2">{{ $t('words.salary-due') }}</th>
                        <th class="border px-2 py-2">{{ $t('words.full-reward') }}</th>
                        <th class="border px-2 py-2">{{ $t('words.daily-reward-cost') }}</th>
                        <th class="border px-2 py-2">{{ $t('words.reward-due') }}</th>
                        <th class="border px-2 py-2">{{ $t('words.established-fees') }}</th>
                        <th class="border px-2 py-2">{{ $t('words.full-fees') }}</th>
                        <th class="border px-2 py-2">{{ $t('words.daily-fees-cost') }}</th>
                        <th class="border px-2 py-2">{{ $t('words.training-fees-due') }}</th>
                        <th class="border px-2 py-2">{{ $t('words.full-refund') }}</th>
                        <th class="border px-2 py-2">{{ $t('words.daily-refund-cost') }}</th>
                        <th class="border px-2 py-2">{{ $t('words.refund-due') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(row, rowIndex) in localRows" :key="row.invoice_id">
                        <td class="border px-2 py-1 sticky right-0 bg-white whitespace-nowrap">{{ row.trainee_name }}</td>
                        <td class="border px-2 py-1">{{ row.identity_number }}</td>
                        <td class="border px-2 py-1">{{ formatAmount(row.sub_total) }}</td>
                        <td class="border px-2 py-1">{{ formatAmount(row.tax) }}</td>
                        <td class="border px-2 py-1">{{ formatAmount(row.grand_total) }}</td>
                        <td class="border px-2 py-1 whitespace-nowrap">{{ row.status }}</td>
                        <td class="border px-2 py-1 masdr-cell">
                            <div class="masdr-summary">
                                <div class="font-medium whitespace-nowrap">{{ row.masdr_start_label || '—' }}</div>
                                <div v-if="row.masdr_employer_name" class="masdr-meta">{{ row.masdr_employer_name }}</div>
                                <div v-if="row.masdr_wage !== null && row.masdr_wage !== undefined" class="masdr-meta whitespace-nowrap">
                                    {{ $t('words.masdr-wage') }}: {{ formatAmount(row.masdr_wage) }}
                                </div>
                                <div v-if="row.masdr_working_months !== null && row.masdr_working_months !== undefined && row.masdr_approx_ago" class="masdr-meta whitespace-nowrap">
                                    {{ $t('words.masdr-working-months') }}: {{ row.masdr_working_months }} ({{ row.masdr_approx_ago }})
                                </div>
                            </div>
                            <pre v-if="row.masdr_payload" class="masdr-tooltip">{{ formatMasdrPayload(row.masdr_payload) }}</pre>
                        </td>
                        <td class="border px-2 py-1">{{ row.invoice_date }}</td>
                        <td class="border px-1 py-1">
                            <input type="date" class="form-input text-xs" :value="row.manual_start_date || ''" @change="saveCell(row, 'manual_start_date', $event.target.value)">
                            <button
                                v-if="canCopyDown(rowIndex)"
                                type="button"
                                class="copy-down"
                                :disabled="copyingDown"
                                @click="copyDown('manual_start_date')"
                            >
                                {{ $t('words.apply-to-all-below') }}
                            </button>
                        </td>
                        <td class="border px-1 py-1">
                            <input type="date" class="form-input text-xs" :value="row.end_date || ''" @change="saveCell(row, 'end_date', $event.target.value)">
                            <button
                                v-if="canCopyDown(rowIndex)"
                                type="button"
                                class="copy-down"
                                :disabled="copyingDown"
                                @click="copyDown('end_date')"
                            >
                                {{ $t('words.apply-to-all-below') }}
                            </button>
                        </td>
                        <td class="border px-2 py-1 bg-gray-50">{{ row.day_count === null ? '' : row.day_count }}</td>
                        <td class="border px-1 py-1">
                            <input type="number" min="0" step="0.01" class="form-input text-xs w-24" :value="numberValue(row.full_salary)" @change="saveCell(row, 'full_salary', $event.target.value)">
                            <button
                                v-if="canCopyDown(rowIndex)"
                                type="button"
                                class="copy-down"
                                :disabled="copyingDown"
                                @click="copyDown('full_salary')"
                            >
                                {{ $t('words.apply-to-all-below') }}
                            </button>
                        </td>
                        <td class="border px-2 py-1 bg-gray-50">{{ formatAmount(row.daily_salary) }}</td>
                        <td class="border px-2 py-1 bg-gray-50">{{ formatAmount(row.salary_due) }}</td>
                        <td class="border px-1 py-1">
                            <input type="number" min="0" step="0.01" class="form-input text-xs w-24" :value="numberValue(row.full_reward)" @change="saveCell(row, 'full_reward', $event.target.value)">
                            <button
                                v-if="canCopyDown(rowIndex)"
                                type="button"
                                class="copy-down"
                                :disabled="copyingDown"
                                @click="copyDown('full_reward')"
                            >
                                {{ $t('words.apply-to-all-below') }}
                            </button>
                        </td>
                        <td class="border px-2 py-1 bg-gray-50">{{ formatAmount(row.daily_reward) }}</td>
                        <td class="border px-2 py-1 bg-gray-50">{{ formatAmount(row.reward_due) }}</td>
                        <td class="border px-2 py-1 bg-gray-50">{{ formatAmount(row.established_fees) }}</td>
                        <td class="border px-1 py-1">
                            <input type="number" min="0" step="0.01" class="form-input text-xs w-24" :value="numberValue(row.full_fees)" @change="saveCell(row, 'full_fees', $event.target.value)">
                            <button
                                v-if="canCopyDown(rowIndex)"
                                type="button"
                                class="copy-down"
                                :disabled="copyingDown"
                                @click="copyDown('full_fees')"
                            >
                                {{ $t('words.apply-to-all-below') }}
                            </button>
                        </td>
                        <td class="border px-2 py-1 bg-gray-50">{{ formatAmount(row.daily_fees) }}</td>
                        <td class="border px-2 py-1 bg-gray-50">{{ formatAmount(row.fees_due) }}</td>
                        <td class="border px-1 py-1">
                            <input type="number" min="0" step="0.01" class="form-input text-xs w-24" :value="numberValue(row.full_refund)" @change="saveCell(row, 'full_refund', $event.target.value)">
                            <button
                                v-if="canCopyDown(rowIndex)"
                                type="button"
                                class="copy-down"
                                :disabled="copyingDown"
                                @click="copyDown('full_refund')"
                            >
                                {{ $t('words.apply-to-all-below') }}
                            </button>
                        </td>
                        <td class="border px-2 py-1 bg-gray-50">{{ formatAmount(row.daily_refund) }}</td>
                        <td class="border px-2 py-1 bg-gray-50">{{ formatAmount(row.refund_due) }}</td>
                    </tr>
                    </tbody>
                    <tfoot>
                    <tr class="bg-gray-100 font-semibold">
                        <td class="border px-2 py-2 sticky right-0 bg-gray-100">{{ $t('words.total') }}</td>
                        <td class="border px-2 py-2"></td>
                        <td class="border px-2 py-2">{{ formatTotal('sub_total') }}</td>
                        <td class="border px-2 py-2">{{ formatTotal('tax') }}</td>
                        <td class="border px-2 py-2">{{ formatTotal('grand_total') }}</td>
                        <td class="border px-2 py-2"></td>
                        <td class="border px-2 py-2"></td>
                        <td class="border px-2 py-2"></td>
                        <td class="border px-2 py-2"></td>
                        <td class="border px-2 py-2"></td>
                        <td class="border px-2 py-2">{{ formatTotal('day_count') }}</td>
                        <td class="border px-2 py-2">{{ formatTotal('full_salary') }}</td>
                        <td class="border px-2 py-2">{{ formatTotal('daily_salary') }}</td>
                        <td class="border px-2 py-2">{{ formatTotal('salary_due') }}</td>
                        <td class="border px-2 py-2">{{ formatTotal('full_reward') }}</td>
                        <td class="border px-2 py-2">{{ formatTotal('daily_reward') }}</td>
                        <td class="border px-2 py-2">{{ formatTotal('reward_due') }}</td>
                        <td class="border px-2 py-2">{{ formatTotal('established_fees') }}</td>
                        <td class="border px-2 py-2">{{ formatTotal('full_fees') }}</td>
                        <td class="border px-2 py-2">{{ formatTotal('daily_fees') }}</td>
                        <td class="border px-2 py-2">{{ formatTotal('fees_due') }}</td>
                        <td class="border px-2 py-2">{{ formatTotal('full_refund') }}</td>
                        <td class="border px-2 py-2">{{ formatTotal('daily_refund') }}</td>
                        <td class="border px-2 py-2">{{ formatTotal('refund_due') }}</td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </app-layout>
</template>

<script>
    import AppLayout from '@/Layouts/AppLayout';
    import BreadcrumbContainer from '@/Components/BreadcrumbContainer';
    import CompanySearchSelect from '@/Components/CompanySearchSelect';

    export default {
        props: ['company', 'filters', 'rows'],
        metaInfo() {
            return {
                title: this.$t('words.invoices-details-report'),
            }
        },
        components: {
            AppLayout,
            BreadcrumbContainer,
            CompanySearchSelect,
        },
        data() {
            return {
                selectedCompany: this.company || null,
                form: {
                    company_id: this.company ? this.company.id : (this.filters.company_id || null),
                    date_from: this.filters.date_from || new Date().toISOString().substring(0, 10),
                    date_to: this.filters.date_to || new Date().toISOString().substring(0, 10),
                },
                localRows: this.rows || [],
                copyingDown: false,
            }
        },
        watch: {
            rows(rows) {
                this.localRows = rows || [];
            },
            company(company) {
                this.selectedCompany = company || null;
                this.form.company_id = company ? company.id : null;
            },
            selectedCompany(company) {
                this.form.company_id = company ? company.id : null;
            },
        },
        computed: {
            totals() {
                const keys = [
                    'sub_total', 'tax', 'grand_total', 'day_count',
                    'full_salary', 'daily_salary', 'salary_due',
                    'full_reward', 'daily_reward', 'reward_due',
                    'established_fees',
                    'full_fees', 'daily_fees', 'fees_due',
                    'full_refund', 'daily_refund', 'refund_due',
                ];
                const totals = {};

                keys.forEach((key) => {
                    totals[key] = this.localRows.reduce((sum, row) => {
                        const value = row[key];
                        if (value === null || value === undefined || value === '') {
                            return sum;
                        }

                        return sum + Number(value);
                    }, 0);
                });

                return totals;
            },
        },
        methods: {
            loadReport() {
                this.$inertia.get(route('back.finance.invoices.details'), {
                    company_id: this.form.company_id,
                    date_from: this.form.date_from,
                    date_to: this.form.date_to,
                });
            },
            exportUrl(name) {
                const params = new URLSearchParams({
                    company_id: this.form.company_id || '',
                    date_from: this.form.date_from || '',
                    date_to: this.form.date_to || '',
                });
                return route(name) + '?' + params.toString();
            },
            numberValue(value) {
                return value === null || value === undefined ? '' : value;
            },
            formatMasdrPayload(payload) {
                try {
                    return JSON.stringify(payload, null, 2);
                } catch (error) {
                    return String(payload);
                }
            },
            formatAmount(value) {
                if (value === null || value === undefined || value === '') {
                    return '';
                }
                return Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },
            formatTotal(key) {
                const value = this.totals[key];
                if (key === 'day_count') {
                    return Number(value).toLocaleString('en-US', { maximumFractionDigits: 0 });
                }

                return this.formatAmount(value);
            },
            canCopyDown(rowIndex) {
                return rowIndex === 0 && this.localRows.length > 1;
            },
            saveCell(row, field, value) {
                return axios.patch(route('back.finance.invoices.details.update', row.invoice_id), {
                    field: field,
                    value: value === '' ? null : value,
                }).then(response => {
                    const index = this.localRows.findIndex(item => item.invoice_id === row.invoice_id);
                    if (index !== -1) {
                        this.$set(this.localRows, index, response.data);
                    }
                }).catch(() => {
                    alert(this.$t('words.error-occurred'));
                    return Promise.reject();
                });
            },
            copyDown(field) {
                if (this.copyingDown || this.localRows.length < 2) {
                    return;
                }

                if (!window.confirm(this.$t('words.apply-to-all-below-confirm'))) {
                    return;
                }

                const value = this.localRows[0][field];
                const rows = this.localRows.slice(1);
                this.copyingDown = true;

                Promise.all(rows.map(row => this.saveCell(row, field, value === null || value === undefined ? '' : value)))
                    .catch(() => {})
                    .finally(() => {
                        this.copyingDown = false;
                    });
            },
        },
    }
</script>

<style scoped>
.invoice-details-page {
    display: flex;
    flex-direction: column;
    box-sizing: border-box;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    height: calc(100vh - 4.5rem);
    padding: 0.25rem 0.75rem 0.35rem;
    overflow: hidden;
}

.invoice-details-page >>> nav {
    margin: 0 0 0.35rem;
    font-size: 0.8125rem;
}

.invoice-details-toolbar {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex: 0 0 auto;
    flex-wrap: wrap;
    margin-bottom: 0.4rem;
}

.toolbar-company {
    flex: 1 1 14rem;
    min-width: 12rem;
    max-width: 22rem;
}

.toolbar-company >>> .company-search-input {
    height: 2rem;
    padding-top: 0;
    padding-bottom: 0;
}

.toolbar-field {
    height: 2rem;
    width: 9.5rem;
    padding: 0 0.4rem;
    font-size: 0.8125rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    background: #fff;
}

.toolbar-action {
    display: inline-flex;
    align-items: center;
    height: 2rem;
    padding: 0 0.7rem;
    border-radius: 0.375rem;
    background: #4b5563;
    color: #fff;
    font-size: 0.75rem;
    font-weight: 700;
    line-height: 1;
    white-space: nowrap;
}

.toolbar-action:hover,
.toolbar-action:focus {
    background: #f97316;
    color: #fff;
}

.invoice-details-scroll {
    flex: 1 1 auto;
    min-height: 0;
    min-width: 0;
    width: 100%;
    max-width: 100%;
    overflow: scroll;
    scrollbar-color: #6b7280 #e5e7eb;
}

.invoice-details-scroll::-webkit-scrollbar {
    -webkit-appearance: none;
    height: 14px;
    width: 14px;
}

.invoice-details-scroll::-webkit-scrollbar-thumb {
    background: #6b7280;
    border-radius: 7px;
}

.invoice-details-scroll::-webkit-scrollbar-track {
    background: #e5e7eb;
}

.invoice-details-scroll th,
.invoice-details-scroll td {
    padding: 2px 4px;
    line-height: 1.2;
}

.invoice-details-scroll .form-input {
    height: 1.5rem;
    min-height: 0;
    padding: 0 4px;
    font-size: 11px;
    line-height: 1.5rem;
    border-radius: 0.25rem;
}

.copy-down {
    display: block;
    margin-top: 1px;
    padding: 0;
    border: 0;
    background: none;
    color: #2563eb;
    font-size: 10px;
    line-height: 1.1;
    text-decoration: underline;
    white-space: nowrap;
    cursor: pointer;
}

.copy-down:disabled {
    opacity: 0.5;
    cursor: default;
}

.invoice-details-scroll thead th {
    position: sticky;
    top: 0;
    z-index: 10;
    background: #f3f4f6;
}

.invoice-details-scroll thead th:first-child {
    z-index: 20;
    right: 0;
}

.masdr-cell {
    position: relative;
    max-width: 14rem;
    vertical-align: top;
}

.masdr-summary {
    cursor: help;
}

.masdr-meta {
    color: #6b7280;
    font-size: 10px;
    line-height: 1.2;
    white-space: normal;
}

.masdr-tooltip {
    display: none;
    position: absolute;
    top: 100%;
    right: 0;
    z-index: 40;
    width: 18rem;
    max-height: 14rem;
    margin: 0;
    padding: 0.5rem;
    overflow: auto;
    background: #111827;
    color: #f9fafb;
    border-radius: 0.375rem;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2);
    font-size: 10px;
    line-height: 1.35;
    white-space: pre-wrap;
    word-break: break-word;
    direction: ltr;
    text-align: left;
}

.masdr-cell:hover .masdr-tooltip,
.masdr-cell:focus-within .masdr-tooltip {
    display: block;
}

.invoice-details-scroll tfoot td {
    position: sticky;
    bottom: 0;
    z-index: 10;
    background: #f3f4f6;
    box-shadow: inset 0 1px 0 #d1d5db;
}

.invoice-details-scroll tfoot td:first-child {
    z-index: 30;
    right: 0;
}
</style>
