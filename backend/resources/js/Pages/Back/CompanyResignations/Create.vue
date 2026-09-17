<style lang="css">
.search-results-box {
    position: absolute;
    width: 600px;
    margin-top: -10px;
}
</style>
<template>
    <app-layout>
        <div class="container px-6 mx-auto grid">
            <div class="mt-20" v-if="company">
                <div class="md:grid md:grid-cols-3 md:gap-6">
                    <div class="md:col-span-1">
                        <div class="px-4 sm:px-0">
                            <h3 class="text-lg font-medium text-gray-900">
                                {{ company.name_ar }}<br/>
                                {{ company.name_en }}
                            </h3>
                            <p class="mt-1 text-sm text-gray-600">
                                {{ $t('words.resignations') }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 md:mt-0 md:col-span-2">
                        <form @submit.prevent="submitForm" enctype="multipart/form-data">
                            <div class="shadow overflow-hidden sm:rounded-md">
                                <div class="px-4 py-5 bg-white sm:p-6">
                                    <div class="grid bg-yellow-200 rounded p-2">
                                        <p>بعد حفظ الطلب، سوف تتلقى إشعار عبر البريد الإلكتروني فور الانتهاء من إجراءات عمل الاستقالة.</p>
                                    </div>

                                    <div class="mt-2">
                                        <div class="col-span-4 sm:col-span-4">
                                            <jet-label for="notes" :value="$t('words.date')" />
                                            <jet-input type="date" class="mt-1 block w-full" v-model="form.date" required/>
                                        </div>
                                    </div>

                                    <div class="mt-2">
                                        <div class="col-span-4 sm:col-span-4">
                                            <jet-label for="resignation_date" :value="'تاريخ الاستقالة'" />
                                            <jet-input type="date" class="mt-1 block w-full" v-model="form.resignation_date" required/>
                                        </div>
                                    </div>


                                    <div class="mt-2">
                                        <div class="col-span-4 sm:col-span-4">
                                            <jet-label for="reason" :value="$t('words.reason')" />
                                            <div class="relative mt-2">
                                                <select class="mt-1 block w-full bg-gray-100 appearance-none border border-gray-300 text-gray-700 py-3 px-4 pr-8 rounded leading-tight focus:outline-none focus:border-gray-500"
                                                        v-model="form.reason"
                                                        id="reason"
                                                        required>
                                                    <option disabled value="">{{ $t('words.please-select') }}</option>
                                                    <option value="عدم سداد">عدم سداد</option>
                                                    <option value="استبعاد من الشركة">استبعاد من الشركة</option>
                                                    <option value="استقالة">استقالة</option>
                                                    <option value="انسحاب">انسحاب</option>
                                                    <option value="عمل مشاكل">عمل مشاكل</option>
                                                    <option value="غير مسجلة في الشركة">غير مسجلة في الشركة</option>
                                                    <option value="عدم الالتزام في شروط المعهد">عدم الالتزام في شروط المعهد</option>
                                                    <option value="استبعاد لعدم الحضور">استبعاد لعدم الحضور</option>
                                                    <option value="عدم التقييد بشرح المعهد">عدم التقييد بشرح المعهد</option>
                                                    <option value="لديها سجل التجاري">لديها سجل التجاري</option>
                                                    <option value="مستنفذة للدعم">مستنفذة للدعم</option>
                                                    <option value="عدم سداد مستحق مالي / غير نشط في التأمينات">عدم سداد مستحق مالي / غير نشط في التأمينات</option>
                                                    <option value="رفضت التوقيع على الاعتراض">رفضت التوقيع على الاعتراض</option>
                                                    <option value="حذف من قبل التأمينات">حذف من قبل التأمينات</option>
                                                    <option value="قائمة سوداء">قائمة سوداء</option>
                                                    <option value="المباشرة في مقر الشركة">المباشرة في مقر الشركة</option>
                                                </select>
                                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                                                    <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                                                </div>
                                            </div>
                                            <jet-input-error :message="form.error('reason')" class="mt-2" />
                                        </div>
                                    </div>

                                    <div class="mt-4 space-y-4">
                                        <email-list-input
                                            v-model="form.emails_to"
                                            :label="$t('words.to')"
                                            required
                                        />
                                        <email-list-input
                                            v-model="form.emails_cc"
                                            :label="$t('words.cc')"
                                        />
                                        <email-list-input
                                            v-model="form.emails_bcc"
                                            :label="$t('words.bcc')"
                                        />
                                    </div>

                                    <div class="mt-5">
                                        <jet-input
                                            type="text"
                                            class="mt-1 block w-full"
                                            :placeholder="$t('words.search')"
                                            autocomplete="off"
                                            v-model="searchString"
                                            @input="triggerSearching()"
                                        />
                                        <div v-if="searchBoxVisible"
                                             ref="searchResultsRef"
                                             id="searchResultsRef"
                                             class="search-results-box bg-white shadow-lg mt-1 p-3 overflow-y-auto">
                                            <div class="flex justify-between">
                                                <div class="text-gray-500 cursor-pointer hover:text-black" @click="searchBoxVisible=false;searchString=''">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </div>
                                                <p class="text-gray-500">{{ $t('words.results') }}</p>
                                            </div>

                                            <div v-if="searchResults.length === 0"
                                                 style="min-height:200px;"
                                                 class="flex justify-center align-middle align-items-stretch h-full flex-col">
                                                <div class="text-center">
                                                    <svg class="mx-auto" style="fill:#a9a8a8;" width="64px" viewBox="0 0 512.00037 512" xmlns="http://www.w3.org/2000/svg"><g fill-rule="evenodd"><path d="m248.953125 61.273438c-5.367187-1.238282-10.722656 2.101562-11.964844 7.46875-1.242187 5.367187 2.105469 10.726562 7.472657 11.96875 24.8125 5.734374 47.492187 18.332031 65.582031 36.421874 53.183593 53.183594 53.183593 139.726563 0 192.910157-53.183594 53.1875-139.726563 53.1875-192.910157 0-53.1875-53.183594-53.1875-139.726563 0-192.910157 15.269532-15.269531 33.339844-26.40625 53.710938-33.109374 5.230469-1.71875 8.078125-7.355469 6.359375-12.589844-1.722656-5.234375-7.363281-8.082032-12.59375-6.359375-23.367187 7.683593-44.085937 20.453125-61.582031 37.953125-60.964844 60.964844-60.964844 160.160156 0 221.125 30.480468 30.480468 70.519531 45.722656 110.5625 45.722656 40.039062-.003906 80.078125-15.242188 110.5625-45.722656 60.960937-60.964844 60.960937-160.160156 0-221.125-20.738282-20.734375-46.738282-35.175782-75.199219-41.753906zm0 0"/><path d="m498.414062 432.707031-104.53125-104.53125c53.601563-84.054687 41.863282-194.484375-29.265624-265.617187-40.339844-40.339844-93.976563-62.558594-151.027344-62.558594-57.054688 0-110.691406 22.21875-151.03125 62.558594-40.34375 40.339844-62.558594 93.976562-62.558594 151.03125 0 57.050781 22.214844 110.6875 62.558594 151.027344 40.339844 40.339843 93.972656 62.554687 151.023437 62.554687 40.945313 0 80.386719-11.484375 114.59375-33.289063l104.53125 104.53125c8.746094 8.75 20.414063 13.566407 32.855469 13.566407 12.4375 0 24.105469-4.816407 32.855469-13.566407 18.109375-18.117187 18.109375-47.589843-.003907-65.707031zm-14.105468 51.601563c-4.980469 4.976562-11.636719 7.71875-18.746094 7.71875-7.113281 0-13.769531-2.742188-18.75-7.71875l-110.304688-110.304688c-1.929687-1.933594-4.484374-2.921875-7.054687-2.921875-1.976563 0-3.960937.582031-5.683594 1.777344-32.410156 22.480469-70.515625 34.363281-110.1875 34.363281-51.722656 0-100.347656-20.140625-136.917969-56.710937-75.5-75.5-75.5-198.347657 0-273.847657 36.574219-36.574218 85.199219-56.714843 136.925782-56.714843 51.722656 0 100.347656 20.140625 136.921875 56.714843 66.28125 66.285157 75.683593 170.207032 22.347656 247.105469-2.75 3.964844-2.269531 9.324219 1.144531 12.738281l110.304688 110.304688c10.335937 10.335938 10.335937 27.15625 0 37.496094zm0 0"/><path d="m273.804688 153.371094c-3.894532-3.894532-10.207032-3.894532-14.105469 0l-46.109375 46.109375-46.113282-46.109375c-3.894531-3.894532-10.210937-3.894532-14.105468 0-3.894532 3.894531-3.894532 10.210937 0 14.105468l46.109375 46.113282-46.109375 46.109375c-3.894532 3.894531-3.894532 10.210937 0 14.105469 1.945312 1.949218 4.5 2.921874 7.050781 2.921874 2.554687 0 5.105469-.972656 7.054687-2.921874l46.109376-46.109376 46.109374 46.109376c1.949219 1.949218 4.503907 2.921874 7.054688 2.921874 2.554688 0 5.105469-.972656 7.054688-2.921874 3.894531-3.894532 3.894531-10.210938 0-14.105469l-46.113282-46.109375 46.113282-46.113282c3.894531-3.894531 3.894531-10.210937 0-14.105468zm0 0"/><path d="m206.976562 77.328125c5.492188 0 9.972657-4.480469 9.972657-9.976563 0-5.492187-4.480469-9.972656-9.972657-9.972656-5.496093 0-9.976562 4.480469-9.976562 9.972656 0 5.496094 4.480469 9.976563 9.976562 9.976563zm0 0"/></g></svg>
                                                    <p class="inline-block text-xl p-1 text-white bg-black p-1 px-5 mt-5">{{ $t('words.no-records-have-been-found') }}</p>
                                                </div>
                                            </div>
                                            <div v-else class="search-results-container mt-5">
                                                <div v-for="(record, index) in searchResults"
                                                     :key="index"
                                                     tabindex="-1"
                                                     class="search-result-row p-1 px-5 rounded-lg block"
                                                     :class="{'hover:bg-gray-100': searchResults.length}"
                                                >
                                                    <div class="text-black font-bold flex justify-between text-sm">
                                                        <div style="min-width:150px;">
                                                            <span v-if="record.deleted_at" class="text-xs inline-block p-1 px-2 bg-red-600 text-white rounded-sm">{{ $t('words.blocked') }}</span>
                                                            <Skeleton>{{ record.resource_label }}</Skeleton>
                                                            <br/>
                                                            <Skeleton><span v-if="record.identity_number" class="text-gray-500 font-light">{{ $t('words.identity_number') }}: {{ record.identity_number }}</span></Skeleton>
                                                        </div>
                                                        <div class="flex gap-5 my-2" v-if="record.show_url">
                                                            <a class="border flex gap-2 p-1 rounded text-sm hover:bg-blue-500" :href="record.show_url ? record.show_url : '/'" target="_blank">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                                </svg>
                                                                {{ $t('words.view') }}
                                                            </a>
                                                            <div class="border flex gap-2 p-1 rounded text-sm hover:bg-blue-500 cursor-pointer" @click="addToTrainees(record)">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3   w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                                                </svg>
                                                                {{ $t('words.add') }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <hr>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-2 grid grid-cols-1 gap-4">
                                        <table class="table-auto w-full text-xs table-5">
                                            <colgroup>
                                                <col style="width:10px;">
                                                <col style="width:200px;">
                                                <col style="width:100px;">
                                            </colgroup>
                                            <thead>
                                            <tr>
                                                <th class="text-center border-black border bg-gray-300 px-2">#</th>
                                                <th class="text-right border-black border bg-gray-300 px-2">{{ $t('words.name') }}</th>
                                                <th class="text-right border-black border bg-gray-300 px-2">{{ $t('words.identity_number') }}</th>
                                                <th class="text-right border-black border bg-gray-300 px-2">{{ $t('words.current-company-as-of-today') }}</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <tr v-for="(trainee, key) in form.trainees" :key="trainee.id">
                                                <td class="text-center border-black border px-2">{{ ++key }}</td>
                                                <td class="border-black border px-2">{{ trainee.name }}</td>
                                                <td class="border-black border px-2">{{ trainee.identity_number }}</td>
                                                <td class="border-black border px-2"><span v-if="trainee.company">{{ trainee.company.name_ar }}</span></td>
                                                <td class="text-center border-black border px-2">
                                                    <button @click="removeFromTrainees(trainee)" class="bg-red-500 text-white px-2 py-1 rounded">
                                                        {{ $t('words.delete') }}
                                                    </button>
                                                </td>
                                            </tr>
                                            <tr v-if="!form.trainees.length">
                                                <td class="text-center border-black border px-2"
                                                    colspan="4">
                                                    {{ $t('words.no-records-have-been-found') }}
                                                </td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    </div>


                                    <div class="grid grid-cols-4 gap-6">
                                        <div class="col-span-4 sm:col-span-4">
                                            <!--<jet-label for="notes" :value="$t('words.notes')" />-->
                                            <!--<jet-textarea id="notes" type="textarea" class="mt-1 block w-full" />-->
                                        </div>

                                        <div class="flex items-center justify-start px-4 py-3 text-right">
                                            <inertia-link :href="`/back/companies/${company.id}`" class="flex items-center justify-start rtl:ml-4 ltr:mr-4 rounded-md px-4 py-2 bg-white hover:bg-gray-300 text-right">
                                                {{ $t('words.cancel') }}
                                            </inertia-link>

                                            <jet-button :class="{ 'opacity-25': $wait.is('SAVING_CONTRACT') }" :disabled="$wait.is('SAVING_CONTRACT')">
                                                {{ $t('words.save') }}
                                            </jet-button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
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
import JetTextarea from '@/Jetstream/Textarea'
import VueDropzone from 'vue2-dropzone'
import 'vue2-dropzone/dist/vue2Dropzone.min.css'
import _ from "lodash";
import { Skeleton } from 'vue-loading-skeleton';
import EmailListInput from '@/Components/EmailListInput';

export default {
    props: ['sessions', 'company', 'default_cc_emails', 'default_bcc_emails'],

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
        JetTextarea,
        VueDropzone,
        Skeleton,
        EmailListInput,
    },
    data() {
        return {
            form: this.$inertia.form({
                company_id: null,
                trainees: [],
                date: new Date().toISOString().substring(0, 10),
                resignation_date: new Date().toISOString().substring(0, 10),
                reason: '',
                emails_to: '',
                emails_cc: '',
                emails_bcc: '',
            }),
            searchString: '',
            searchResults: [],
            searchBoxVisible: false,
        }
    },
    mounted() {
        if (this.company.id) {
            this.form.company_id = this.company.id;

            const lastResignation = this.company.resignations[0] || null;

            // Copy To/CC/BCC from the previous resignation. On the first one, seed CC/BCC from settings.
            const emailsTo = this.parseEmails(lastResignation ? lastResignation.emails_to : '');
            const emailsCc = this.parseEmails(lastResignation ? lastResignation.emails_cc : this.default_cc_emails);
            const emailsBcc = this.parseEmails(lastResignation ? lastResignation.emails_bcc : this.default_bcc_emails);

            const alreadyIncluded = new Set(
                [...emailsTo, ...emailsCc, ...emailsBcc].map((email) => email.toLowerCase())
            );

            // Join any company / salesperson emails that are not already in To, CC, or BCC
            this.parseEmails(this.mergeEmailStrings(this.company.email, this.company.salesperson_email))
                .forEach((email) => {
                    if (!alreadyIncluded.has(email.toLowerCase())) {
                        emailsTo.push(email);
                        alreadyIncluded.add(email.toLowerCase());
                    }
                });

            this.form.emails_to = emailsTo.join(', ');
            this.form.emails_cc = emailsCc.join(', ');
            this.form.emails_bcc = emailsBcc.join(', ');
        }
    },
    methods: {
        normalizeEmailString(value) {
            if (Array.isArray(value)) {
                return value.map((email) => String(email || '').trim()).filter(Boolean).join(', ');
            }

            if (!value) {
                return '';
            }

            return String(value)
                .split(/[,;\n]+/)
                .map((email) => email.trim())
                .filter(Boolean)
                .join(', ');
        },
        parseEmails(value) {
            const seen = new Set();
            const emails = [];

            this.normalizeEmailString(value)
                .split(', ')
                .filter(Boolean)
                .forEach((email) => {
                    const key = email.toLowerCase();
                    if (!seen.has(key)) {
                        seen.add(key);
                        emails.push(email);
                    }
                });

            return emails;
        },
        mergeEmailStrings(...values) {
            return this.parseEmails(values.join(', ')).join(', ');
        },
        triggerSearching() {
            if (this.searchString) {
                this.searchBoxVisible = true;
                this.loadSearchResultsBox();
            } else {
                // this.userFinishedWithResults();
                this.searchBoxVisible = false;
            }
        },
        loadSearchResultsBox: _.debounce(function() {
            if (this.searchString) {
                this.searchResults = 3;
                axios.get('/back/search', {
                    params: {
                        search: this.searchString,
                        trainees: true,
                    }
                })
                    .then(response => {
                        this.searchResults = response.data;
                    })
            }
        }, 320),
        addToTrainees(trainee) {
            this.searchBoxVisible = false;
            this.searchString = '';
            if (! this.form.trainees.find(t => t.id === trainee.id)) {

                if (trainee.company_id != this.company.id) {
                    alert(this.$t('words.trainee-must-be-in-the-same-company'));
                    return;
                }

                this.form.trainees.push(trainee);
            }
        },

        removeFromTrainees(trainee) {
            this.form.trainees = this.form.trainees.filter(t => t.id !== trainee.id);
        },


        submitForm() {
            if (this.form.trainees.length === 0) {
                alert(this.$t('words.please-select'));
                return;
            }

            if (!this.form.reason) {
                alert(this.$t('words.resignation-reason-required'));
                return;
            }

            if (!this.normalizeEmailString(this.form.emails_to)) {
                alert(this.$t('words.please-add-at-least-one-to-email'));
                return;
            }

            this.form.emails_to = this.normalizeEmailString(this.form.emails_to);
            this.form.emails_cc = this.normalizeEmailString(this.form.emails_cc);
            this.form.emails_bcc = this.normalizeEmailString(this.form.emails_bcc);

            if(confirm(this.$t('words.are-you-sure'))) {
                this.form.post(route('back.resignations.store', {company_id: this.company.id}), {
                    preserveScroll: true
                }).catch(error => {
                    this.form.processing = false;
                }).finally(() => {
                    this.form.processing = false;
                });
            }
        },
    }
}
</script>
