<template>
  <app-layout>
    <div class="container px-6 mx-auto grid pt-6 max-w-3xl">
      <breadcrumb-container :crumbs="breadcrumbs" />
      <recorded-course-flash />

      <h1 class="font-bold text-2xl text-gray-900 mb-1">{{ lessonTitle }}</h1>
      <p class="text-sm text-gray-600 mb-6">
        {{ $t("words.recorded-course-lesson-video-page-description") }}
      </p>

      <recorded-course-step-nav
        :course-id="recordedCourse.id"
        current-step="lessons"
        :readiness="readiness"
      />

      <div
        class="mb-6 rounded-lg border p-4 flex flex-wrap items-center justify-between gap-3"
        :class="lesson.has_video ? 'border-green-200 bg-green-50' : 'border-amber-200 bg-amber-50'"
      >
        <div>
          <p class="text-sm font-semibold text-gray-800">
            {{
              lesson.has_video
                ? $t("words.recorded-course-lesson-video-ready-badge")
                : $t("words.recorded-course-lesson-video-missing-badge")
            }}
          </p>
          <p v-if="lesson.has_video && lesson.video_file_name" class="text-sm text-gray-600 mt-1">
            {{ lesson.video_file_name }}
            <span v-if="formattedSize" class="text-gray-500"> · {{ formattedSize }}</span>
          </p>
          <p v-else class="text-sm text-amber-900 mt-1">
            {{ $t("words.recorded-course-lesson-upload-video-hint") }}
          </p>
        </div>
      </div>

      <div v-if="lesson.has_video && lesson.video_stream_url" class="mb-8">
        <h2 class="text-lg font-semibold text-gray-800 mb-3">
          {{ $t("words.recorded-course-lesson-preview") }}
        </h2>
        <video
          :key="lesson.video_stream_url"
          :src="lesson.video_stream_url"
          controls
          class="w-full rounded-lg bg-black shadow"
        />
      </div>

      <div class="bg-white rounded-lg shadow border border-gray-100 p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-2">
          {{
            lesson.has_video
              ? $t("words.recorded-course-lesson-replace-video")
              : $t("words.recorded-course-lesson-upload-video")
          }}
        </h2>
        <p class="text-sm text-gray-600 mb-4">
          {{ $t("words.recorded-course-lesson-video-optional") }}
        </p>

        <input
          id="lesson_video"
          ref="fileInput"
          type="file"
          accept="video/mp4,video/webm,video/quicktime"
          class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
          :disabled="uploading"
          @change="onFileChange"
        />

        <div v-if="selectedFileName" class="mt-3 text-sm text-gray-700">
          {{ selectedFileName }}
        </div>

        <div v-if="uploading" class="mt-4">
          <div class="flex items-center justify-between text-sm text-gray-700 mb-1">
            <span>{{ uploadStatusLabel }}</span>
            <span>{{ uploadPercent }}%</span>
          </div>
          <div class="h-2 w-full rounded-full bg-gray-200 overflow-hidden">
            <div
              class="h-full bg-indigo-600 transition-all duration-300"
              :style="{ width: uploadPercent + '%' }"
            />
          </div>
        </div>

        <jet-input-error :message="videoError" class="mt-3" />

        <div class="mt-6 flex flex-wrap gap-3">
          <jet-button
            type="button"
            :disabled="!selectedFile || uploading"
            @click.native="submitUpload"
          >
            {{
              lesson.has_video
                ? $t("words.recorded-course-lesson-replace-video")
                : $t("words.recorded-course-lesson-upload-video")
            }}
          </jet-button>
          <inertia-link
            class="btn-gray"
            :href="route('back.settings.recorded-courses.lessons.index', recordedCourse.id)"
          >
            {{ $t("words.go-back") }}
          </inertia-link>
        </div>
      </div>
    </div>
  </app-layout>
</template>

<script>
import { Inertia } from "@inertiajs/inertia";
import AppLayout from "@/Layouts/AppLayout";
import BreadcrumbContainer from "@/Components/BreadcrumbContainer";
import RecordedCourseFlash from "@/Components/RecordedCourseFlash";
import RecordedCourseStepNav from "@/Components/RecordedCourseStepNav";
import JetButton from "@/Jetstream/Button";
import JetInputError from "@/Jetstream/InputError";
import {
  needsChunkedRecordedCourseVideoUpload,
  uploadRecordedCourseLessonVideoInChunks,
} from "@/helpers/recordedCourseChunkVideoUpload";

export default {
  metaInfo: { title: "Lesson video" },
  components: {
    AppLayout,
    BreadcrumbContainer,
    RecordedCourseFlash,
    RecordedCourseStepNav,
    JetButton,
    JetInputError,
  },
  props: {
    recordedCourse: { type: Object, required: true },
    readiness: { type: Object, required: true },
    lesson: { type: Object, required: true },
  },
  data() {
    return {
      selectedFile: null,
      selectedFileName: "",
      uploading: false,
      uploadPhase: "idle",
      uploadPercent: 0,
      videoError: "",
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
    lessonTitle() {
      return this.locale === "ar"
        ? this.lesson.title_ar
        : this.lesson.title_en || this.lesson.title_ar;
    },
    formattedSize() {
      if (!this.lesson.video_size) {
        return "";
      }
      const mb = this.lesson.video_size / (1024 * 1024);
      return mb >= 1 ? `${mb.toFixed(1)} MB` : `${Math.round(this.lesson.video_size / 1024)} KB`;
    },
    uploadStatusLabel() {
      if (this.uploadPhase === "saving") {
        return this.$t("words.recorded-course-upload-saving");
      }
      return this.$t("words.recorded-course-upload-progress");
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
        {
          title: "recorded-course-step-lessons",
          link: this.route("back.settings.recorded-courses.lessons.index", this.recordedCourse.id),
        },
        { title_raw: this.lessonTitle },
      ];
    },
  },
  methods: {
    onFileChange(e) {
      const file = e.target.files && e.target.files[0];
      this.selectedFile = file || null;
      this.selectedFileName = file ? file.name : "";
      this.videoError = "";
      this.uploadPercent = 0;
      this.uploadPhase = "idle";
    },
    async submitUpload() {
      if (!this.selectedFile || this.uploading) {
        return;
      }

      this.uploading = true;
      this.videoError = "";
      this.uploadPercent = 0;

      let video = null;
      let uploadToken = null;

      try {
        if (needsChunkedRecordedCourseVideoUpload(this.selectedFile)) {
          this.uploadPhase = "uploading";
          uploadToken = await uploadRecordedCourseLessonVideoInChunks(
            this.selectedFile,
            (fraction) => {
              this.uploadPercent = Math.round(fraction * 100);
            }
          );
        } else {
          video = this.selectedFile;
          this.uploadPhase = "uploading";
          this.uploadPercent = 50;
        }

        this.uploadPhase = "saving";
        this.uploadPercent = Math.max(this.uploadPercent, 95);

        await new Promise((resolve, reject) => {
          const url = this.route(
            "back.settings.recorded-courses.lessons.video.update",
            [this.recordedCourse.id, this.lesson.id]
          );
          const visitOptions = {
            preserveScroll: true,
            onSuccess: resolve,
            onError: (errors) => {
              this.videoError =
                (errors.video && errors.video[0]) ||
                (errors.upload_token && errors.upload_token[0]) ||
                this.$t("words.upload-failed");
              reject(new Error(this.videoError));
            },
            onFinish: () => {
              this.uploading = false;
              this.uploadPhase = "idle";
            },
          };

          // PHP only parses multipart file uploads on POST — not PUT.
          if (uploadToken) {
            Inertia.put(url, { upload_token: uploadToken }, visitOptions);
          } else {
            Inertia.post(
              url,
              { video, _method: "PUT" },
              { ...visitOptions, forceFormData: true }
            );
          }
        });

        this.uploadPercent = 100;
        this.selectedFile = null;
        this.selectedFileName = "";
        if (this.$refs.fileInput) {
          this.$refs.fileInput.value = "";
        }
      } catch (e) {
        this.uploading = false;
        this.uploadPhase = "idle";
        if (!this.videoError) {
          const msg =
            (e.response && e.response.data && e.response.data.message) ||
            e.message ||
            this.$t("words.upload-failed");
          this.videoError = msg;
        }
      }
    },
  },
};
</script>
