<template>
  <app-layout>
    <div class="container px-6 mx-auto grid pt-6 max-w-6xl">
      <breadcrumb-container :crumbs="breadcrumbs" />

      <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 class="font-bold text-2xl text-gray-900">
            {{ $t("words.training-disclosure-report") }}
          </h1>
          <p class="text-sm text-gray-600 mt-1 max-w-2xl">
            {{ $t("words.training-disclosure-report-help") }}
          </p>
        </div>
        <a
          v-if="selectedCompany && selectedCompany.linked"
          class="btn-blue"
          :href="exportHref"
        >
          {{ $t("words.training-disclosure-report-download") }}
        </a>
      </div>

      <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5 mb-6">
        <label class="block text-sm font-medium text-gray-700 mb-1" for="disclosure-company">
          {{ $t("words.company") }}
        </label>
        <select
          id="disclosure-company"
          class="border-gray-300 rounded-md shadow-sm text-sm min-w-[16rem] max-w-full"
          :value="selectedCompany ? selectedCompany.id : ''"
          @change="onCompanyChange"
        >
          <option value="">{{ $t("words.training-disclosure-report-select-company") }}</option>
          <option v-for="company in companies" :key="company.id" :value="company.id">
            {{ companyLabel(company) }}
          </option>
        </select>
        <p class="text-xs text-gray-500 mt-2">
          {{ $t("words.training-disclosure-report-companies-only") }}
        </p>
        <p v-if="selectedCompany && selectedCompany.request_numbers.length" class="text-xs text-gray-500 mt-1">
          {{ $t("words.training-disclosure-report-requests") }}:
          {{ selectedCompany.request_numbers.join(" · ") }}
        </p>
      </div>

      <p v-if="!selectedCompany" class="text-sm text-gray-500 mb-6">
        {{ $t("words.training-disclosure-report-empty-company") }}
      </p>

      <template v-else>
        <p v-if="!selectedCompany.linked" class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 mb-6">
          {{ $t("words.training-disclosure-report-unlinked") }}
        </p>

        <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mb-6">
          <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4">
            <p class="text-xs text-gray-500">{{ $t("words.training-disclosure-report-summary-employees") }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ summary.employees }}</p>
          </div>
          <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4">
            <p class="text-xs text-gray-500">{{ $t("words.training-disclosure-report-summary-delivered") }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ summary.emails_delivered }}</p>
          </div>
          <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4">
            <p class="text-xs text-gray-500">{{ $t("words.training-disclosure-report-summary-accessed") }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ summary.accessed }}</p>
          </div>
          <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4">
            <p class="text-xs text-gray-500">{{ $t("words.training-disclosure-report-summary-eligible") }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ summary.eligible }}</p>
          </div>
        </div>

        <div v-if="selectedCompany.linked" class="bg-white rounded shadow overflow-x-auto">
          <table v-if="rows.length" class="min-w-full text-sm border border-gray-200 rounded-lg overflow-hidden">
            <thead class="bg-gray-50 text-left rtl:text-right">
              <tr>
                <th class="border-b border-gray-200 px-3 py-2 font-semibold text-gray-700">
                  {{ $t("words.name") }}
                </th>
                <th class="border-b border-gray-200 px-3 py-2 font-semibold text-gray-700">
                  {{ $t("words.email") }}
                </th>
                <th class="border-b border-gray-200 px-3 py-2 font-semibold text-gray-700">
                  {{ $t("words.course") }}
                </th>
                <th class="border-b border-gray-200 px-3 py-2 font-semibold text-gray-700 whitespace-nowrap">
                  {{ $t("words.training-disclosure-report-delivered-email") }}
                </th>
                <th class="border-b border-gray-200 px-3 py-2 font-semibold text-gray-700 whitespace-nowrap">
                  {{ $t("words.training-disclosure-report-accessed") }}
                </th>
                <th class="border-b border-gray-200 px-3 py-2 font-semibold text-gray-700 whitespace-nowrap">
                  {{ $t("words.recorded-course-progress") }}
                </th>
                <th class="border-b border-gray-200 px-3 py-2 font-semibold text-gray-700 whitespace-nowrap">
                  {{ $t("words.training-disclosure-report-certificate-eligibility") }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in rows"
                :key="row.id"
                class="border-b border-gray-100 last:border-0"
                :class="{ 'bg-amber-50': row.entitlement === 'eligible' }"
              >
                <td class="px-3 py-2 text-gray-900">{{ row.name || "—" }}</td>
                <td class="px-3 py-2 text-gray-600" dir="ltr">{{ row.email || "—" }}</td>
                <td class="px-3 py-2 text-gray-700">{{ courseTitle(row) }}</td>
                <td class="px-3 py-2">
                  <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium" :class="deliveryClass(row.delivery_status)">
                    {{ deliveryLabel(row.delivery_status) }}
                  </span>
                </td>
                <td class="px-3 py-2 text-xs">
                  <span v-if="row.accessed" class="font-medium text-green-700">
                    {{ $t("words.yes") }}
                  </span>
                  <span v-else class="text-gray-500">{{ $t("words.no") }}</span>
                  <div v-if="row.checked_in_at" class="text-gray-500 mt-0.5 whitespace-nowrap">
                    {{ formatTs(row.checked_in_at) }}
                  </div>
                </td>
                <td class="px-3 py-2 whitespace-nowrap">
                  <div class="text-xs font-medium text-gray-900">
                    {{ row.lessons_completed }}/{{ row.lessons_total }}
                    ({{ row.progress_percent }}%)
                  </div>
                  <div class="mt-1 h-1.5 w-24 rounded bg-gray-100 overflow-hidden">
                    <div
                      class="h-full bg-indigo-500"
                      :style="{ width: Math.min(100, row.progress_percent || 0) + '%' }"
                    ></div>
                  </div>
                </td>
                <td class="px-3 py-2 text-xs font-medium">
                  {{ entitlementLabel(row.entitlement) }}
                </td>
              </tr>
            </tbody>
          </table>
          <p v-else class="px-6 py-8 text-sm text-gray-500">
            {{ $t("words.training-disclosure-report-no-enrollments") }}
          </p>
        </div>
      </template>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout";
import BreadcrumbContainer from "@/Components/BreadcrumbContainer";
import { Inertia } from "@inertiajs/inertia";

export default {
  metaInfo() {
    return { title: this.$t("words.training-disclosure-report") };
  },
  components: {
    AppLayout,
    BreadcrumbContainer,
  },
  props: {
    companies: { type: Array, default: () => [] },
    selectedCompany: { type: Object, default: null },
    rows: { type: Array, default: () => [] },
    summary: {
      type: Object,
      default: () => ({
        employees: 0,
        emails_delivered: 0,
        accessed: 0,
        eligible: 0,
      }),
    },
  },
  computed: {
    locale() {
      return this.$page.props.locale || "ar";
    },
    breadcrumbs() {
      return [
        { title: "dashboard", link: this.route("dashboard") },
        { title: "training-disclosure", link: this.route("back.training-disclosure.index") },
        { title: "training-disclosure-report" },
      ];
    },
    exportHref() {
      if (!this.selectedCompany) {
        return "#";
      }
      const base = this.route("back.training-disclosure.report.export");
      const joiner = base.indexOf("?") === -1 ? "?" : "&";
      return base + joiner + "company=" + encodeURIComponent(this.selectedCompany.id);
    },
  },
  methods: {
    companyLabel(company) {
      return this.locale === "ar"
        ? company.name_ar || company.name_en
        : company.name_en || company.name_ar;
    },
    courseTitle(row) {
      return this.locale === "ar"
        ? row.course_name_ar || row.course_name_en || "—"
        : row.course_name_en || row.course_name_ar || "—";
    },
    onCompanyChange(event) {
      const company = event.target.value;
      Inertia.get(
        this.route("back.training-disclosure.report"),
        company ? { company } : {},
        { preserveScroll: true, replace: true }
      );
    },
    formatTs(value) {
      if (!value) return "";
      try {
        return new Date(value).toLocaleString();
      } catch (e) {
        return value;
      }
    },
    deliveryLabel(status) {
      const key = "words.training-disclosure-report-delivery-" + (status || "not_sent");
      const translated = this.$t(key);
      return translated === key ? status : translated;
    },
    deliveryClass(status) {
      if (status === "delivered") return "bg-green-100 text-green-800";
      if (status === "failed") return "bg-red-100 text-red-800";
      if (status === "pending") return "bg-yellow-100 text-yellow-800";
      return "bg-gray-100 text-gray-600";
    },
    entitlementLabel(entitlement) {
      const key = "words.recorded-course-entitlement-" + (entitlement || "in_progress");
      const translated = this.$t(key);
      return translated === key ? entitlement : translated;
    },
  },
};
</script>
