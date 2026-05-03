<template>
  <app-layout>
    <div class="container px-6 mx-auto grid pt-6">
      <breadcrumb-container
        :crumbs="[
          { title: 'dashboard', link: route('dashboard') },
          { title: 'settings', link: route('back.settings') },
          { title: 'recorded-courses' },
        ]"
      ></breadcrumb-container>
      <div class="flex justify-between">
        <h1 class="mb-8 font-bold text-3xl">
          {{ $t("words.recorded-courses") }}
        </h1>
        <div class="mb-6 flex justify-between items-center gap-2">
          <inertia-link
            class="btn-gray"
            :href="route('back.settings.recorded-courses.create')"
          >
            <span>{{ $t("words.recorded-course-new") }}</span>
          </inertia-link>
        </div>
      </div>
      <div class="bg-white rounded shadow overflow-x-auto">
        <table class="w-full whitespace-no-wrap">
          <tr class="text-left font-bold">
            <th class="px-6 pt-6 pb-4">{{ $t("words.name_ar") }}</th>
            <th class="px-6 pt-6 pb-4">{{ $t("words.name_en") }}</th>
            <th class="px-6 pt-6 pb-4">
              {{ $t("words.recorded-course-lessons-count") }}
            </th>
            <th class="px-6 pt-6 pb-4">{{ $t("words.unlock-delay-hours") }}</th>
            <th class="px-6 pt-6 pb-4">{{ $t("words.allowed-weekdays") }}</th>
            <th class="px-6 pt-6 pb-4"></th>
          </tr>
          <tr
            v-for="course in recordedCourses.data"
            :key="course.id"
            class="hover:bg-gray-100 focus-within:bg-gray-100"
          >
            <td class="border-t">
              <div class="px-6 py-4">{{ course.name_ar }}</div>
            </td>
            <td class="border-t">
              <div class="px-6 py-4">{{ course.name_en }}</div>
            </td>
            <td class="border-t">
              <div class="px-6 py-4">{{ course.lessons_count }}</div>
            </td>
            <td class="border-t">
              <div class="px-6 py-4">{{ course.unlock_delay_hours }}</div>
            </td>
            <td class="border-t">
              <div class="px-6 py-4">{{ formatWeekdays(course.allowed_weekdays) }}</div>
            </td>
            <td class="border-t">
              <div class="px-6 py-4 flex gap-2">
                <inertia-link
                  class="btn-gray text-sm"
                  :href="
                    route('back.settings.recorded-courses.edit', course.id)
                  "
                >
                  {{ $t("words.edit") }}
                </inertia-link>
                <button
                  type="button"
                  class="bg-red-500 p-2 px-3 rounded text-white text-sm"
                  @click.prevent="confirmDelete(course.id)"
                >
                  {{ $t("words.delete") }}
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="recordedCourses.data.length === 0">
            <td class="border-t px-6 py-4" colspan="6">
              <empty-slate />
            </td>
          </tr>
        </table>
      </div>
      <pagination :links="recordedCourses.links" />
    </div>
  </app-layout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout";
import BreadcrumbContainer from "@/Components/BreadcrumbContainer";
import EmptySlate from "@/Components/EmptySlate";
import Pagination from "@/Shared/Pagination";

export default {
  metaInfo: { title: "Recorded courses" },
  components: {
    AppLayout,
    BreadcrumbContainer,
    EmptySlate,
    Pagination,
  },
  props: {
    recordedCourses: Object,
  },
  methods: {
    formatWeekdays(days) {
      if (!days || !days.length) return "—";
      return [...days]
        .sort((a, b) => a - b)
        .map((d) => this.$t(`words.recorded-course-weekday-${d}`))
        .join(", ");
    },
    confirmDelete(id) {
      if (confirm(this.$t("words.recorded-course-delete-confirm"))) {
        this.$inertia.delete(
          this.route("back.settings.recorded-courses.destroy", id)
        );
      }
    },
  },
};
</script>
