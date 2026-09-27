<template>
    <app-layout>
        <div class="container px-6 mx-auto grid pt-6">
            <breadcrumb-container
                :crumbs="[
                    {title: 'dashboard', link: route('dashboard')},
                    {title: 'trainees', link: route('back.trainees.index')},
                    {title_raw: trainee.name, link: trainee.show_url},
                    {title_raw: $t('words.fixed-training-costs')}
                ]"
            ></breadcrumb-container>

            <div class="grid grid-cols-12 gap-6">
                <div class="col-span-6">
                    <div class="bg-white p-4">
                        <p><b>{{ $t('words.fixed-training-costs') }}</b></p>
                        <p>{{ $t('words.here-you-can-override-training-cost-for-the-trainee') }}</p>

                        <form class="mt-10" @submit.prevent="submit">
                            <div class="col-span-4 sm:col-span-4">
                                <jet-label for="monthly-costs" :value="$t('words.fixed-training-costs')" />
                                <jet-input id="monthly-costs" type="number" class="mt-2 block w-full" v-model="form.override_training_costs" autocomplete="off" autofocus />
                                <jet-input-error :message="form.error('monthly-costs')" class="mt-2" />
                            </div>
                            <div class="col-span-4 sm:col-span-4 mt-4">
                                <jet-label for="platform-reward" :value="$t('words.platform-reward')" />
                                <jet-input id="platform-reward" type="number" class="mt-2 block w-full" v-model="form.platform_reward" autocomplete="off" />
                                <p class="text-sm text-gray-500 mt-1">{{ $t('words.here-you-can-set-platform-reward') }}</p>
                                <jet-input-error :message="form.error('platform_reward')" class="mt-2" />
                            </div>
                            <div class="col-span-4 sm:col-span-4 mt-4">
                                <jet-label for="monthly-costs" :value="$t('words.ignore-attendance-warnings-and-emails')" />

                                <div class="relative mt-2">
                                    <select class="bg-white block appearance-none w-full border border-gray-200 text-gray-700 py-3 px-4 pr-8 rounded leading-tight focus:outline-none focus:bg-white focus:border-gray-500"
                                            v-model="form.ignore_attendance"
                                            required>
                                        <option :value="false">{{ $t('words.no') }}</option>
                                        <option :value="true">{{ $t('words.yes') }}</option>
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                                        <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                                    </div>
                                </div>

                                <jet-input-error :message="form.error('monthly-costs')" class="mt-2" />
                            </div>
                            <div class="col-span-4 sm:col-span-4 mt-4">
                                <jet-label for="monthly-costs" :value="$t('words.do-not-edit-without-permission-notice')" />
                                <div class="relative mt-2">
                                    <select class="bg-white block appearance-none w-full border border-gray-200 text-gray-700 py-3 px-4 pr-8 rounded leading-tight focus:outline-none focus:bg-white focus:border-gray-500"
                                            v-model="form.dont_edit_notice"
                                            required>
                                        <option :value="false">{{ $t('words.no') }}</option>
                                        <option :value="true">{{ $t('words.yes') }}</option>
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                                        <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                                    </div>
                                </div>
                                <jet-input-error :message="form.error('monthly-costs')" class="mt-2" />
                            </div>

                            <div class="col-span-4 sm:col-span-4 mt-4 flex gap-5">
                                <inertia-link :href="trainee.show_url" class="mt-1 btn btn-text">{{ $t('words.cancel') }}</inertia-link>
                                <button type="submit" class="btn btn-primary">{{ $t('words.save') }}</button>
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
import CompanyContractsPagination from "@/Components/CompanyContractsPagination";
import BreadcrumbContainer from "@/Components/BreadcrumbContainer";
import VueDropzone from 'vue2-dropzone'
import 'vue2-dropzone/dist/vue2Dropzone.min.css'
import SelectTraineeGroup from "@/Components/SelectTraineeGroup";
import ChangeTraineePassword from '@/Components/ChangeTraineePassword';
import AttendanceSheetManagementForTrainee from "@/Components/AttendanceSheetManagementForTrainee";
import 'selectize/dist/js/standalone/selectize.min';
import EmptySlate from "@/Components/EmptySlate";
import TraineeAuditContainer from "@/Components/TraineeAuditContainer";
import {Inertia} from "@inertiajs/inertia";
import ValidationErrors from "@/Components/ValidationErrors";

export default {
    props: [
        'trainee',
    ],
    components: {
        ValidationErrors,
        TraineeAuditContainer,
        AppLayout,
        AttendanceSheetManagementForTrainee,
        Breadcrumb,
        BreadcrumbContainer,
        ChangeTraineePassword,
        CompanyContractsPagination,
        JetActionMessage,
        JetButton,
        JetDialogModal,
        JetFormSection,
        JetInput,
        JetInputError,
        JetLabel,
        JetSectionBorder,
        SelectTraineeGroup,
        VueDropzone,
        EmptySlate,
    },
    data() {
        return {
            form: this.$inertia.form({
                override_training_costs: null,
                platform_reward: null,
                ignore_attendance: false,
                dont_edit_notice: false,
            }, {
                bag: 'updateTraineeCosts',
            }),
        }
    },
    mounted() {
        this.form.override_training_costs = this.trainee.override_training_costs;
        this.form.platform_reward = this.trainee.platform_reward;
        this.form.ignore_attendance = this.trainee.ignore_attendance;
        this.form.dont_edit_notice = this.trainee.dont_edit_notice;
    },
    methods: {
        submit() {
            this.form.put(route('back.trainees.fixed-training-costs.update', this.trainee.id));
        },
    },
}
</script>

