<template>
    <ul class="mt-6">

        <li class="relative px-6 py-3">
            <span class="inline-flex items-center w-full text-sm font-semibold text-gray-800 transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200 dark:text-gray-100" aria-hidden="true"></span>
            <a href="https://jisr-ksa.com" class="inline-flex items-center w-full text-sm text-gray-800 transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200 dark:text-gray-100">
                <EarthIcon w="20px" h="20px" class="w-5 h-5" />
                <span class="ltr:ml-4 rtl:mr-4 hover:text-red-600 hover:font-bold">{{ $t('words.main') }}</span>
            </a>
        </li>

        <sidebar-link link-value="/dashboard" :active="$page.currentRouteName == 'dashboard'">
            <template #icon><DesktopIcon w="20px" h="20px" class="w-5 h-5" /></template>
            <template #title>
                <span class="ltr:ml-4 rtl:mr-4 hover:text-red-600 hover:font-bold">{{ $t('words.dashboard') }}</span>
            </template>
        </sidebar-link>

        <sidebar-link :link-value="route('back.companies.index')" :active="$page.currentRouteName == 'back.companies.index'">
            <template #icon>
                <ion-icon name="business-outline" class="w-5 h-5"></ion-icon>
            </template>
            <template #title>
                <span class="ltr:ml-4 rtl:mr-4 hover:text-red-600 hover:font-bold">{{ $t('words.companies') }}</span>
            </template>
        </sidebar-link>

        <sidebar-link :link-value="route('back.instructors.index')">
            <template #icon>
                <img src="/img/teacher.svg" alt="" class="w-5 h-5">
            </template>
            <template #title>
                <span class="ltr:ml-4 rtl:mr-4 hover:text-red-600 hover:font-bold">{{ $t('words.instructors') }}</span>
            </template>
        </sidebar-link>

        <sidebar-link :link-value="route('back.trainees.index')" :active="$page.currentRouteName == 'back.trainees.index'">
            <template #icon>
                <img src="/img/student.svg" alt="" class="w-5 h-5">
            </template>
            <template #title>
                <span class="ltr:ml-4 rtl:mr-4 hover:text-red-600 hover:font-bold">{{ $t('words.trainees') }}</span>
            </template>
        </sidebar-link>

        <sidebar-link :link-value="route('back.trainees.engineers.index')" :active="$page.currentRouteName == 'back.trainees.engineers.index'">
            <template #icon>
                <img src="/img/student.svg" alt="" class="w-5 h-5">
            </template>
            <template #title>
                <span class="ltr:ml-4 rtl:mr-4 hover:text-red-600 hover:font-bold">{{ $t('words.engineers') }}</span>
            </template>
        </sidebar-link>


        <sidebar-link :link-value="route('back.candidates.index')" :active="$page.currentRouteName == 'back.candidates.index'">
            <template #icon>
                <img src="/img/student.svg" alt="" class="w-5 h-5">
            </template>
            <template #title>
                <span class="ltr:ml-4 rtl:mr-4 hover:text-red-600 hover:font-bold">{{ $t('words.candidates') }}</span>
            </template>
        </sidebar-link>

        <sidebar-link :link-value="route('back.courses.index')">
            <template #icon>
                <img src="/img/book.svg" alt="" class="w-5 h-5">
            </template>
            <template #title>
                <span class="ltr:ml-4 rtl:mr-4 hover:text-red-600 hover:font-bold">{{ $t('words.courses') }}</span>
            </template>
        </sidebar-link>

        <sidebar-link v-can="'view-backoffice-reports'" :link-value="route('back.finance')" :active="$page.currentRouteName == 'back.finance'">
            <template #icon>
                <img src="/img/bank.svg" alt="" class="w-5 h-5">
            </template>
            <template #title>
                <span class="ltr:ml-4 rtl:mr-4 hover:text-red-600 hover:font-bold">{{ $t('words.finance') }}</span>
            </template>
        </sidebar-link>

        <sidebar-link v-can="'view-backoffice-reports'" :link-value="route('back.reports.index')" :active="$page.currentRouteName == 'back.reports.index'">
            <template #icon>
                <img src="/img/reports.svg" alt="" class="w-5 h-5">
            </template>
            <template #title>
                <span class="ltr:ml-4 rtl:mr-4 hover:text-red-600 hover:font-bold">{{ $t('words.reports') }}</span>
            </template>
        </sidebar-link>

        <sidebar-link v-can="'view-orders'" :link-value="route('orders.index')" :active="$page.currentRouteName == 'orders.index'">
            <template #icon>
                <img src="https://icons.veryicon.com/png/o/miscellaneous/linear-icon-14/place-order-1.png" alt="" class="w-5 h-5">
            </template>
            <template #title>
                <span class="ltr:ml-4 rtl:mr-4 hover:text-red-600 hover:font-bold">{{ $t('words.orders') }}</span>
            </template>
        </sidebar-link>

        <sidebar-link :link-value="route('back.settings')">
            <template #icon>
                <ion-icon name="settings-outline" class="w-5 h-5"></ion-icon>
            </template>
            <template #title>
                <span class="ltr:ml-4 rtl:mr-4 hover:text-red-600 hover:font-bold">{{ $t('words.settings') }}</span>
            </template>
        </sidebar-link>

  

        <li class="pr-8 text-xs"><a class="hover:text-blue-600" href="/terms">السياسات</a></li>
        <li class="pr-8 mt-1 text-2xs text-gray-500" v-if="buildVersion">Version {{ buildVersion }}</li>
    </ul>
</template>

<script>
    import EarthIcon from 'vue-ionicons/dist/ios-easel.vue';
    import DesktopIcon from 'vue-ionicons/dist/ios-desktop.vue';
    import BusinessIcon from 'vue-ionicons/dist/ios-business';
    import SidebarLink from "./SidebarLink";
    export default {
        components: {
            DesktopIcon,
            SidebarLink,
            EarthIcon,
            BusinessIcon,
        },
        data() {
            return {
                buildVersion: (typeof process !== 'undefined' && process.env && process.env.BUILD_VERSION) ? process.env.BUILD_VERSION : null,
            }
        },
        mounted() {
            //
        },
        methods: {
            //
        }
    }
</script>

<style lang="sass">
    //
</style>
