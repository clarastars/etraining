<template>
    <div class="border-2 border-green-600 rounded w-full p-3">
        <div class="flex flex-wrap items-start gap-2 md:flex-nowrap md:items-center md:gap-0">
            <div class="flex flex-shrink-0">
                <img src="/img/gosi.png" alt="gosi" class="p-3" style="max-width: 130px">
            </div>
            <div class="w-full min-w-0 flex-[1_1_200px]">
                <div v-if="!data" class="flex justify-center align-middle">
                    <btn-loading-indicator v-if="$wait.is('LOADING_GOSI')" />
                    <p class="text-xs" v-else>الرجاء الضغط على 'تحديث' لطلب البيانات</p>
                </div>
                <div v-if="data && data.errorText">{{ data.errorText }}</div>
                <template v-if="data && !data.errorText">
                    <table class="w-full text-xs self-center" v-for="record in data.employmentStatusInfo">
                        <colgroup>
                            <col style="width:110px">
                            <col>
                            <col style="width:120px">
                        </colgroup>
                        <tbody>
                        <tr>
                            <td class="border font-bold bg-gray-100">{{ $t('words.updated-date') }}</td>
                            <td class="border">
                                <span :class="{
                                    'font-bold text-red-600': isOlderThan30Days(data.updated_at)
                                }">
                                    {{ data.updated_at ? new Date(data.updated_at).toLocaleDateString('en-GB') : '—' }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="border font-bold bg-gray-100">{{ $t('words.identity_number') }}</td>
                            <td class="border">{{ ninOrIqama }}</td>
                            <td class="border font-bold bg-gray-100">{{ $t('words.full-name') }}</td>
                            <td class="border">{{ record.fullName }}</td>
                        </tr>
                        <tr>
                            <td class="border font-bold bg-gray-100">{{ $t('words.full-wage') }}</td>
                            <td class="border">{{ record.fullWage }}</td>
                            <td class="border font-bold bg-gray-100">{{ $t('words.employer-name') }}</td>
                            <td class="border">{{ record.employerName }} ({{ record.commercialRegistrationNumber }})</td>
                        </tr>
                        <tr>
                            <td class="border font-bold bg-gray-100">{{ $t('words.salary-starting-date') }}</td>
                            <td class="border">{{ record.salaryStartingDate }}</td>
                            <td class="border font-bold bg-gray-100">{{ $t('words.employment-status') }}</td>
                            <td class="border">{{ record.employmentStatus }}</td>
                        </tr>
                        </tbody>
                    </table>
                </template>
            </div>
            <button class="btn-primary self-center mx-2 mt-2 flex-shrink-0" @click="fetch(false)">
                {{ $t('words.refresh') }}
            </button>
            <button class="btn-secondary self-center mx-2 mt-2 flex-shrink-0 whitespace-normal text-center" @click="fetchFresh">
                طلب بيانات جديدة من مصدر
            </button>
        </div>

    </div>
</template>

<script>
import BtnLoadingIndicator from './BtnLoadingIndicator.vue'
export default {
    components: {
        BtnLoadingIndicator
    },
    name: "GosiContainer",
    props: ['ninOrIqama', 'reasons'],
    data() {
        return {
            loading: false,
            data: null,
        }
    },
    methods: {
        fetch(force = true) {
            this.$wait.start('LOADING_GOSI');
            axios.post(route('back.gosi.show'), {
                ninOrIqama: this.ninOrIqama,
                ...this.reasons,
                force: force,
            })
            .then(response => {
                this.data = response.data;
                this.$emit('fetch-success');
                this.$wait.end('LOADING_GOSI');
            })
            .catch(error => {
                this.$wait.end('LOADING_GOSI');
                if (error.response && error.response.data && error.response.data.message === 'Monthly request limit reached.') {
                    alert('Monthly request limit reached.');
                } else {
                    console.log(error);
                }
            });
        },
        fetchFresh() {
            this.fetch(true);
        },
        isOlderThan30Days(date) {
            if (!date) return false;
            const updatedDate = new Date(date);
            const thirtyDaysAgo = new Date();
            thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
            return updatedDate < thirtyDaysAgo;
        }
    },
    watch: {
        reasons: {
            handler(newVal) {
                // Optionally, you can re-fetch or update internal state
                // this.fetch(); // Uncomment if needed
            },
            deep: true,
        }
    }
}
</script>

<style scoped>

</style>
