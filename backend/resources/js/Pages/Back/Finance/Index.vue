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
                ]"
            ></breadcrumb-container>

            <div class="grid md:grid-cols-4 grid-cols-1 gap-6">
                <inertia-link :href="route('back.finance.invoices.index')"
                              class="col-span-1 bg-white shadow-lg rounded-lg p-5 transition-all duration-500 ease-in-out hover:bg-gray-200 text-center">
                    {{ $t('words.invoices') }}
                </inertia-link>

                <inertia-link :href="route('back.finance.invoices.excel')" class="col-span-1 bg-white shadow-lg rounded-lg p-5 transition-all duration-500 ease-in-out hover:bg-gray-200 text-center">
                    {{ $t('words.invoices-report') }}
                </inertia-link>

                <inertia-link v-can="'view-financial-invoices-details'"
                              :href="route('back.finance.invoices.details')"
                              class="col-span-1 bg-white shadow-lg rounded-lg p-5 transition-all duration-500 ease-in-out hover:bg-gray-200 text-center">
                    {{ $t('words.invoices-details-report') }}
                </inertia-link>


                <inertia-link :href="route('back.finance.account-statements')"
                              class="col-span-1 bg-white shadow-lg rounded-lg p-5 transition-all duration-500 ease-in-out hover:bg-gray-200 text-center">
                    {{ $t('words.account-statements') }}
                </inertia-link>

                <finance-whats-app-chat v-can="'access-whatsapp-chats'" />
            </div>
        </div>
    </app-layout>
</template>

<script>
    // import Icon from '@/Shared/Icon'
    // import Layout from '@/Shared/Layout'
    import mapValues from 'lodash/mapValues'
    import Pagination from '@/Shared/Pagination'
    import pickBy from 'lodash/pickBy'
    // import SearchFilter from '@/Shared/SearchFilter'
    import throttle from 'lodash/throttle'
    import AppLayout from '@/Layouts/AppLayout'
    import IconNavigate from 'vue-ionicons/dist/ios-arrow-dropright'
    import BreadcrumbContainer from "@/Components/BreadcrumbContainer";
    import FinanceWhatsAppChat from "@/Components/FinanceWhatsAppChat";

    export default {
        metaInfo: { title: 'Finance' },
        // layout: Layout,
        components: {
            IconNavigate,
            AppLayout,
            // Icon,
            Pagination,
            // SearchFilter,
            BreadcrumbContainer,
            FinanceWhatsAppChat,
        },
        props: {
            companies: Object,
            filters: Object,
        },
        data() {
            return {
                form: {
                    // search: this.filters.search,
                    // trashed: this.filters.trashed,
                },
            }
        },
        watch: {
            form: {
                handler: throttle(function() {
                    let query = pickBy(this.form)
                    this.$inertia.replace(this.route('companies', Object.keys(query).length ? query : { remember: 'forget' }))
                }, 150),
                deep: true,
            },
        },
        methods: {
            reset() {
                this.form = mapValues(this.form, () => null)
            },
        },
    }
</script>
