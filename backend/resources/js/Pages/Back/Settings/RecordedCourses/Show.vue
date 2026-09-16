<template>
  <app-layout>
    <div class="container px-6 mx-auto grid pt-6 max-w-5xl">
      <breadcrumb-container :crumbs="breadcrumbs" />
      <recorded-course-flash />

      <div class="flex flex-wrap items-start justify-between gap-4 mb-2">
        <div>
          <h1 class="font-bold text-3xl text-gray-900">{{ courseTitle }}</h1>
          <p class="text-sm text-gray-600 mt-1">
            {{ $t("words.recorded-course-setup-title") }}
          </p>
        </div>
        <inertia-link
          class="btn-gray text-sm"
          :href="route('back.settings.recorded-courses.index')"
        >
          {{ $t("words.recorded-courses") }}
        </inertia-link>
      </div>

      <recorded-course-step-nav
        :course-id="recordedCourse.id"
        current-step="overview"
        :readiness="readiness"
      />

      <div class="grid gap-4 sm:grid-cols-2">
        <inertia-link
          v-for="card in setupCards"
          :key="card.id"
          :href="card.href"
          class="block p-5 bg-white rounded-lg shadow border transition hover:border-indigo-300 hover:shadow-md"
          :class="card.complete ? 'border-green-200' : 'border-gray-200'"
        >
          <div class="flex items-start justify-between gap-2 mb-2">
            <h3 class="font-semibold text-gray-900">{{ card.title }}</h3>
            <span
              class="text-xs font-medium px-2 py-0.5 rounded-full shrink-0"
              :class="card.complete ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
            >
              {{ card.complete ? "✓" : "…" }}
            </span>
          </div>
          <p class="text-sm text-gray-600">{{ card.description }}</p>
          <p v-if="card.meta" class="text-xs text-gray-500 mt-2">{{ card.meta }}</p>
        </inertia-link>
      </div>

      <div v-if="lessons.length" class="mt-8 bg-white rounded-lg shadow border border-gray-100 p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">
          {{ $t("words.recorded-course-lessons-section") }}
        </h2>
        <ul class="divide-y divide-gray-100">
          <li
            v-for="(lesson, index) in lessons"
            :key="lesson.id"
            class="py-3 flex flex-wrap items-center justify-between gap-3"
          >
            <div>
              <span class="text-xs text-gray-500 mr-2">#{{ index + 1 }}</span>
              <span class="font-medium text-gray-900">{{ lessonTitle(lesson) }}</span>
            </div>
            <div class="flex items-center gap-2">
              <span
                class="text-xs font-medium px-2 py-1 rounded-full"
                :class="lesson.has_video ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'"
              >
                {{
                  lesson.has_video
                    ? $t("words.recorded-course-lesson-video-ready-badge")
                    : $t("words.recorded-course-lesson-video-missing-badge")
                }}
              </span>
              <inertia-link
                class="text-sm text-indigo-600 hover:text-indigo-800"
                :href="
                  route('back.settings.recorded-courses.lessons.video.edit', [
                    recordedCourse.id,
                    lesson.id,
                  ])
                "
              >
                {{ lesson.has_video ? $t("words.recorded-course-lesson-preview") : $t("words.recorded-course-lesson-upload-video") }}
              </inertia-link>
            </div>
          </li>
        </ul>
      </div>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout";
import BreadcrumbContainer from "@/Components/BreadcrumbContainer";
import RecordedCourseFlash from "@/Components/RecordedCourseFlash";
import RecordedCourseStepNav from "@/Components/RecordedCourseStepNav";

export default {
  metaInfo: { title: "Manage recorded course" },
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
        { title_raw: this.courseTitle },
      ];
    },
    videosMeta() {
      return this.$t("words.recorded-course-videos-ready-count", {
        ready: this.readiness.lessons_with_video_count,
        total: this.readiness.lessons_count,
      });
    },
    enrollmentsMeta() {
      return this.$t("words.recorded-course-enrollments-count", {
        count: this.readiness.enrollments_count || 0,
      });
    },
    setupCards() {
      const id = this.recordedCourse.id;
      return [
        {
          id: "details",
          title: this.$t("words.recorded-course-step-details"),
          description: this.$t("words.recorded-course-details-description"),
          complete: this.readiness.details_complete,
          href: this.route("back.settings.recorded-courses.details.edit", id),
        },
        {
          id: "schedule",
          title: this.$t("words.recorded-course-step-schedule"),
          description: this.$t("words.recorded-course-schedule-description"),
          complete: this.readiness.schedule_complete,
          href: this.route("back.settings.recorded-courses.schedule.edit", id),
        },
        {
          id: "lessons",
          title: this.$t("words.recorded-course-step-lessons"),
          description: this.$t("words.recorded-course-lessons-description"),
          complete: this.readiness.all_lessons_have_video,
          href: this.route("back.settings.recorded-courses.lessons.index", id),
          meta: this.videosMeta,
        },
        {
          id: "enrollments",
          title: this.$t("words.recorded-course-step-enrollments"),
          description: this.$t("words.recorded-course-company-manage-intro"),
          complete: (this.readiness.enrollments_count || 0) > 0,
          href: this.route("back.settings.recorded-courses.enrollments.index", id),
          meta: this.enrollmentsMeta,
        },
      ];
    },
  },
  methods: {
    lessonTitle(lesson) {
      return this.locale === "ar"
        ? lesson.title_ar
        : lesson.title_en || lesson.title_ar;
    },
  },
};
</script>
