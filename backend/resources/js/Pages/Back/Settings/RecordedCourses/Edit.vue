<template>
  <app-layout>
    <div class="container px-6 mx-auto grid pt-6">
      <breadcrumb-container
        :crumbs="[
          { title: 'dashboard', link: route('dashboard') },
          { title: 'settings', link: route('back.settings') },
          {
            title: 'recorded-courses',
            link: route('back.settings.recorded-courses.index'),
          },
          { title: 'recorded-course-edit' },
        ]"
      ></breadcrumb-container>
      <div v-if="$page.props.flash && $page.props.flash.success" class="mb-4 p-3 bg-green-100 text-green-800 rounded">
        {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash && $page.props.flash.warning" class="mb-4 p-3 bg-yellow-100 text-yellow-800 rounded">
        {{ $page.props.flash.warning }}
      </div>

      <div class="mt-4">
        <jet-form-section @submitted="submitForm">
          <template #title>{{ $t("words.recorded-course-edit") }}</template>
          <template #description>
            <span>{{ $t("words.recorded-course-help-unlock") }}</span>
            <span class="block mt-2 text-gray-600">{{
              $t("words.recorded-course-edit-lesson-videos-hint")
            }}</span>
          </template>
          <template #form>
            <div class="col-span-6 sm:col-span-4">
              <jet-label for="name_ar" :value="$t('words.name_ar')" />
              <jet-input
                id="name_ar"
                v-model="form.name_ar"
                type="text"
                class="mt-1 block w-full"
                autocomplete="off"
                autofocus
              />
              <jet-input-error
                :message="form.error('name_ar')"
                class="mt-2"
              />
            </div>
            <div class="col-span-6 sm:col-span-4">
              <jet-label for="name_en" :value="$t('words.name_en')" />
              <jet-input
                id="name_en"
                v-model="form.name_en"
                type="text"
                class="mt-1 block w-full"
                autocomplete="off"
              />
              <jet-input-error
                :message="form.error('name_en')"
                class="mt-2"
              />
            </div>
            <div class="col-span-6 sm:col-span-4">
              <jet-label for="description" :value="$t('words.description')" />
              <textarea
                id="description"
                v-model="form.description"
                class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm"
                rows="3"
              />
              <jet-input-error
                :message="form.error('description')"
                class="mt-2"
              />
            </div>
            <div class="col-span-6 sm:col-span-2">
              <jet-label
                for="unlock_delay_hours"
                :value="$t('words.unlock-delay-hours')"
              />
              <jet-input
                id="unlock_delay_hours"
                v-model.number="form.unlock_delay_hours"
                type="number"
                min="1"
                max="8760"
                class="mt-1 block w-full"
              />
              <jet-input-error
                :message="form.error('unlock_delay_hours')"
                class="mt-2"
              />
            </div>
            <div class="col-span-6">
              <jet-label :value="$t('words.allowed-weekdays')" />
              <div class="mt-2 flex flex-wrap gap-4">
                <label
                  v-for="d in weekdayValues"
                  :key="d"
                  class="inline-flex items-center"
                >
                  <input
                    v-model="form.allowed_weekdays"
                    type="checkbox"
                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                    :value="d"
                  />
                  <span class="rtl:mr-2 ltr:ml-2 text-sm text-gray-700">{{
                    $t("words.recorded-course-weekday-" + d)
                  }}</span>
                </label>
              </div>
              <jet-input-error
                :message="form.error('allowed_weekdays')"
                class="mt-2"
              />
            </div>
            <div class="col-span-6">
              <p class="text-sm font-medium text-gray-700 mb-2">
                {{ $t("words.recorded-course-lessons-section") }}
              </p>
              <div
                v-for="(lesson, index) in form.lessons"
                :key="lesson.id || 'new-' + index"
                class="mb-6 p-4 border border-gray-200 rounded-lg"
              >
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <jet-label
                      :for="'title_ar_' + index"
                      :value="$t('words.name_ar')"
                    />
                    <jet-input
                      :id="'title_ar_' + index"
                      v-model="lesson.title_ar"
                      type="text"
                      class="mt-1 block w-full"
                    />
                    <jet-input-error
                      :message="form.error('lessons.' + index + '.title_ar')"
                      class="mt-2"
                    />
                  </div>
                  <div>
                    <jet-label
                      :for="'title_en_' + index"
                      :value="$t('words.name_en')"
                    />
                    <jet-input
                      :id="'title_en_' + index"
                      v-model="lesson.title_en"
                      type="text"
                      class="mt-1 block w-full"
                    />
                    <jet-input-error
                      :message="form.error('lessons.' + index + '.title_en')"
                      class="mt-2"
                    />
                  </div>
                  <div class="sm:col-span-2">
                    <p
                      v-if="lesson.id && lesson.has_video && lesson.video_file_name"
                      class="text-sm text-gray-600 mb-1"
                    >
                      {{ $t("words.recorded-course-current-video") }}:
                      {{ lesson.video_file_name }}
                    </p>
                    <jet-label
                      :for="'video_' + index"
                      :value="$t('words.recorded-course-lesson-video-optional')"
                    />
                    <input
                      :id="'video_' + index"
                      type="file"
                      accept="video/mp4,video/webm,video/quicktime"
                      class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                      @change="onVideoChange($event, index)"
                    />
                    <jet-input-error
                      :message="form.error('lessons.' + index + '.video')"
                      class="mt-2"
                    />
                  </div>
                </div>
                <div class="mt-2">
                  <button
                    v-if="form.lessons.length > 1"
                    type="button"
                    class="text-sm text-red-600 hover:text-red-800"
                    @click.prevent="removeLesson(index)"
                  >
                    {{ $t("words.delete") }}
                  </button>
                </div>
              </div>
              <button
                type="button"
                class="btn-gray text-sm"
                @click.prevent="addLesson"
              >
                {{ $t("words.recorded-course-add-lesson") }}
              </button>
            </div>
          </template>
          <template #actions>
            <p
              v-if="form.processing && chunkUploadLessonIndex != null"
              class="text-sm text-gray-600 mb-2 w-full"
            >
              {{ $t("words.recorded-course-uploading-large-videos") }}
              ({{ $t("words.recorded-course-lessons-count") }}
              {{ chunkUploadLessonIndex }}/{{ form.lessons.length }} ·
              {{ chunkUploadPercent }}%)
            </p>
            <jet-action-message :on="form.recentlySuccessful" class="mr-3">
              {{ $t("words.saved-successfully") }}
            </jet-action-message>
            <jet-button
              :class="{ 'opacity-25': form.processing }"
              :disabled="form.processing"
            >
              {{ $t("words.save") }}
            </jet-button>
          </template>
        </jet-form-section>
      </div>

      <div class="mt-10 p-6 bg-white rounded shadow overflow-x-auto">
        <h2 class="text-lg font-semibold text-gray-800 mb-2">
          {{ $t("words.recorded-course-enrollments-progress") }}
        </h2>
        <p class="text-sm text-gray-600 mb-4">
          {{ $t("words.recorded-course-enrollments-hint-from-trainee") }}
        </p>
        <template v-if="enrollments.length">
          <table class="min-w-full text-sm border border-gray-200 rounded-lg overflow-hidden">
            <thead class="bg-gray-50 text-left rtl:text-right">
              <tr>
                <th class="border-b border-gray-200 px-3 py-2 font-semibold text-gray-700">
                  {{ $t("words.name") }}
                </th>
                <th class="border-b border-gray-200 px-3 py-2 font-semibold text-gray-700 whitespace-nowrap">
                  {{ $t("words.recorded-course-enrolled-at") }}
                </th>
                <th
                  v-for="lesson in lessons"
                  :key="'h-' + lesson.id"
                  class="border-b border-gray-200 px-2 py-2 font-semibold text-gray-700 text-center min-w-[7rem]"
                >
                  <span class="line-clamp-2">{{ lesson.title_en || lesson.title_ar }}</span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in enrollments"
                :key="row.id"
                class="border-b border-gray-100 last:border-0"
              >
                <td class="px-3 py-2 text-gray-900">
                  {{ row.trainee_name || row.trainee_id }}
                </td>
                <td class="px-3 py-2 text-gray-600 whitespace-nowrap text-xs">
                  {{ row.enrolled_at }}
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
              </tr>
            </tbody>
          </table>
        </template>
        <p v-else class="text-sm text-gray-500">{{ $t("words.nothing-is-here") }}</p>
      </div>
    </div>
  </app-layout>
</template>

<script>
import { Inertia } from "@inertiajs/inertia";
import AppLayout from "@/Layouts/AppLayout";
import JetActionMessage from "@/Jetstream/ActionMessage";
import JetButton from "@/Jetstream/Button";
import JetFormSection from "@/Jetstream/FormSection";
import JetInput from "@/Jetstream/Input";
import JetInputError from "@/Jetstream/InputError";
import JetLabel from "@/Jetstream/Label";
import BreadcrumbContainer from "@/Components/BreadcrumbContainer";
import {
  needsChunkedRecordedCourseVideoUpload,
  uploadRecordedCourseLessonVideoInChunks,
} from "@/helpers/recordedCourseChunkVideoUpload";

export default {
  metaInfo: { title: "Edit recorded course" },
  components: {
    AppLayout,
    JetActionMessage,
    JetButton,
    JetFormSection,
    JetInput,
    JetInputError,
    JetLabel,
    BreadcrumbContainer,
  },
  props: {
    recordedCourse: {
      type: Object,
      required: true,
    },
    lessons: {
      type: Array,
      required: true,
    },
    enrollments: {
      type: Array,
      default: () => [],
    },
  },
  data() {
    return {
      chunkUploadLessonIndex: null,
      chunkUploadPercent: 0,
      weekdayValues: [0, 1, 2, 3, 4, 5, 6],
      form: this.$inertia.form({
        name_ar: this.recordedCourse.name_ar,
        name_en: this.recordedCourse.name_en,
        description: this.recordedCourse.description || "",
        unlock_delay_hours: this.recordedCourse.unlock_delay_hours,
        allowed_weekdays: [...(this.recordedCourse.allowed_weekdays || [])],
        lessons: this.lessons.map((l) => ({
          id: l.id,
          title_ar: l.title_ar,
          title_en: l.title_en || "",
          video: null,
          has_video: l.has_video,
          video_file_name: l.video_file_name,
        })),
      }),
    };
  },
  methods: {
    onVideoChange(e, index) {
      const file = e.target.files && e.target.files[0];
      this.$set(this.form.lessons[index], "video", file || null);
    },
    addLesson() {
      this.form.lessons.push({
        id: null,
        title_ar: "",
        title_en: "",
        video: null,
        has_video: false,
        video_file_name: null,
      });
    },
    removeLesson(index) {
      this.form.lessons.splice(index, 1);
    },
    courseMetaPayload() {
      return {
        name_ar: this.form.name_ar == null ? "" : String(this.form.name_ar),
        name_en: this.form.name_en == null ? "" : String(this.form.name_en),
        description:
          this.form.description == null ? "" : String(this.form.description),
        unlock_delay_hours: Number(this.form.unlock_delay_hours),
        allowed_weekdays: Array.isArray(this.form.allowed_weekdays)
          ? this.form.allowed_weekdays.map((d) => Number(d))
          : [],
      };
    },
    async submitForm() {
      this.form.processing = true;
      this.form.clearErrors();
      this.chunkUploadLessonIndex = null;
      this.chunkUploadPercent = 0;

      let lessonsPayload;
      try {
        lessonsPayload = [];
        for (let i = 0; i < this.form.lessons.length; i++) {
          const lesson = this.form.lessons[i];
          let video = null;
          let uploadToken = null;
          if (lesson.video instanceof File) {
            if (needsChunkedRecordedCourseVideoUpload(lesson.video)) {
              this.chunkUploadLessonIndex = i + 1;
              uploadToken = await uploadRecordedCourseLessonVideoInChunks(
                lesson.video,
                (fraction) => {
                  this.chunkUploadPercent = Math.round(fraction * 100);
                }
              );
            } else {
              video = lesson.video;
            }
          }
          lessonsPayload.push({
            id: lesson.id || null,
            title_ar: lesson.title_ar == null ? "" : String(lesson.title_ar),
            title_en: lesson.title_en == null ? "" : String(lesson.title_en),
            video,
            upload_token: uploadToken,
          });
        }
      } catch (e) {
        this.form.processing = false;
        this.chunkUploadLessonIndex = null;
        this.chunkUploadPercent = 0;
        const msg =
          (e.response && e.response.data && e.response.data.message) ||
          e.message ||
          this.$t("words.upload-failed");
        window.alert(msg);
        return;
      }

      Inertia.put(
        this.route(
          "back.settings.recorded-courses.update",
          this.recordedCourse.id
        ),
        { ...this.courseMetaPayload(), lessons: lessonsPayload },
        {
          forceFormData: true,
          preserveScroll: true,
          onError: (errors) => {
            this.form.processing = false;
            this.form.errors = errors;
            this.form.hasErrors = true;
          },
          onSuccess: () => {
            this.form.processing = false;
            this.form.clearErrors();
          },
          onFinish: () => {
            this.form.processing = false;
            this.chunkUploadLessonIndex = null;
            this.chunkUploadPercent = 0;
          },
        }
      );
    },
  },
};
</script>
