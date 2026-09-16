<template>
    <app-layout>
        <div class="container px-6 mx-auto grid pt-6">
            <breadcrumb-container
                :crumbs="[
                    {title: 'dashboard', link: route('dashboard')},
                    {title: 'trainees', link: route('back.trainees.index')},
                    {title_raw: trainee.name},
                ]"
            ></breadcrumb-container>

            <div class="grid grid-cols-6 gap-6">

                <div class="col-span-6 items-center justify-end bg-gray-50 text-right gap-6">

                    <a :href="route('back.trainees.admin.attendance-sheet.pdf', trainee.id)"
                       target="_blank"
                       class="items-center justify-start text-left float-left rounded-md px-4 py-2 bg-gray-200 hover:bg-gray-300 text-right">
                        {{ $t('words.attendance-sheet') }}
                    </a>

                    <button
                        v-if="!editButton.editOption"
                        @click="editTrainee"
                        class="items-center justify-end rounded-md px-4 py-2 bg-gray-200 hover:bg-gray-300 text-right"
                    >
                        {{ editButton.text }}
                    </button>
                    <button
                        v-else
                        @click="editTrainee"
                        :disabled="$wait.is('UPDATING_TRAINEE')"
                        :class="{
                            'bg-green-200 cursor-wait': $wait.is('UPDATING_TRAINEE'),
                        }"
                        class="items-center justify-end rounded-md px-4 py-2 bg-green-300 hover:bg-green-400 text-right"
                    >
                        {{ editButton.text }}
                    </button>

                    <button
                        v-if="editButton.editOption"
                        @click="cancelEdit"
                        class="items-center justify-end rounded-md px-4 py-2 bg-red-300 hover:bg-red-400 text-right"
                    >
                        {{ cancelButton.text }}
                    </button>

                    <button @click="unblock" class="items-center justify-end rounded-md px-4 py-2 bg-red-600 hover:bg-red-600 text-right text-white">
                        {{ $t('words.unblock') }}
                    </button>
                    <change-trainee-password :trainee="trainee" />


                </div>

                <validation-errors
                    class="col-span-6"
                    :errors="validationErrors"
                    v-if="validationErrors"
                />

                <div class="col-span-6 items-center justify-end bg-gray-50 text-right flex gap-6" v-can="'view-gosi'">
                    <gosi-container :nin-or-iqama="trainee.identity_number"></gosi-container>
                </div>

                <div class="col-span-6 sm:col-span-2">
                    <jet-label for="trainee_group_name" :value="$t('words.group-name')" />
                    <jet-input id="trainee_group_name"
                               type="text"
                               :class="editButton.inputClass"
                               :value="trainee.trainee_group ? trainee.trainee_group.name : ''"
                               autocomplete="off"
                               :disabled="!editButton.editOption" />
                </div>

                <div class="col-span-6 sm:col-span-2">
                    <jet-label for="company_id" :value="$t('words.company')" />
                    <jet-input id="company_id"
                               type="text"
                               :class="editButton.inputClass"
                               :value="trainee.company ? trainee.company.name_ar : ''"
                               autocomplete="off"
                               :disabled="!editButton.editOption" />
                    <div v-if="trainee.company && trainee.company.nature_of_work === 'عمل عن بعد'" class="mt-2 p-2 bg-red-100 text-red-800 rounded-md text-sm">
                        هذه الشركة تعمل عن بعد
                    </div>
                </div>

                <div class="col-span-6 sm:col-span-2">
                    <jet-label for="name" :value="$t('words.name')" />
                    <jet-input id="name" type="text" :class="editButton.inputClass" v-model="trainee.name" autocomplete="off" :disabled="!editButton.editOption" />
                </div>

                <div class="col-span-6 sm:col-span-2">
                    <jet-label for="english_name" :value="$t('words.name_en')" />
                    <jet-input id="english_name" type="text" :class="editButton.inputClass" v-model="trainee.english_name" autocomplete="off" :disabled="!editButton.editOption" />
                </div>

                <div class="col-span-6 sm:col-span-2">
                    <jet-label for="identity_number" :value="$t('words.identity_number')" />
                    <jet-input id="identity_number" type="text" :class="editButton.inputClass" v-model="trainee.identity_number" :disabled="!editButton.editOption" />
                </div>

                <div class="col-span-6 sm:col-span-2">
                    <jet-label for="birthday" :value="$t('words.birthday')" />
                    <jet-input id="birthday" type="date" :class="editButton.inputClass" v-model="trainee.birthday" :disabled="!editButton.editOption" />
                </div>

                <div class="col-span-6 sm:col-span-2">
                    <jet-label for="phone" :value="$t('words.phone')" />
                    <jet-input id="phone" type="text" :class="editButton.inputClass" v-model="trainee.phone" placeholder="9665XXXXXXXX" :disabled="!editButton.editOption" />
                </div>

                <div class="col-span-6 sm:col-span-2">
                    <jet-label for="phone_additional" :value="$t('words.phone_additional')" />
                    <jet-input id="phone_additional" type="text" :class="editButton.inputClass" v-model="trainee.phone_additional" :disabled="!editButton.editOption" />
                </div>

                <div class="col-span-6 sm:col-span-2">
                    <jet-label for="national_address" :value="$t('words.national-address')" />
                    <jet-input id="national_address" type="text" :class="editButton.inputClass" v-model="trainee.national_address" :disabled="!editButton.editOption" />
                </div>

                <div class="col-span-6 sm:col-span-2">
                    <jet-label for="email" :value="$t('words.email')" />
                    <jet-input id="email" type="text" :class="editButton.inputClass" v-model="trainee.email" :disabled="!editButton.editOption" />
                </div>

                <div class="col-span-6 sm:col-span-2" v-if="this.lang=='ar'">
                    <jet-label for="educational_level" :value="$t('words.educational_level')" />

                    <select :class="editButton.selectInputClass"
                                        v-model="trainee.educational_level_id"
                                        id="educational_level_id"  :disabled="!editButton.editOption" >
                                    <option v-for="educational_level in educational_levels" :key="educational_level.id" :value="educational_level.id">{{ educational_level.name_ar }}</option>
                    </select>

                </div>

                <div class="col-span-6 sm:col-span-2" v-else>
                    <jet-label for="educational_level" :value="$t('words.educational_level')" />

                    <select :class="editButton.selectInputClass"
                                        v-model="trainee.educational_level_id"
                                        id="educational_level_id"  :disabled="!editButton.editOption" >
                                    <option v-for="educational_level in educational_levels" :key="educational_level.id" :value="educational_level.id">{{ educational_level.name_en }}</option>
                    </select>


                </div>

                <div class="col-span-6 sm:col-span-2">
                    <jet-label for="city_id" :value="$t('words.city')" />

                    <select :class="editButton.selectInputClass"
                            v-model="trainee.city_id"
                            id="city_id"  :disabled="!editButton.editOption">
                        <option v-for="city in cities" :key="city.id" :value="city.id">{{ city.name_ar }}</option>
                    </select>
                </div>

                <div class="col-span-6 sm:col-span-1" v-if="this.lang=='ar'">
                    <jet-label for="marital_status" :value="$t('words.marital_status')" />

                    <select :class="editButton.selectInputClass"
                                        v-model="trainee.marital_status_id"
                                        id="city_id"  :disabled="!editButton.editOption" >
                                    <option  v-for="marital_status in marital_statuses" :key="marital_status.id" :value="marital_status.id">{{ marital_status.name_ar }}</option>
                    </select>


                </div>

                <div class="col-span-6 sm:col-span-1" v-else>

                    <jet-label for="marital_status" :value="$t('words.marital_status')" />

                    <select :class="editButton.selectInputClass"
                            v-model="trainee.marital_status_id"
                            id="city_id"  :disabled="!editButton.editOption" >
                        <option  v-for="marital_status in marital_statuses" :key="marital_status.id" :value="marital_status.id">{{ marital_status.name_en }}</option>
                    </select>

                </div>

                <div class="col-span-6 sm:col-span-1">
                    <jet-label for="children_count" :value="$t('words.children_count')" />
                    <jet-input id="children_count" type="text" :class="editButton.inputClass" v-model="trainee.children_count" :disabled="!editButton.editOption" />
                </div>

                <div class="col-span-6 sm:col-span-2">
                    <jet-label for="deleted-at" :value="$t('words.blocked-at')" />
                    <jet-input id="deleted-at" type="text" :class="editButton.inputClass" v-model="trainee.deleted_at_timezone" :disabled="!editButton.editOption" />
                </div>

                <div class="col-span-6 sm:col-span-2">
                    <jet-label for="reason" :value="$t('words.delete-remark')" />
                    <input id="reason"
                            autocomplete="off"
                               type="text"
                               class="mt-1 block w-full border-2 border-gray-200 bg-white py-2.5 px-4 pr-8 rounded leading-tight focus:outline-none focus:border-blue-300"
                               @blur="saveDeletedRemark(trainee)"
                               v-model="trainee.deleted_remark"/>
                </div>

                <div class="col-span-6 sm:col-span-6">
                    <jet-label for="name" :value="$t('words.status')" />
                    <p>

                        <span class="text-sm inline-block mt-2 p-1 px-2 bg-red-300 rounded-lg">
                            {{ $t('words.blocked') }}
                        </span>

                    </p>
                </div>

                <div class="col-span-6 sm:col-span-1" v-if="canViewCertificates">
                    <jet-label 
                        for="name" 
                        :value="$t('words.certificates')" 
                        @click="navigateToCreateCertificate"
                        class="cursor-pointer"
                    />
                    <div 
                        @click="navigateToCreateCertificate"
                        class="cursor-pointer hover:bg-gray-100 rounded"
                    >
                        <div v-if="trainee.custom_certificates && trainee.custom_certificates.length > 0" 
                             class="text-sm mt-2 p-1 px-2 bg-gray-200 rounded-lg">
                            <div v-for="certificate in trainee.custom_certificates" :key="certificate.id" class="mt-1">
                                {{ certificate.title }} - {{ certificate.issued_at_formatted }}
                            </div>
                        </div>
                        
                        <!-- UK Certificates -->
                        <div v-if="trainee.uk_certificates && trainee.uk_certificates.length > 0" 
                             class="text-sm mt-2 p-1 px-2 bg-blue-100 rounded-lg">
                            <div v-for="certificate in trainee.uk_certificates" :key="certificate.id" class="mt-1 flex items-center justify-between">
                                <span>{{ certificate.uk_certificate.course.name_ar }} - {{ certificate.sent_at ? new Date(certificate.sent_at).toLocaleDateString() : '' }}</span>
                                <a 
                                    :href="route('back.uk-certificates.download', certificate.id)"
                                    target="_blank"
                                    class="text-blue-600 hover:text-blue-800 ml-2"
                                    @click.stop
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                    </svg>
                                </a>
                            </div>
                        </div>
                        
                        <div v-if="(!trainee.custom_certificates || trainee.custom_certificates.length === 0) && (!trainee.uk_certificates || trainee.uk_certificates.length === 0)" 
                             class="text-sm inline-block mt-2 p-1 px-2 bg-gray-200 rounded-lg hover:bg-gray-300">
                            {{ $t("words.no-certificates") }}
                        </div>
                    </div>
                </div>

                <div class="col-span-6 sm:col-span-1" v-if="canViewCertificates">
                    <jet-label :value="$t('words.jasarah-certificates')" />
                    <div v-if="trainee.jasarah_center_certificate_rows && trainee.jasarah_center_certificate_rows.length > 0"
                         class="text-sm mt-2 p-1 px-2 bg-green-100 rounded-lg">
                        <div v-for="row in trainee.jasarah_center_certificate_rows" :key="row.id" class="mt-1 flex items-center justify-between">
                            <span>
                                {{ row.jasarah_center_certificate?.course_title || row.jasarah_center_certificate?.course?.name_ar }}
                                - {{ row.sent_at ? new Date(row.sent_at).toLocaleDateString() : '' }}
                            </span>
                            <a
                                :href="route('back.jasarah-center-certificates.download', row.id)"
                                target="_blank"
                                class="text-blue-600 hover:text-blue-800 ml-2"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                </svg>
                            </a>
                        </div>
                    </div>
                    <div v-else class="text-sm inline-block mt-2 p-1 px-2 bg-gray-200 rounded-lg">
                        {{ $t('words.no-jasarah-certificates') }}
                    </div>
                </div>
            </div>


            <jet-section-border></jet-section-border>

            <div class="grid grid-cols-1 md:grid-cols-6 gap-6 my-2">
                <div class="md:col-span-4 lg:col-span-3 sm:col-span-3">
                    <div class="px-4 sm:px-0">
                        <h3 class="text-lg font-medium text-gray-900">
                            {{ $t('words.documents') }}
                        </h3>

                        <p class="mt-1 text-sm text-gray-600">
                            {{ $t('words.documents-help') }}
                        </p>

                        <div class="mt-3">
                            <inertia-link class="text-blue-600 border-2 border-blue-600 text-xs p-1" :href="route('back.trainees.files.index', trainee.id)">
                                {{ $t('words.other-files') }} ({{ trainee.general_files_count }})
                            </inertia-link>
                        </div>
                    </div>
                </div>

                <div class="md:col-span-3 lg:col-span-1 sm:col-span-3">
                    <jet-label :value="$t('words.identity-card-photocopy')" class="mb-2" />

                    <div class="bg-white border-2 rounder-lg flex flex-col justify-center items-center min-container-upload" v-if="trainee.identity_copy_url">
                        <a class="bg-gray-700 text-white font-semibold p-2 text-center w-1/2 rounded my-1" target="_blank" :href="trainee.identity_copy_url">{{ $t('words.download') }}</a>
                        <button class="bg-red-500 text-white font-semibold p-2 text-center w-1/2 rounded my-1" @click="deleteIdentity">{{ $t('words.delete') }}</button>
                    </div>
                    <vue-dropzone v-else
                                  id="dropzoneIdentity"
                                  @vdropzone-sending="sendingCsrf"
                                  @vdropzone-success="onIdentityUploadSuccess"
                                  :options="dropzoneOptionsIdentity"
                    ></vue-dropzone>
                </div>

                <detected-english-name-popup
                    :show="showDetectedEnglishNamePopup"
                    :detected-name="detectedEnglishName"
                    :trainee-id="trainee.id"
                    @confirmed="onDetectedEnglishNameConfirmed"
                    @declined="onDetectedEnglishNameDeclined"
                />

                <div class="md:col-span-3 lg:col-span-1 sm:col-span-3">
                    <jet-label :value="$t('words.qualification-photocopy')" class="mb-2" />

                    <div class="bg-white border-2 rounder-lg flex flex-col justify-center items-center min-container-upload" v-if="trainee.qualification_copy_url">
                        <a class="bg-gray-700 text-white font-semibold p-2 text-center w-1/2 rounded my-1" target="_blank" :href="trainee.qualification_copy_url">{{ $t('words.download') }}</a>
                        <button class="bg-red-500 text-white font-semibold p-2 text-center w-1/2 rounded my-1" @click="deleteQualification">{{ $t('words.delete') }}</button>
                    </div>
                    <vue-dropzone v-else
                                  id="dropzoneQualification"
                                  @vdropzone-sending="sendingCsrf"
                                  :options="dropzoneOptionsQualification"
                    ></vue-dropzone>
                </div>

                <div class="md:col-span-3 lg:col-span-1 sm:col-span-3">
                    <jet-label :value="$t('words.bank-account-photocopy')" class="mb-2" />

                    <div class="bg-white border-2 rounder-lg flex flex-col justify-center items-center min-container-upload" v-if="trainee.bank_account_copy_url">
                        <a class="bg-gray-700 text-white font-semibold p-2 text-center w-1/2 rounded my-1" target="_blank" :href="trainee.bank_account_copy_url">{{ $t('words.download') }}</a>
                        <button class="bg-red-500 text-white font-semibold p-2 text-center w-1/2 rounded my-1" @click="deleteBankAccount">{{ $t('words.delete') }}</button>
                    </div>
                    <vue-dropzone v-else
                                  id="dropzoneBankAccount"
                                  @vdropzone-sending="sendingCsrf"
                                  :options="dropzoneOptionsBankAccount"
                    ></vue-dropzone>
                </div>

                <div class="md:col-span-3 lg:col-span-1 sm:col-span-3" v-if="canViewSpecialDocuments">
                    <jet-label :value="$t('words.gosi-certificate')" class="mb-2" />

                    <div class="bg-white border-2 rounder-lg flex flex-col justify-center items-center min-container-upload" v-if="trainee.gosi_certificate_copy_url">
                        <a class="bg-gray-700 text-white font-semibold p-2 text-center w-1/2 rounded my-1" target="_blank" :href="trainee.gosi_certificate_copy_url">{{ $t('words.download') }}</a>
                        <button class="bg-red-500 text-white font-semibold p-2 text-center w-1/2 rounded my-1" @click="deleteGosiCertificate">{{ $t('words.delete') }}</button>
                    </div>
                    <vue-dropzone v-else
                                  id="dropzoneGosiCertificate"
                                  @vdropzone-sending="sendingCsrf"
                                  :options="dropzoneOptionsGosiCertificate"
                    ></vue-dropzone>
                </div>

                <div class="md:col-span-3 lg:col-span-1 sm:col-span-3" v-if="canViewSpecialDocuments">
                    <jet-label :value="$t('words.qiwa-contract')" class="mb-2" />

                    <div class="bg-white border-2 rounder-lg flex flex-col justify-center items-center min-container-upload" v-if="trainee.qiwa_contract_copy_url">
                        <a class="bg-gray-700 text-white font-semibold p-2 text-center w-1/2 rounded my-1" target="_blank" :href="trainee.qiwa_contract_copy_url">{{ $t('words.download') }}</a>
                        <button class="bg-red-500 text-white font-semibold p-2 text-center w-1/2 rounded my-1" @click="deleteQiwaContract">{{ $t('words.delete') }}</button>
                    </div>
                    <vue-dropzone v-else
                                  id="dropzoneQiwaContract"
                                  @vdropzone-sending="sendingCsrf"
                                  :options="dropzoneOptionsQiwaContract"
                    ></vue-dropzone>
                </div>
            </div>

            <jet-section-border></jet-section-border>

            <div
                class="grid grid-cols-1 md:grid-cols-2 gap-6 my-2"
            >
                <div class="md:col-span-4 lg:col-span-1 sm:col-span-3">
                    <div class="px-4 sm:px-0">
                        <h3 class="text-lg font-medium text-gray-900">
                            {{ $t('words.invoices') }}
                        </h3>

                        <p class="mt-1 text-sm text-gray-600">
                            {{ $t('words.invoices-help') }}
                        </p>
                    </div>
                </div>
                <div class="md:col-span-3 lg:col-span-1 sm:col-span-3">
                    <div class="flex w-full justify-end">
                        <inertia-link :href="route('back.trainees.invoices.create', trainee.id)">
                            <jet-button type="button">
                                {{ $t('words.issue-invoice') }}
                            </jet-button>
                        </inertia-link>
                    </div>
                    <table class="w-full whitespace-no-wrap bg-white rounded-lg my-5 p-5 shadow text-sm">
                        <tr class="text-left font-bold text-center">
                            <th class="px-6 pt-6 pb-4 text-left">{{ $t('words.invoice-no') }}</th>
                            <th class="px-6 pt-6 pb-4">{{ $t('words.amount') }}</th>
                            <th class="px-6 pt-6 pb-4">{{ $t('words.status') }}</th>
                        </tr>
                        <tr
                            v-for="invoice in trainee.invoices"
                            :key="invoice.id"
                            class="border-t hover:bg-gray-100 focus-within:bg-gray-100 text-center"
                        >
                            <td class="px-4 py-4 text-left text-blue-500">
                                <inertia-link :href="route('back.finance.invoices.show', invoice.id)">
                                    {{ invoice.number_formatted }}
                                </inertia-link>
                            </td>

                            <td class="px-4 py-4">
                                {{ invoice.grand_total }}
                            </td>

                            <td class="px-4 py-4">
                                {{ invoice.status_formatted }}
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <jet-section-border></jet-section-border>

        <trainee-audit-container :trainee_id="trainee.id"></trainee-audit-container>
    </app-layout>
</template>

<script>
    import AppLayout from '@/Layouts/AppLayout'
    import JetSectionBorder from '@/Jetstream/SectionBorder'
    import Breadcrumb from "@/Components/Breadcrumb";
    import JetDialogModal from '@/Jetstream/DialogModal'
    import JetInput from '@/Jetstream/Input'
    import JetInputError from '@/Jetstream/InputError'
    import JetActionMessage from '@/Jetstream/ActionMessage';
    import JetButton from '@/Jetstream/Button';
    import JetFormSection from '@/Jetstream/FormSection';
    import JetLabel from '@/Jetstream/Label';
    import CompanyContractsPagination from "@/Components/CompanyContractsPagination";
    import BreadcrumbContainer from "@/Components/BreadcrumbContainer";
    import VueDropzone from 'vue2-dropzone'
    import 'vue2-dropzone/dist/vue2Dropzone.min.css'
    import SelectTraineeGroup from "@/Components/SelectTraineeGroup";
    import EmptySlate from "@/Components/EmptySlate";
    import ValidationErrors from "@/Components/ValidationErrors";
    import NProgress from 'nprogress'
    import TraineeAuditContainer from "@/Components/TraineeAuditContainer";
    import GosiContainer from "@/Components/GosiContainer";
    import ChangeTraineePassword from "@/Components/ChangeTraineePassword";
    import DetectedEnglishNamePopup from "@/Components/DetectedEnglishNamePopup";
    import { Inertia } from '@inertiajs/inertia'


    export default {
        props: [
            'sessions',
            'trainee',
            'cities',
            'marital_statuses',
            'educational_levels',
            'trainee_groups',
            'trainee_group_trainees',
        ],

        components: {
            AppLayout,
            JetSectionBorder,
            Breadcrumb,
            JetDialogModal,
            JetInput,
            JetInputError,
            JetActionMessage,
            JetButton,
            JetFormSection,
            JetLabel,
            CompanyContractsPagination,
            BreadcrumbContainer,
            VueDropzone,
            SelectTraineeGroup,
            ValidationErrors,
            EmptySlate,
            TraineeAuditContainer,
            GosiContainer,
           ChangeTraineePassword,
           DetectedEnglishNamePopup,

        },
        data() {
            return {
                showDetectedEnglishNamePopup: false,
                detectedEnglishName: '',
                new_trainee_group: {
                    name: '',
                    id: '',
                },
                validationErrors: null,
                cancelButton: {
                    text: this.$t('words.cancel'),
                },
                lang: (this.$t('words.edit') == "Edit") ? 'en':'ar',
                editButton: {
                    text: this.$t('words.edit'),
                    editOption: false,
                    inputClass: "mt-1 block w-full bg-gray-200",
                    selectInputClass: "mt-1 block w-full border border-gray-200 bg-gray-200 py-2.5 px-4 pr-8 rounded leading-tight focus:outline-none"
                },
                dropzoneOptionsIdentity: {
                    destroyDropzone: false,
                    url: route('back.trainees.attachments.identity', {trainee_id: this.trainee.id}),
                    dictDefaultMessage: `<ion-icon name='cloud-upload-outline' class='text-red-500' size='large'></ion-icon><br/> ${this.$t('words.upload-files-here')}`,
                    thumbnailWidth: 150,
                    maxFilesize: 20,
                },
                dropzoneOptionsQualification: {
                    destroyDropzone: false,
                    url: route('back.trainees.attachments.qualification', {trainee_id: this.trainee.id}),
                    dictDefaultMessage: `<ion-icon name='cloud-upload-outline' class='text-red-500' size='large'></ion-icon><br/> ${this.$t('words.upload-files-here')}`,
                    thumbnailWidth: 150,
                    maxFilesize: 20,
                },
                dropzoneOptionsBankAccount: {
                    destroyDropzone: false,
                    url: route('back.trainees.attachments.bank-account', {trainee_id: this.trainee.id}),
                    dictDefaultMessage: `<ion-icon name='cloud-upload-outline' class='text-red-500' size='large'></ion-icon><br/> ${this.$t('words.upload-files-here')}`,
                    thumbnailWidth: 150,
                    maxFilesize: 20,
                },
                dropzoneOptionsGosiCertificate: {
                    destroyDropzone: false,
                    url: route('back.trainees.attachments.gosi-certificate', {trainee_id: this.trainee.id}),
                    dictDefaultMessage: `<ion-icon name='cloud-upload-outline' class='text-red-500' size='large'></ion-icon><br/> ${this.$t('words.upload-files-here')}`,
                    thumbnailWidth: 150,
                    maxFilesize: 20,
                },
                dropzoneOptionsQiwaContract: {
                    destroyDropzone: false,
                    url: route('back.trainees.attachments.qiwa-contract', {trainee_id: this.trainee.id}),
                    dictDefaultMessage: `<ion-icon name='cloud-upload-outline' class='text-red-500' size='large'></ion-icon><br/> ${this.$t('words.upload-files-here')}`,
                    thumbnailWidth: 150,
                    maxFilesize: 20,
                },
            }
        },
        computed: {
            canViewSpecialDocuments() {
                const currentUserEmail = this.$page.props.user?.email;
                const allowedUsers = this.$page.props.allowed_users_for_special_documents || [];

                return allowedUsers.includes(currentUserEmail);
            },
            canViewCertificates() {
                // Check if user has permission 'override-training-costs'
                const permissions = document.head.querySelector('meta[name="user-permissions"]');
                const hasPermission = permissions && permissions.content.indexOf('override-training-costs') !== -1;
                
                // Check if user is in the allowed users list (for special documents access)
                const currentUserEmail = this.$page.props.user?.email;
                const allowedUsers = this.$page.props.allowed_users_for_special_documents || [];
                const isInAllowedUsers = allowedUsers.includes(currentUserEmail);
                
                // Check if user has any of the specific role ids
                const user = this.$page.props.user;
                if (!user) return hasPermission || isInAllowedUsers;
                
                const userRoles = user.roles || [];
                const allowedRoleIds = [
                    'c89e8671-9f3a-427a-90ca-bd2443f04df2',
                    'b87b7924-64da-492f-afb1-e54a49f0d800',
                    '097438c1-2614-42db-95f2-6402bb607fdc',
                    '7a9101c7-728f-4653-82f1-e6318359c344' // شؤون متدربات
                ];
                
                // Also check by role name pattern (for roles that contain "services" which is شؤون متدربات)
                const allowedRoleNamePatterns = ['services']; // This matches team_id_services
                
                // Check if user has any of the allowed role ids OR role name patterns
                const hasRole = userRoles.some(role => {
                    if (!role) return false;
                    
                    // Check by role id
                    const roleId = role.id || role.role_id || role.pivot?.role_id;
                    if (roleId && allowedRoleIds.includes(roleId)) {
                        return true;
                    }
                    
                    // Check by role name pattern (contains "services")
                    const roleName = role.name || '';
                    if (allowedRoleNamePatterns.some(pattern => roleName.includes(pattern))) {
                        return true;
                    }
                    
                    return false;
                });
                
                return hasPermission || hasRole || isInAllowedUsers;
            }
        },
        mounted() {
            // if(!this.trainee.trainee_group_object) {
            //     this.trainee.trainee_group_object = this.new_trainee_group;
            // }
        },
        methods: {
            saveDeletedRemark(trainee) {
                NProgress.start()
                axios.put(route('back.trainees.update-deleted-remark', trainee.id), {
                    deleted_remark: trainee.deleted_remark,
                }).then(response => {
                    NProgress.stop();
                }).catch(error => {
                    throw error;
                })
            },
            cancelEdit() {
                this.editButton.editOption = false;
                this.editButton.inputClass = "mt-1 block w-full bg-gray-200";
                this.editButton.selectInputClass =
                    "mt-1 block w-full border border-gray-200 bg-gray-200 py-2.5 px-4 pr-8 rounded leading-tight focus:outline-none";
                this.editButton.text = this.$t('words.edit');
                this.validationErrors = null;
                window.location.reload();
            },
            editTrainee() {
                if (!this.editButton.editOption) {
                    this.editButton.editOption = true;
                    this.editButton.inputClass = "mt-1 block w-full bg-white";
                    this.editButton.selectInputClass =
                        "mt-1 block w-full border border-gray-200 bg-white py-2.5 px-4 pr-8 rounded leading-tight focus:outline-none";
                    this.editButton.text = this.$t('words.save');
                    return;
                }

                let newForm = {
                    trainee_group_name: this.trainee.trainee_group
                        ? this.trainee.trainee_group.name
                        : "",
                    company_id: this.trainee.company_id,
                    name: this.trainee.name,
                    english_name: this.trainee.english_name,
                    email: this.trainee.email,
                    identity_number: this.trainee.identity_number,
                    birthday: this.trainee.birthday,
                    phone: this.trainee.phone,
                    phone_additional: this.trainee.phone_additional,
                    national_address: this.trainee.national_address,
                    educational_level_id: this.trainee.educational_level_id,
                    city_id: this.trainee.city_id,
                    marital_status_id: this.trainee.marital_status_id,
                    children_count: this.trainee.children_count,
                };

                this.$wait.start("UPDATING_TRAINEE");
                this.validationErrors = null;
                axios
                    .put(route("back.trainees.update", this.trainee.id), newForm)
                    .then(() => {
                        Inertia.reload().then(() => {
                            this.editButton.editOption = false;
                            this.editButton.inputClass = "mt-1 block w-full bg-gray-200";
                            this.editButton.selectInputClass =
                                "mt-1 block w-full border border-gray-200 bg-gray-200 py-2.5 px-4 pr-8 rounded leading-tight focus:outline-none";
                            this.editButton.text = this.$t("words.edit");
                            this.$wait.end("UPDATING_TRAINEE");
                        });
                    })
                    .catch((error) => {
                        this.$wait.end("UPDATING_TRAINEE");
                        if (error.response && error.response.status == 422) {
                            this.validationErrors = error.response.data.errors;
                        }
                    });
            },
            sendingCsrf(file, xhr, formData) {
                xhr.setRequestHeader('X-CSRF-TOKEN', window.token ? window.token.content : '');
            },
            onIdentityUploadSuccess(file, response) {
                let detectedName = null;

                try {
                    const payload = typeof response === 'string' ? JSON.parse(response) : response;
                    detectedName = payload?.detected_english_name || null;
                } catch (e) {
                    detectedName = null;
                }

                Inertia.reload({ only: ['trainee'] }).then(() => {
                    if (detectedName) {
                        this.detectedEnglishName = detectedName;
                        this.showDetectedEnglishNamePopup = true;
                    }
                });
            },
            onDetectedEnglishNameConfirmed(englishName) {
                this.showDetectedEnglishNamePopup = false;
                this.detectedEnglishName = '';
                this.trainee.english_name = englishName;
                Inertia.reload({ only: ['trainee'] });
            },
            onDetectedEnglishNameDeclined() {
                this.showDetectedEnglishNamePopup = false;
                this.detectedEnglishName = '';
            },
            deleteIdentity() {
                if (confirm(this.$t('words.are-you-sure'))) {
                    this.$inertia.delete(route('back.trainees.attachments.identity.destroy', {trainee_id: this.trainee.id}));
                }
            },
            deleteQualification() {
                if (confirm(this.$t('words.are-you-sure'))) {
                    this.$inertia.delete(route('back.trainees.attachments.qualification.destroy', {trainee_id: this.trainee.id}));
                }
            },
            deleteBankAccount() {
                if (confirm(this.$t('words.are-you-sure'))) {
                    this.$inertia.delete(route('back.trainees.attachments.bank-account.destroy', {trainee_id: this.trainee.id}));
                }
            },
            deleteGosiCertificate() {
                if (confirm(this.$t('words.are-you-sure'))) {
                    this.$inertia.delete(route('back.trainees.attachments.gosi-certificate.destroy', {trainee_id: this.trainee.id}));
                }
            },
            deleteQiwaContract() {
                if (confirm(this.$t('words.are-you-sure'))) {
                    this.$inertia.delete(route('back.trainees.attachments.qiwa-contract.destroy', {trainee_id: this.trainee.id}));
                }
            },
            unblock() {
                if (confirm(this.$t('words.are-you-sure') + ' ' + this.trainee.deleted_remark)) {
                    this.$inertia.post(route('back.trainees.unblock', {trainee_id: this.trainee.id}));
                }
            },
            navigateToCreateCertificate() {
                this.$inertia.visit(route('back.trainees.custom-certificates.create', this.trainee.id));
            },
        }
    }
</script>

<style>
    .min-container-upload {
        min-height: 168px;
    }
    .vue-dropzone {
        pointer-events: none;
    }
</style>
