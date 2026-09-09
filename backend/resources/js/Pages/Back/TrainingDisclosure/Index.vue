<template>
  <app-layout>
    <div class="container px-6 mx-auto grid pt-6 max-w-6xl">
      <breadcrumb-container :crumbs="breadcrumbs" />

      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mb-8">
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4">
          <p class="text-xs text-gray-500">{{ $t("words.recorded-courses") }}</p>
          <p class="text-2xl font-bold text-gray-900 mt-1">{{ stats.courses }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4">
          <p class="text-xs text-gray-500">{{ $t("words.training-disclosure-stat-companies") }}</p>
          <p class="text-2xl font-bold text-gray-900 mt-1">{{ stats.companies }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4">
          <p class="text-xs text-gray-500">{{ $t("words.training-disclosure-stat-enrollments") }}</p>
          <p class="text-2xl font-bold text-gray-900 mt-1">{{ stats.enrollments }}</p>
          <p class="text-xs text-gray-500 mt-1">
            {{ $t("words.training-disclosure-stat-checked-in") }}: {{ stats.checked_in }} ·
            {{ $t("words.training-disclosure-stat-completed") }}: {{ stats.completed }}
          </p>
        </div>
        <div class="bg-white rounded-lg border border-amber-200 shadow-sm p-4">
          <p class="text-xs text-amber-700">{{ $t("words.training-disclosure-stat-pending") }}</p>
          <p class="text-2xl font-bold text-amber-800 mt-1">{{ stats.pending_approval }}</p>
          <p class="text-xs text-gray-500 mt-1">
            {{ $t("words.training-disclosure-stat-sent") }}: {{ stats.certificates_sent }}
          </p>
        </div>
      </div>

      <div class="grid gap-4 sm:grid-cols-2 mb-10">
        <inertia-link
          :href="route('back.settings.recorded-courses.index')"
          class="block p-5 bg-white rounded-lg shadow border border-gray-200 hover:border-indigo-300 hover:shadow-md transition"
        >
          <h2 class="font-semibold text-gray-900 mb-1">
            {{ $t("words.training-disclosure-card-courses-title") }}
          </h2>
          <p class="text-sm text-gray-600">
            {{ $t("words.training-disclosure-card-courses-body") }}
          </p>
        </inertia-link>

        <inertia-link
          :href="route('back.settings.recorded-courses.index')"
          class="block p-5 bg-white rounded-lg shadow border border-gray-200 hover:border-indigo-300 hover:shadow-md transition"
        >
          <h2 class="font-semibold text-gray-900 mb-1">
            {{ $t("words.training-disclosure-card-companies-title") }}
          </h2>
          <p class="text-sm text-gray-600">
            {{ $t("words.training-disclosure-card-companies-body") }}
          </p>
        </inertia-link>
      </div>

      <div class="grid gap-6 lg:grid-cols-2">
        <section class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-semibold text-gray-900">
              {{ $t("words.training-disclosure-courses-list") }}
            </h2>
            <inertia-link
              class="text-sm text-indigo-600 hover:underline"
              :href="route('back.settings.recorded-courses.index')"
            >
              {{ $t("words.manage") }}
            </inertia-link>
          </div>
          <div v-if="courses.length" class="divide-y divide-gray-100">
            <div
              v-for="course in courses"
              :key="course.id"
              class="px-5 py-3 flex flex-wrap items-center justify-between gap-3"
            >
              <div>
                <p class="font-medium text-gray-900">{{ courseTitle(course) }}</p>
                <p class="text-xs text-gray-500 mt-0.5">
                  {{ $t("words.recorded-course-enrollments-count", { count: course.enrollments_count }) }}
                  ·
                  {{ $t("words.training-disclosure-stat-completed") }}:
                  {{ course.completed_enrollments_count }}
                  ·
                  {{ $t("words.training-disclosure-stat-pending") }}:
                  {{ course.pending_approval_count }}
                </p>
              </div>
              <div class="flex gap-2">
                <inertia-link
                  class="text-xs font-medium text-indigo-600 hover:underline"
                  :href="route('back.settings.recorded-courses.show', course.id)"
                >
                  {{ $t("words.recorded-course-manage") }}
                </inertia-link>
                <inertia-link
                  class="text-xs font-medium text-indigo-600 hover:underline"
                  :href="route('back.settings.recorded-courses.enrollments.index', course.id)"
                >
                  {{ $t("words.recorded-course-step-enrollments") }}
                </inertia-link>
              </div>
            </div>
          </div>
          <p v-else class="px-5 py-8 text-sm text-gray-500">
            {{ $t("words.nothing-is-here") }}
          </p>
        </section>

        <section class="bg-white rounded-lg border border-amber-100 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-amber-50 bg-amber-50/50">
            <h2 class="font-semibold text-gray-900">
              {{ $t("words.training-disclosure-pending-queue") }}
            </h2>
            <p class="text-xs text-gray-600 mt-1">
              {{ $t("words.training-disclosure-pending-queue-help") }}
            </p>
          </div>
          <div v-if="pendingApprovals.length" class="divide-y divide-gray-100">
            <div
              v-for="row in pendingApprovals"
              :key="row.id"
              class="px-5 py-3"
            >
              <p class="font-medium text-gray-900">{{ row.trainee_name }}</p>
              <p class="text-xs text-gray-500 mt-0.5">
                {{ row.company_name || "—" }} · {{ row.course_name }}
              </p>
              <div class="mt-2 flex flex-wrap gap-3">
                <inertia-link
                  class="text-xs font-medium text-indigo-600 hover:underline"
                  :href="route('back.settings.recorded-courses.enrollments.index', row.course_id)"
                >
                  {{ $t("words.recorded-course-step-enrollments") }}
                </inertia-link>
                <button
                  v-if="canApproveCertificates"
                  type="button"
                  class="text-xs font-medium text-green-700 hover:underline"
                  @click="approveCert(row)"
                >
                  {{ $t("words.recorded-course-approve-send-certificate") }}
                </button>
              </div>
            </div>
          </div>
          <p v-else class="px-5 py-8 text-sm text-gray-500">
            {{ $t("words.training-disclosure-no-pending") }}
          </p>
        </section>
      </div>

      <section class="mt-8 bg-white rounded-lg border border-gray-200 shadow-sm p-5">
        <h2 class="font-semibold text-gray-900 mb-3">
          {{ $t("words.training-disclosure-process-title") }}
        </h2>
        <ol class="list-decimal list-inside space-y-2 text-sm text-gray-700">
          <li>{{ $t("words.training-disclosure-step-1") }}</li>
          <li>{{ $t("words.training-disclosure-step-2") }}</li>
          <li>{{ $t("words.training-disclosure-step-3") }}</li>
          <li>{{ $t("words.training-disclosure-step-4") }}</li>
          <li>{{ $t("words.training-disclosure-step-5") }}</li>
        </ol>
      </section>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout";
import BreadcrumbContainer from "@/Components/BreadcrumbContainer";
import { Inertia } from "@inertiajs/inertia";

export default {
  metaInfo: { title: "Training disclosure" },
  components: {
    AppLayout,
    BreadcrumbContainer,
  },
  props: {
    stats: { type: Object, required: true },
    courses: { type: Array, default: () => [] },
    pendingApprovals: { type: Array, default: () => [] },
    canApproveCertificates: { type: Boolean, default: false },
  },
  computed: {
    locale() {
      return this.$page.props.locale || "ar";
    },
    breadcrumbs() {
      return [
        { title: "dashboard", link: this.route("dashboard") },
        { title: "training-disclosure" },
      ];
    },
  },
  methods: {
    courseTitle(course) {
      return this.locale === "ar" ? course.name_ar : course.name_en || course.name_ar;
    },
    approveCert(row) {
      Inertia.post(
        this.route(
          "back.settings.recorded-courses.enrollments.approve-certificate",
          [row.course_id, row.id]
        ),
        {},
        { preserveScroll: true }
      );
    },
  },
};
</script>
