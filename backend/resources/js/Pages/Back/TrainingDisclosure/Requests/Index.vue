<template>
  <app-layout>
    <div class="container px-6 mx-auto grid pt-6 max-w-6xl">
      <breadcrumb-container :crumbs="breadcrumbs" />

      <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
          <h1 class="font-bold text-2xl text-gray-900">
            {{ $t("words.training-disclosure-requests") }}
          </h1>
          <p class="text-sm text-gray-600 mt-1">
            {{ $t("words.training-disclosure-requests-help") }}
          </p>
        </div>
        <inertia-link
          class="btn-blue"
          :href="route('back.training-disclosure.requests.create')"
        >
          {{ $t("words.training-disclosure-request-new") }}
        </inertia-link>
      </div>

      <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
        <template v-if="requests.data && requests.data.length">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left rtl:text-right">
              <tr>
                <th class="px-4 py-3 font-semibold text-gray-700">
                  {{ $t("words.training-disclosure-request-number") }}
                </th>
                <th class="px-4 py-3 font-semibold text-gray-700">
                  {{ $t("words.company") }}
                </th>
                <th class="px-4 py-3 font-semibold text-gray-700 whitespace-nowrap">
                  {{ $t("words.training-disclosure-request-trainees-count") }}
                </th>
                <th class="px-4 py-3 font-semibold text-gray-700 whitespace-nowrap">
                  {{ $t("words.training-disclosure-request-draft-count") }}
                </th>
                <th class="px-4 py-3 font-semibold text-gray-700">
                  {{ $t("words.actions") }}
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="row in requests.data" :key="row.id">
                <td class="px-4 py-3 font-mono font-semibold text-indigo-700">
                  {{ row.number }}
                </td>
                <td class="px-4 py-3 text-gray-900">{{ row.company_name }}</td>
                <td class="px-4 py-3 text-gray-700">{{ row.trainees_count }}</td>
                <td class="px-4 py-3 text-gray-700">{{ row.draft_count }}</td>
                <td class="px-4 py-3">
                  <inertia-link
                    class="text-indigo-600 hover:underline font-medium"
                    :href="route('back.training-disclosure.requests.show', row.id)"
                  >
                    {{ $t("words.view") }}
                  </inertia-link>
                </td>
              </tr>
            </tbody>
          </table>
        </template>
        <p v-else class="px-5 py-10 text-sm text-gray-500 text-center">
          {{ $t("words.nothing-is-here") }}
        </p>
      </div>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout";
import BreadcrumbContainer from "@/Components/BreadcrumbContainer";

export default {
  metaInfo: { title: "Disclosure requests" },
  components: {
    AppLayout,
    BreadcrumbContainer,
  },
  props: {
    requests: { type: Object, required: true },
  },
  computed: {
    breadcrumbs() {
      return [
        { title: "dashboard", link: this.route("dashboard") },
        { title: "training-disclosure", link: this.route("back.training-disclosure.index") },
        { title: "training-disclosure-requests" },
      ];
    },
  },
};
</script>
