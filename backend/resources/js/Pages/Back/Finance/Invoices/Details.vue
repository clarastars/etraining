<template>
    <app-layout>
        <div class="px-6 mx-auto pt-6">
            <breadcrumb-container
                :crumbs="[
                    {title: 'dashboard', link: route('dashboard')},
                    {title: 'finance', link: route('back.finance')},
                    {title: 'invoices-details-report'},
                ]"
            ></breadcrumb-container>

            <form @submit.prevent="loadReport" class="grid grid-cols-12 gap-6">
                <div class="col-span-12 sm:col-span-6 mt-5">
                    <jet-label class="mb-2" for="company_id" :value="$t('words.company')" />
                    <select v-model="form.company_id" name="company_id" id="company_id">
                        <option value="">{{ $t('words.company') }}</option>
                        <option v-for="company in companies" :key="company.id" :value="company.id">
                            {{ company.name_ar }}
                        </option>
                    </select>
                </div>
                <div class="col-span-12 sm:col-span-2 mt-5">
                    <jet-label class="mb-2" for="date_from" :value="$t('words.date-from')" />
                    <input id="date_from" type="date" v-model="form.date_from" class="form-input rounded-md shadow-sm w-full" required>
                </div>
                <div class="col-span-12 sm:col-span-2 mt-5">
                    <jet-label class="mb-2" for="date_to" :value="$t('words.date-to')" />
                    <input id="date_to" type="date" v-model="form.date_to" class="form-input rounded-md shadow-sm w-full" required>
                </div>
                <div class="col-span-12 sm:col-span-2 mt-5 flex items-end">
                    <button class="btn btn-gray" type="submit">{{ $t('words.search') }}</button>
                </div>
            </form>

            <p class="text-sm text-gray-500 mt-4">{{ $t('words.invoice-details-hint') }}</p>

            <div v-if="localRows.length" class="mt-4 flex gap-3">
                <a class="btn btn-gray" :href="exportUrl('back.finance.invoices.details.excel')">Excel</a>
                <a class="btn btn-gray" :href="exportUrl('back.finance.invoices.details.pdf')">PDF</a>
            </div>

            <div v-if="localRows.length" class="invoice-details-scroll mt-4 bg-white shadow rounded">
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
                        <td class="border px-2 py-1 whitespace-nowrap">
                            <div>{{ row.masdr_start_label || '—' }}</div>
                            <button type="button" class="text-blue-600 underline" @click="refreshMasdr(row)">
                                {{ $t('words.refresh-from-masdr') }}
                            </button>
                        </td>
                        <td class="border px-2 py-1">{{ row.invoice_date }}</td>
                        <td class="border px-1 py-1">
                            <input type="date" class="form-input text-xs" :value="row.manual_start_date || ''" @change="saveCell(row, 'manual_start_date', $event.target.value)">
                            <button
                                v-if="canCopyDown(rowIndex)"
                                type="button"
                                class="block mt-1 text-blue-600 underline whitespace-nowrap"
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
                                class="block mt-1 text-blue-600 underline whitespace-nowrap"
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
                                class="block mt-1 text-blue-600 underline whitespace-nowrap"
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
                                class="block mt-1 text-blue-600 underline whitespace-nowrap"
                                :disabled="copyingDown"
                                @click="copyDown('full_reward')"
                            >
                                {{ $t('words.apply-to-all-below') }}
                            </button>
                        </td>
                        <td class="border px-2 py-1 bg-gray-50">{{ formatAmount(row.daily_reward) }}</td>
                        <td class="border px-2 py-1 bg-gray-50">{{ formatAmount(row.reward_due) }}</td>
                        <td class="border px-1 py-1">
                            <input type="number" min="0" step="0.01" class="form-input text-xs w-24" :value="numberValue(row.full_fees)" @change="saveCell(row, 'full_fees', $event.target.value)">
                            <button
                                v-if="canCopyDown(rowIndex)"
                                type="button"
                                class="block mt-1 text-blue-600 underline whitespace-nowrap"
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
                                class="block mt-1 text-blue-600 underline whitespace-nowrap"
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
    import JetLabel from '@/Jetstream/Label';
    import AppLayout from '@/Layouts/AppLayout';
    import BreadcrumbContainer from '@/Components/BreadcrumbContainer';
    import 'selectize/dist/js/standalone/selectize.min';

    export default {
        props: ['companies', 'filters', 'rows'],
        metaInfo() {
            return {
                title: this.$t('words.invoices-details-report'),
            }
        },
        components: {
            AppLayout,
            JetLabel,
            BreadcrumbContainer,
        },
        data() {
            return {
                form: {
                    company_id: this.filters.company_id || null,
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
        },
        computed: {
            totals() {
                const keys = [
                    'sub_total', 'tax', 'grand_total', 'day_count',
                    'full_salary', 'daily_salary', 'salary_due',
                    'full_reward', 'daily_reward', 'reward_due',
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
        mounted() {
            let vm = this;
            $(document).ready(function () {
                $('#company_id').selectize({
                    sortField: 'text',
                    maxOptions: 9999,
                    onChange: function (value) {
                        vm.form.company_id = value;
                    }
                });
            });
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
            refreshMasdr(row) {
                if (!window.confirm(this.$t('words.masdr-refresh-confirm'))) {
                    return;
                }
                axios.post(route('back.finance.invoices.details.refresh-masdr', row.invoice_id))
                    .then(response => {
                        const index = this.localRows.findIndex(item => item.invoice_id === row.invoice_id);
                        if (index !== -1) {
                            this.$set(this.localRows, index, response.data);
                        }
                    })
                    .catch(error => {
                        const message = error.response && error.response.data && error.response.data.message
                            ? error.response.data.message
                            : this.$t('words.masdr-refresh-failed');
                        alert(message);
                    });
            },
        },
    }
</script>

<style scoped>
.invoice-details-scroll {
    max-height: calc(100vh - 12rem);
    overflow-x: scroll;
    overflow-y: auto;
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
</style>
