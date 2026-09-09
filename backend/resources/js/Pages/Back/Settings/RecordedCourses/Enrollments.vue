<template>
  <app-layout>
    <div class="container px-6 mx-auto grid pt-6 max-w-6xl">
      <breadcrumb-container :crumbs="breadcrumbs" />
      <recorded-course-flash />

      <h1 class="font-bold text-2xl text-gray-900 mb-1">{{ courseTitle }}</h1>
      <p class="text-sm text-gray-600 mb-6">
        {{ $t("words.recorded-course-company-manage-intro") }}
      </p>

      <recorded-course-step-nav
        :course-id="recordedCourse.id"
        current-step="enrollments"
        :readiness="readiness"
      />

      <div
        v-if="companySummaries.length"
        class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
      >
        <button
          v-for="summary in companySummaries"
          :key="summary.company_id"
          type="button"
          class="text-left rtl:text-right p-4 bg-white rounded-lg border shadow-sm transition hover:border-indigo-300"
          :class="
            filterCompanyId === summary.company_id
              ? 'border-indigo-400 ring-1 ring-indigo-200'
              : 'border-gray-200'
          "
          @click="toggleCompanyFilter(summary.company_id)"
        >
          <p class="font-semibold text-gray-900 mb-2">{{ summary.company_name }}</p>
          <dl class="grid grid-cols-2 gap-x-2 gap-y-1 text-xs text-gray-600">
            <div>
              <dt class="inline">{{ $t("words.recorded-course-summary-enrolled") }}:</dt>
              <dd class="inline font-medium text-gray-900">{{ summary.enrolled }}</dd>
            </div>
            <div>
              <dt class="inline">{{ $t("words.recorded-course-summary-checked-in") }}:</dt>
              <dd class="inline font-medium text-gray-900">{{ summary.checked_in }}</dd>
            </div>
            <div>
              <dt class="inline">{{ $t("words.recorded-course-summary-completed") }}:</dt>
              <dd class="inline font-medium text-gray-900">{{ summary.completed }}</dd>
            </div>
            <div>
              <dt class="inline">{{ $t("words.recorded-course-summary-eligible") }}:</dt>
              <dd class="inline font-medium text-amber-700">{{ summary.eligible }}</dd>
            </div>
            <div class="col-span-2">
              <dt class="inline">{{ $t("words.recorded-course-summary-cert-sent") }}:</dt>
              <dd class="inline font-medium text-green-700">{{ summary.certificate_sent }}</dd>
            </div>
          </dl>
        </button>
      </div>

      <div class="mb-6 p-4 bg-white rounded-lg border border-gray-200">
        <h2 class="font-semibold text-gray-900 mb-1">
          {{ $t("words.recorded-course-bulk-enroll-title") }}
        </h2>
        <p class="text-xs text-gray-500 mb-3">
          {{ $t("words.recorded-course-bulk-enroll-help") }}
        </p>
        <div class="grid gap-3 md:grid-cols-2">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              {{ $t("words.company") }}
            </label>
            <select
              v-model="bulkCompanyId"
              class="w-full border-gray-300 rounded-md shadow-sm text-sm"
              @change="loadCompanyTrainees"
            >
              <option value="">{{ $t("words.please-select") }}</option>
              <option v-for="c in companies" :key="c.id" :value="c.id">
                {{ c.name }}
              </option>
            </select>
          </div>
          <div class="flex items-end gap-2 flex-wrap">
            <button
              type="button"
              class="btn-blue"
              :disabled="!enrollableSelectedCount || bulkSubmitting"
              @click="submitBulkEnroll"
            >
              {{ $t("words.recorded-course-bulk-enroll-submit") }}
              <span v-if="enrollableSelectedCount">({{ enrollableSelectedCount }})</span>
            </button>
            <button
              v-if="companyTrainees.length"
              type="button"
              class="btn-gray text-sm"
              @click="selectAllNotEnrolled"
            >
              {{ $t("words.recorded-course-select-unenrolled") }}
            </button>
          </div>
        </div>
        <div v-if="companyTrainees.length" class="mt-4 max-h-56 overflow-y-auto border rounded">
          <label
            v-for="t in companyTrainees"
            :key="t.id"
            class="flex items-center gap-2 px-3 py-2 border-b last:border-0 text-sm"
            :class="t.already_enrolled ? 'bg-gray-50 text-gray-500' : 'hover:bg-gray-50'"
          >
            <input
              type="checkbox"
              :value="t.id"
              v-model="selectedTraineeIds"
              :disabled="t.already_enrolled"
            />
            <span class="font-medium" :class="t.already_enrolled ? '' : 'text-gray-900'">
              {{ t.name }}
            </span>
            <span class="text-xs">{{ t.email }}</span>
            <span
              v-if="t.already_enrolled"
              class="ml-auto rtl:mr-auto text-xs font-medium text-indigo-600"
            >
              {{ $t("words.recorded-course-already-enrolled-badge") }}
            </span>
          </label>
        </div>
        <p v-else-if="bulkCompanyId" class="mt-3 text-sm text-gray-500">
          {{ $t("words.recorded-course-company-no-trainees") }}
        </p>
      </div>

      <div class="mb-3 flex flex-wrap items-center gap-3">
        <label class="text-sm font-medium text-gray-700">
          {{ $t("words.recorded-course-filter-company") }}
        </label>
        <select
          v-model="filterCompanyId"
          class="border-gray-300 rounded-md shadow-sm text-sm"
        >
          <option value="">{{ $t("words.recorded-course-filter-all-companies") }}</option>
          <option
            v-for="summary in companySummaries"
            :key="'f-' + summary.company_id"
            :value="summary.company_id"
          >
            {{ summary.company_name }}
          </option>
        </select>
        <span class="text-xs text-gray-500">
          {{ filteredEnrollments.length }} / {{ enrollments.length }}
        </span>
      </div>

      <div class="bg-white rounded shadow overflow-x-auto">
        <template v-if="filteredEnrollments.length">
          <table class="min-w-full text-sm border border-gray-200 rounded-lg overflow-hidden">
            <thead class="bg-gray-50 text-left rtl:text-right">
              <tr>
                <th class="border-b border-gray-200 px-3 py-2 font-semibold text-gray-700">
                  {{ $t("words.name") }}
                </th>
                <th class="border-b border-gray-200 px-3 py-2 font-semibold text-gray-700 whitespace-nowrap">
                  {{ $t("words.company") }}
                </th>
                <th class="border-b border-gray-200 px-3 py-2 font-semibold text-gray-700 whitespace-nowrap">
                  {{ $t("words.recorded-course-progress") }}
                </th>
                <th class="border-b border-gray-200 px-3 py-2 font-semibold text-gray-700 whitespace-nowrap">
                  {{ $t("words.recorded-course-entitlement") }}
                </th>
                <th class="border-b border-gray-200 px-3 py-2 font-semibold text-gray-700 whitespace-nowrap">
                  {{ $t("words.recorded-course-link-sent") }}
                </th>
                <th class="border-b border-gray-200 px-3 py-2 font-semibold text-gray-700 whitespace-nowrap">
                  {{ $t("words.recorded-course-check-in") }}
                </th>
                <th class="border-b border-gray-200 px-3 py-2 font-semibold text-gray-700 whitespace-nowrap">
                  {{ $t("words.recorded-course-check-out") }}
                </th>
                <th class="border-b border-gray-200 px-3 py-2 font-semibold text-gray-700 whitespace-nowrap">
                  {{ $t("words.recorded-course-certificate-status") }}
                </th>
                <th class="border-b border-gray-200 px-3 py-2 font-semibold text-gray-700 whitespace-nowrap">
                  {{ $t("words.recorded-course-delivery-status") }}
                </th>
                <th
                  v-for="lesson in lessons"
                  :key="'h-' + lesson.id"
                  class="border-b border-gray-200 px-2 py-2 font-semibold text-gray-700 text-center min-w-[7rem]"
                >
                  <span class="line-clamp-2">{{ lesson.title_en || lesson.title_ar }}</span>
                </th>
                <th class="border-b border-gray-200 px-3 py-2 font-semibold text-gray-700">
                  {{ $t("words.actions") }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in filteredEnrollments"
                :key="row.id"
                class="border-b border-gray-100 last:border-0"
                :class="{ 'bg-amber-50': row.entitlement === 'eligible' }"
              >
                <td class="px-3 py-2 text-gray-900">
                  <div>{{ row.trainee_name || row.trainee_id }}</div>
                  <div class="text-xs text-gray-500">{{ row.trainee_email }}</div>
                </td>
                <td class="px-3 py-2 text-gray-600 text-xs">
                  {{ row.company_name || "—" }}
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
                <td class="px-3 py-2 text-gray-600 whitespace-nowrap text-xs">
                  {{ formatTs(row.access_link_sent_at) }}
                </td>
                <td class="px-3 py-2 text-gray-600 whitespace-nowrap text-xs">
                  {{ formatTs(row.checked_in_at) }}
                </td>
                <td class="px-3 py-2 text-gray-600 whitespace-nowrap text-xs">
                  {{ formatTs(row.checked_out_at) }}
                </td>
                <td class="px-3 py-2 text-xs font-medium">
                  {{ certificateLabel(row.certificate_status) }}
                </td>
                <td class="px-3 py-2 text-xs">
                  {{ row.delivery_status || "—" }}
                </td>
                <td
                  v-for="(lp, idx) in row.lesson_progress"
                  :key="row.id + '-' + (lp.lesson_id || idx)"
                  class="px-2 py-2 text-center text-xs border-l border-gray-100"
                >
                  <span v-if="lp.completed_at" class="text-green-700 font-medium">{{
                    $t("words.recorded-course-progress-done")
                  }}</span>
                  <span v-else-if="lp.unlocked_at" class="text-indigo-700 font-medium">{{
                    $t("words.recorded-course-progress-unlocked")
                  }}</span>
                  <span v-else class="text-gray-400">—</span>
                </td>
                <td class="px-3 py-2 whitespace-nowrap space-x-2 rtl:space-x-reverse">
                  <button
                    type="button"
                    class="text-indigo-600 hover:underline text-xs font-medium"
                    @click="resendLink(row)"
                  >
                    {{ $t("words.recorded-course-resend-link") }}
                  </button>
                  <button
                    v-if="canApproveCertificates && (row.certificate_status === 'pending_approval' || row.certificate_status === 'failed')"
                    type="button"
                    class="text-green-700 hover:underline text-xs font-medium"
                    @click="approveCert(row)"
                  >
                    {{ $t("words.recorded-course-approve-send-certificate") }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </template>
        <p v-else class="px-6 py-8 text-sm text-gray-500">
          {{ $t("words.nothing-is-here") }}
        </p>
      </div>

      <div class="mt-6">
        <inertia-link
          class="btn-gray"
          :href="route('back.settings.recorded-courses.show', recordedCourse.id)"
        >
          {{ $t("words.go-back") }}
        </inertia-link>
      </div>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout";
import BreadcrumbContainer from "@/Components/BreadcrumbContainer";
import RecordedCourseFlash from "@/Components/RecordedCourseFlash";
import RecordedCourseStepNav from "@/Components/RecordedCourseStepNav";
import { Inertia } from "@inertiajs/inertia";

export default {
  metaInfo: { title: "Course enrollments" },
  components: {
    AppLayout,
    BreadcrumbContainer,
    RecordedCourseFlash,
    RecordedCourseStepNav,
  },
  props: {
    recordedCourse: { type: Object, required: true },
    readiness: { type: Object, required: true },
    lessons: { type: Array, default: () => [] },
    enrollments: { type: Array, default: () => [] },
    companySummaries: { type: Array, default: () => [] },
    companies: { type: Array, default: () => [] },
    canApproveCertificates: { type: Boolean, default: false },
    initialFilterCompanyId: { type: String, default: "" },
  },
  data() {
    return {
      bulkCompanyId: "",
      companyTrainees: [],
      selectedTraineeIds: [],
      bulkSubmitting: false,
      filterCompanyId: this.initialFilterCompanyId || "",
    };
  },
  computed: {
    locale() {
      return this.$page.props.locale || "ar";
    },
    courseTitle() {
      return this.locale === "ar"
        ? this.recordedCourse.name_ar
        : this.recordedCourse.name_en;
    },
    breadcrumbs() {
      return [
        { title: "dashboard", link: this.route("dashboard") },
        { title: "training-disclosure", link: this.route("back.training-disclosure.index") },
        {
          title: "recorded-courses",
          link: this.route("back.settings.recorded-courses.index"),
        },
        {
          title_raw: this.courseTitle,
          link: this.route("back.settings.recorded-courses.show", this.recordedCourse.id),
        },
        { title: "recorded-course-step-enrollments" },
      ];
    },
    filteredEnrollments() {
      if (!this.filterCompanyId) {
        return this.enrollments;
      }
      return this.enrollments.filter((row) => row.company_id === this.filterCompanyId);
    },
    enrollableSelectedCount() {
      const enrolled = new Set(
        this.companyTrainees.filter((t) => t.already_enrolled).map((t) => t.id)
      );
      return this.selectedTraineeIds.filter((id) => !enrolled.has(id)).length;
    },
  },
  methods: {
    formatTs(value) {
      if (!value) return "—";
      try {
        return new Date(value).toLocaleString();
      } catch (e) {
        return value;
      }
    },
    certificateLabel(status) {
      const key = "words.recorded-course-cert-status-" + (status || "none");
      const translated = this.$t(key);
      return translated === key ? status || "none" : translated;
    },
    entitlementLabel(entitlement) {
      const key = "words.recorded-course-entitlement-" + (entitlement || "in_progress");
      const translated = this.$t(key);
      return translated === key ? entitlement : translated;
    },
    toggleCompanyFilter(companyId) {
      this.filterCompanyId = this.filterCompanyId === companyId ? "" : companyId;
    },
    selectAllNotEnrolled() {
      this.selectedTraineeIds = this.companyTrainees
        .filter((t) => !t.already_enrolled)
        .map((t) => t.id);
    },
    async loadCompanyTrainees() {
      this.selectedTraineeIds = [];
      this.companyTrainees = [];
      if (!this.bulkCompanyId) return;
      try {
        const url = this.route(
          "back.settings.recorded-courses.enrollments.company-trainees",
          this.recordedCourse.id
        );
        const res = await fetch(url + "?company_id=" + encodeURIComponent(this.bulkCompanyId), {
          headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
          credentials: "same-origin",
        });
        const data = await res.json();
        this.companyTrainees = data.trainees || [];
        this.selectAllNotEnrolled();
        this.filterCompanyId = this.bulkCompanyId;
      } catch (e) {
        this.companyTrainees = [];
      }
    },
    submitBulkEnroll() {
      if (!this.enrollableSelectedCount) return;
      this.bulkSubmitting = true;
      Inertia.post(
        this.route("back.settings.recorded-courses.enrollments.bulk", this.recordedCourse.id),
        {
          company_id: this.bulkCompanyId || null,
          trainee_ids: this.selectedTraineeIds.filter((id) => {
            const t = this.companyTrainees.find((row) => row.id === id);
            return t && !t.already_enrolled;
          }),
        },
        {
          onFinish: () => {
            this.bulkSubmitting = false;
          },
        }
      );
    },
    resendLink(row) {
      Inertia.post(
        this.route(
          "back.settings.recorded-courses.enrollments.resend-access-link",
          [this.recordedCourse.id, row.id]
        )
      );
    },
    approveCert(row) {
      Inertia.post(
        this.route(
          "back.settings.recorded-courses.enrollments.approve-certificate",
          [this.recordedCourse.id, row.id]
        )
      );
    },
  },
};
</script>
