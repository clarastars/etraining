<template>
  <app-layout>
    <div class="container px-6 mx-auto grid pt-6 max-w-4xl">
      <breadcrumb-container :crumbs="breadcrumbs" />
      <recorded-course-flash />

      <h1 class="font-bold text-2xl text-gray-900 mb-1">{{ courseTitle }}</h1>
      <p class="text-sm text-gray-600 mb-6">
        {{ $t("words.recorded-course-lessons-description") }}
      </p>

      <recorded-course-step-nav
        :course-id="recordedCourse.id"
        current-step="lessons"
        :readiness="readiness"
      />

      <div class="bg-white rounded-lg shadow border border-gray-100 overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-lg font-semibold text-gray-800">
            {{ $t("words.recorded-course-lessons-section") }}
          </h2>
          <p class="text-sm text-gray-600">
            {{ $t("words.recorded-course-videos-ready-count", {
              ready: readiness.lessons_with_video_count,
              total: readiness.lessons_count,
            }) }}
          </p>
        </div>

        <ul v-if="lessons.length" class="divide-y divide-gray-100">
          <li
            v-for="(lesson, index) in lessons"
            :key="lesson.id"
            class="px-6 py-4"
          >
            <div class="flex flex-wrap items-start justify-between gap-4">
              <div class="flex-1 min-w-[12rem]">
                <p class="text-xs text-gray-500 mb-1">
                  {{ $t("words.recorded-course-lessons-count") }} #{{ index + 1 }}
                </p>
                <template v-if="editingId === lesson.id">
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                      <jet-label :for="'edit_ar_' + lesson.id" :value="$t('words.name_ar')" />
                      <jet-input
                        :id="'edit_ar_' + lesson.id"
                        v-model="editForm.title_ar"
                        type="text"
                        class="mt-1 block w-full"
                      />
                      <jet-input-error :message="editForm.error('title_ar')" class="mt-1" />
                    </div>
                    <div>
                      <jet-label :for="'edit_en_' + lesson.id" :value="$t('words.name_en')" />
                      <jet-input
                        :id="'edit_en_' + lesson.id"
                        v-model="editForm.title_en"
                        type="text"
                        class="mt-1 block w-full"
                      />
                      <jet-input-error :message="editForm.error('title_en')" class="mt-1" />
                    </div>
                  </div>
                  <div class="mt-3 flex gap-2">
                    <jet-button
                      type="button"
                      class="text-sm"
                      :disabled="editForm.processing"
                      @click.native="saveEdit(lesson.id)"
                    >
                      {{ $t("words.save") }}
                    </jet-button>
                    <button
                      type="button"
                      class="text-sm text-gray-600 hover:text-gray-800"
                      @click="cancelEdit"
                    >
                      {{ $t("words.cancel") }}
                    </button>
                  </div>
                </template>
                <template v-else>
                  <p class="font-medium text-gray-900">{{ lessonTitle(lesson) }}</p>
                  <p v-if="lesson.video_file_name" class="text-xs text-gray-500 mt-1">
                    {{ lesson.video_file_name }}
                  </p>
                </template>
              </div>

              <div class="flex flex-wrap items-center gap-2">
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
                  class="btn-gray text-sm"
                  :href="
                    route('back.settings.recorded-courses.lessons.video.edit', [
                      recordedCourse.id,
                      lesson.id,
                    ])
                  "
                >
                  {{
                    lesson.has_video
                      ? $t("words.recorded-course-lesson-preview")
                      : $t("words.recorded-course-lesson-upload-video")
                  }}
                </inertia-link>
                <button
                  v-if="editingId !== lesson.id"
                  type="button"
                  class="text-sm text-indigo-600 hover:text-indigo-800"
                  @click="startEdit(lesson)"
                >
                  {{ $t("words.edit") }}
                </button>
                <button
                  v-if="lessons.length > 1"
                  type="button"
                  class="text-sm text-red-600 hover:text-red-800"
                  @click="confirmDelete(lesson.id)"
                >
                  {{ $t("words.delete") }}
                </button>
              </div>
            </div>
          </li>
        </ul>
        <p v-else class="px-6 py-8 text-sm text-gray-500">
          {{ $t("words.nothing-is-here") }}
        </p>
      </div>

      <div class="bg-white rounded-lg shadow border border-gray-100 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">
          {{ $t("words.recorded-course-add-lesson") }}
        </h3>
        <form @submit.prevent="submitAdd">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <jet-label for="new_title_ar" :value="$t('words.name_ar')" />
              <jet-input
                id="new_title_ar"
                v-model="addForm.title_ar"
                type="text"
                class="mt-1 block w-full"
              />
              <jet-input-error :message="addForm.error('title_ar')" class="mt-2" />
            </div>
            <div>
              <jet-label for="new_title_en" :value="$t('words.name_en')" />
              <jet-input
                id="new_title_en"
                v-model="addForm.title_en"
                type="text"
                class="mt-1 block w-full"
              />
              <jet-input-error :message="addForm.error('title_en')" class="mt-2" />
            </div>
          </div>
          <div class="mt-4 flex gap-3">
            <jet-button :disabled="addForm.processing">
              {{ $t("words.recorded-course-add-lesson") }}
            </jet-button>
            <inertia-link
              class="btn-gray"
              :href="route('back.settings.recorded-courses.show', recordedCourse.id)"
            >
              {{ $t("words.go-back") }}
            </inertia-link>
          </div>
        </form>
      </div>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout";
import BreadcrumbContainer from "@/Components/BreadcrumbContainer";
import RecordedCourseFlash from "@/Components/RecordedCourseFlash";
import RecordedCourseStepNav from "@/Components/RecordedCourseStepNav";
import JetButton from "@/Jetstream/Button";
import JetInput from "@/Jetstream/Input";
import JetInputError from "@/Jetstream/InputError";
import JetLabel from "@/Jetstream/Label";

export default {
  metaInfo: { title: "Course lessons" },
  components: {
    AppLayout,
    BreadcrumbContainer,
    RecordedCourseFlash,
    RecordedCourseStepNav,
    JetButton,
    JetInput,
    JetInputError,
    JetLabel,
  },
  props: {
    recordedCourse: { type: Object, required: true },
    readiness: { type: Object, required: true },
    lessons: { type: Array, default: () => [] },
  },
  data() {
    return {
      editingId: null,
      addForm: this.$inertia.form({
        title_ar: "",
        title_en: "",
      }),
      editForm: this.$inertia.form({
        title_ar: "",
        title_en: "",
      }),
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
        { title: "recorded-course-step-lessons" },
      ];
    },
  },
  methods: {
    lessonTitle(lesson) {
      return this.locale === "ar"
        ? lesson.title_ar
        : lesson.title_en || lesson.title_ar;
    },
    submitAdd() {
      this.addForm.post(
        this.route("back.settings.recorded-courses.lessons.store", this.recordedCourse.id),
        {
          preserveScroll: true,
          onSuccess: () => {
            this.addForm.reset();
          },
        }
      );
    },
    startEdit(lesson) {
      this.editingId = lesson.id;
      this.editForm.title_ar = lesson.title_ar;
      this.editForm.title_en = lesson.title_en || "";
      this.editForm.clearErrors();
    },
    cancelEdit() {
      this.editingId = null;
      this.editForm.reset();
    },
    saveEdit(lessonId) {
      this.editForm.put(
        this.route("back.settings.recorded-courses.lessons.update", [
          this.recordedCourse.id,
          lessonId,
        ]),
        {
          preserveScroll: true,
          onSuccess: () => {
            this.editingId = null;
          },
        }
      );
    },
    confirmDelete(lessonId) {
      if (!confirm(this.$t("words.delete") + "?")) {
        return;
      }
      this.$inertia.delete(
        this.route("back.settings.recorded-courses.lessons.destroy", [
          this.recordedCourse.id,
          lessonId,
        ]),
        { preserveScroll: true }
      );
    },
  },
};
</script>
