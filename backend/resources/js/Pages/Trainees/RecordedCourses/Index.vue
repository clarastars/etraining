<template>
  <app-layout>
    <div class="container px-6 mx-auto grid pt-6">
      <breadcrumb-container
        :crumbs="[
          { title: 'dashboard', link: route('dashboard') },
          { title: 'recorded-courses-my-courses' },
        ]"
      />
      <h1 class="mb-8 font-bold text-3xl">{{ $t("words.recorded-courses-my-courses") }}</h1>

      <div v-if="enrollments.length === 0" class="bg-white rounded shadow p-8 text-center text-gray-600">
        {{ $t("words.nothing-is-here") }}
      </div>

      <div v-else class="bg-white rounded shadow overflow-x-auto">
        <table class="w-full whitespace-no-wrap">
          <thead>
            <tr class="text-left font-bold">
              <th class="px-6 pt-6 pb-4">{{ $t("words.name") }}</th>
              <th class="px-6 pt-6 pb-4 w-px" aria-hidden="true"></th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in enrollments"
              :key="row.id"
              class="hover:bg-gray-100 focus-within:bg-gray-100"
            >
              <td class="border-t px-6 py-4">
                <inertia-link
                  class="font-medium text-indigo-600 hover:text-indigo-800"
                  :href="route('recorded-courses.enrollments.show', row.id)"
                >
                  {{ locale === "ar" ? row.course.name_ar : row.course.name_en }}
                </inertia-link>
              </td>
              <td class="border-t w-px">
                <inertia-link
                  class="px-4 flex items-center"
                  :href="route('recorded-courses.enrollments.show', row.id)"
                  tabindex="-1"
                >
                  <ion-icon name="arrow-forward-outline" class="block w-6 h-6 fill-gray-400" />
                </inertia-link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout";
import BreadcrumbContainer from "@/Components/BreadcrumbContainer";

export default {
  metaInfo: { title: "Recorded courses" },
  components: { AppLayout, BreadcrumbContainer },
  props: {
    enrollments: { type: Array, required: true },
  },
  computed: {
    locale() {
      return this.$page.props.locale || "ar";
    },
  },
};
</script>
