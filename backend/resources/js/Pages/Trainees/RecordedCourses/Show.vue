<template>
  <app-layout>
    <div class="container px-6 mx-auto grid pt-6">
      <breadcrumb-container
        :crumbs="[
          { title: 'dashboard', link: route('dashboard') },
          { title: 'recorded-courses-my-courses', link: route('recorded-courses.index') },
          { title_raw: courseTitle },
        ]"
      />

      <h1 class="mb-4 font-bold text-3xl">{{ courseTitle }}</h1>

      <p class="mb-6 text-sm text-gray-600">
        {{ $t("words.unlock-delay-hours") }}: {{ course.unlock_delay_hours }}
      </p>

      <div v-if="!can_unlock_today && next_pending_lesson_id" class="mb-6 p-4 bg-gray-100 rounded text-gray-800">
        {{ $t("words.recorded-course-no-allowed-weekday-today") }}
      </div>

      <div v-if="can_unlock_today && next_pending_lesson_id" class="mb-6">
        <form @submit.prevent="submitUnlock">
          <button
            type="submit"
            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
          >
            {{ $t("words.recorded-course-unlock-today-video") }}
          </button>
        </form>
      </div>

      <div v-if="!next_pending_lesson_id" class="mb-6 p-4 bg-green-50 rounded text-green-800">
        {{ $t("words.recorded-course-all-lessons-done") }}
      </div>

      <div class="space-y-10">
        <div
          v-for="lesson in lessons"
          :key="lesson.id"
          class="p-6 bg-white rounded shadow border border-gray-100"
        >
          <h2 class="text-lg font-semibold mb-2">
            {{ locale === "ar" ? lesson.title_ar : (lesson.title_en || lesson.title_ar) }}
          </h2>
          <p v-if="lesson.unlocked_at" class="text-xs text-gray-500 mb-2">
            {{ $t("words.recorded-course-unlocked-at") }}: {{ lesson.unlocked_at }}
          </p>
          <p v-if="lesson.completed_at" class="text-xs text-green-700 mb-2">
            {{ $t("words.recorded-course-completed-at") }}: {{ lesson.completed_at }}
          </p>

          <div v-if="lesson.can_stream && lesson.stream_url" class="mt-4">
            <video :src="lesson.stream_url" controls class="w-full max-w-3xl rounded bg-black" />
          </div>
          <p v-else-if="lesson.unlocked_at && !lesson.can_stream" class="text-sm text-gray-500">
            {{ $t("words.recorded-course-lesson-video") }} — {{ $t("words.nothing-is-here") }}
          </p>

          <form
            v-if="lesson.unlocked_at && !lesson.completed_at"
            class="mt-4"
            @submit.prevent="submitComplete(lesson.id)"
          >
            <button
              type="submit"
              class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700"
            >
              {{ $t("words.recorded-course-mark-complete") }}
            </button>
          </form>
        </div>
      </div>
    </div>
  </app-layout>
</template>

<script>
import { Inertia } from "@inertiajs/inertia";
import AppLayout from "@/Layouts/AppLayout";
import BreadcrumbContainer from "@/Components/BreadcrumbContainer";

export default {
  metaInfo: { title: "Recorded course" },
  components: { AppLayout, BreadcrumbContainer },
  props: {
    enrollment: { type: Object, required: true },
    course: { type: Object, required: true },
    lessons: { type: Array, required: true },
    next_pending_lesson_id: { default: null },
    can_unlock_today: { type: Boolean, default: false },
  },
  computed: {
    locale() {
      return this.$page.props.locale || "ar";
    },
    courseTitle() {
      return this.locale === "ar" ? this.course.name_ar : this.course.name_en;
    },
  },
  methods: {
    submitUnlock() {
      Inertia.post(
        this.route("recorded-courses.enrollments.unlock", this.enrollment.id),
        {},
        { preserveScroll: true }
      );
    },
    submitComplete(lessonId) {
      Inertia.post(
        this.route("recorded-courses.enrollments.lessons.complete", {
          enrollment: this.enrollment.id,
          lesson: lessonId,
        }),
        {},
        { preserveScroll: true }
      );
    },
  },
};
</script>
