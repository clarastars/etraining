<template>
    <app-layout>
        <div class="container px-6 mx-auto grid pt-6">
            <breadcrumb-container
                :crumbs="[
                    {title: 'dashboard', link: route('dashboard')},
                    {title: 'jasarah-center-certificates', link: route('back.jasarah-center-certificates.index')},
                    {title: 'new'},
                ]"
            ></breadcrumb-container>

            <div class="bg-white rounded shadow p-6 mt-6">
                <h2 class="text-xl font-semibold mb-6">{{ $t('words.send-notice-of-attendance') }}</h2>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        {{ $t('words.course') }}
                    </label>
                    <select v-model="selectedCourseId" class="w-full form-select">
                        <option value="">{{ $t('words.select-course') }}</option>
                        <option v-for="course in courses.data" :key="course.id" :value="course.id">
                            {{ course.name_ar }} - {{ course.instructor ? course.instructor.name : 'No Instructor' }} ({{ new Date(course.created_at).toLocaleDateString() }})
                        </option>
                    </select>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        {{ $t('words.csv-file') }}
                    </label>
                    <input
                        type="file"
                        @change="onCsvFileChange"
                        accept=".csv,.txt"
                        class="w-full form-input"
                    />
                    <p class="text-sm text-gray-500 mt-1">
                        {{ $t('words.jasarah-center-certificates-help') }}
                    </p>
                    <button
                        type="button"
                        class="mt-2 text-sm text-indigo-600 hover:text-indigo-900"
                        @click="downloadSampleCsv"
                    >
                        {{ $t('words.download-sample-csv') }}
                    </button>
                </div>

                <div v-if="isProcessing" class="mb-6 p-4 bg-blue-100 border border-blue-400 text-blue-700 rounded">
                    <div class="flex items-center gap-3">
                        <svg class="animate-spin h-5 w-5 text-blue-500 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>{{ $t('words.processing') }}...</span>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <inertia-link
                        :href="route('back.jasarah-center-certificates.index')"
                        class="text-gray-600 hover:text-gray-900"
                    >
                        {{ $t('words.cancel') }}
                    </inertia-link>
                    <button
                        type="button"
                        @click="handleCsvUpload"
                        :disabled="!canProcess || isProcessing"
                        class="btn-primary"
                    >
                        {{ $t('words.process-files') }}
                    </button>
                </div>

                <div v-if="uploadError" class="mt-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                    {{ uploadError }}
                </div>
            </div>
        </div>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout'
import BreadcrumbContainer from "@/Components/BreadcrumbContainer"
import axios from 'axios'

export default {
    metaInfo: { title: 'Jasarah Center Certificates' },
    components: {
        BreadcrumbContainer,
        AppLayout,
    },
    props: {
        courses: Object,
    },
    data() {
        return {
            selectedCourseId: '',
            csvFile: null,
            uploadError: '',
            isProcessing: false,
        }
    },
    computed: {
        canProcess() {
            return !!this.selectedCourseId && !!this.csvFile;
        },
    },
    methods: {
        onCsvFileChange(event) {
            this.csvFile = event.target.files[0];
            this.uploadError = '';
        },

        downloadSampleCsv() {
            const csvContent = 'ID,Name (English)\n11101010,Afnan Mousa\n';
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');

            link.href = URL.createObjectURL(blob);
            link.download = 'jasarah-center-certificates-sample.csv';
            link.click();
            URL.revokeObjectURL(link.href);
        },

        async handleCsvUpload() {
            if (!this.csvFile || !this.selectedCourseId) {
                this.uploadError = this.$t('words.please-select-course-and-file');
                return;
            }

            this.uploadError = '';
            this.isProcessing = true;

            const formData = new FormData();
            formData.append('csv', this.csvFile);
            formData.append('course_id', this.selectedCourseId);

            try {
                const response = await axios.post('/back/jasarah-center-certificates/upload-csv', formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });

                this.$inertia.visit(route('back.jasarah-center-certificates.processing', response.data.import_id));
            } catch (err) {
                this.isProcessing = false;
                this.uploadError = err.response?.data?.error || this.$t('words.upload-failed');
            }
        },
    },
}
</script>
