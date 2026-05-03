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
                    {title: 'settings', link: route('back.settings')},
                ]"
            ></breadcrumb-container>

            <div class="grid md:grid-cols-4 grid-cols-1 gap-6">
                <inertia-link v-can="'view-permissions'" :href="route('back.settings.roles.index')" class="col-span-1 bg-white shadow-lg rounded-lg p-5 transition-all duration-500 ease-in-out hover:bg-gray-200 text-center">
                    {{ $t('words.roles-settings') }}
                </inertia-link>

                <inertia-link v-can="'edit-survey-links'" :href="route('back.settings.survey-links.index')" class="col-span-1 bg-white shadow-lg rounded-lg p-5 transition-all duration-500 ease-in-out hover:bg-gray-200 text-center">
                    {{ $t('words.edit-survey-links') }}
                </inertia-link>

                <inertia-link :href="route('back.settings.trainees-application')" class="col-span-1 bg-white shadow-lg rounded-lg p-5 transition-all duration-500 ease-in-out hover:bg-gray-200 text-center">
                    {{ $t('words.trainee-application-settings') }}
                </inertia-link>

                <inertia-link v-can="'disable-website'" :href="route('back.settings.disable-website.index')" class="col-span-1 bg-white shadow-lg rounded-lg p-5 transition-all duration-500 ease-in-out hover:bg-gray-200 text-center">
                    {{ $t('words.disable-website') }}
                </inertia-link>

                <inertia-link :href="route('back.settings.complaints.index')"
                              v-can="'edit-complaint-settings'"
                              class="col-span-1 bg-white shadow-lg rounded-lg p-5 transition-all duration-500 ease-in-out hover:bg-gray-200 text-center">
                    {{ $t('words.complaints-settings') }}
                </inertia-link>

                <inertia-link :href="route('back.settings.resignation.index')"
                              class="col-span-1 bg-white shadow-lg rounded-lg p-5 transition-all duration-500 ease-in-out hover:bg-gray-200 text-center">
                    {{ $t('words.resignation-email-settings') }}
                </inertia-link>

                <inertia-link :href="route('back.settings.payment.index')"
                              v-can="'edit-payment-settings'"
                              class="col-span-1 bg-white shadow-lg rounded-lg p-5 transition-all duration-500 ease-in-out hover:bg-gray-200 text-center">
                    {{ $t('words.edit-payment-settings') }}
                </inertia-link>

                <inertia-link :href="route('back.settings.global-messages.index')"
                              class="col-span-1 bg-white shadow-lg rounded-lg p-5 transition-all duration-500 ease-in-out hover:bg-gray-200 text-center">
                    {{ $t('words.global-messages') }}
                </inertia-link>


                <inertia-link :href="route('back.settings.recruitment-companies.index')"
                              class="col-span-1 bg-white shadow-lg rounded-lg p-5 transition-all duration-500 ease-in-out hover:bg-gray-200 text-center">
                    {{ $t('words.recruitment-companies') }}
                </inertia-link>

                <inertia-link v-can="'manage-recorded-courses'" :href="route('back.settings.recorded-courses.index')"
                              class="col-span-1 bg-white shadow-lg rounded-lg p-5 transition-all duration-500 ease-in-out hover:bg-gray-200 text-center">
                    {{ $t('words.manage-recorded-courses') }}
                </inertia-link>

                <inertia-link :href="route('back.settings.app.index')"
                              class="col-span-1 bg-white shadow-lg rounded-lg p-5 transition-all duration-500 ease-in-out hover:bg-gray-200 text-center">
                    {{ $t('words.system') }}
                </inertia-link>

            </div>
        </div>
    </app-layout>
</template>

<script>
    import AppLayout from '@/Layouts/AppLayout'
    import IconNavigate from 'vue-ionicons/dist/ios-arrow-dropright'
    import BreadcrumbContainer from "@/Components/BreadcrumbContainer";

    export default {
        metaInfo: { title: 'Settings' },
        // layout: Layout,
        components: {
            IconNavigate,
            AppLayout,
            BreadcrumbContainer,
        },
        props: {
            companies: Object,
            filters: Object,
        },
        data() {
            return {
                //
            }
        },
        methods: {
        },
    }
</script>
