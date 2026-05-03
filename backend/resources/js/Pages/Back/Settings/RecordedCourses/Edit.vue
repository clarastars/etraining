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
            {{ $t("words.recorded-course-help-unlock") }}
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
                      :value="$t('words.recorded-course-lesson-video')"
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

      <div class="mt-10 p-6 bg-white rounded shadow">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">
          {{ $t("words.recorded-course-enrollments") }}
        </h2>
        <ul v-if="enrollments.length" class="mb-6 list-disc rtl:mr-6 ltr:ml-6 text-sm text-gray-700">
          <li v-for="row in enrollments" :key="row.id">
            {{ row.trainee_name || row.trainee_id }} — {{ row.enrolled_at }}
          </li>
        </ul>
        <p v-else class="mb-6 text-sm text-gray-500">{{ $t("words.nothing-is-here") }}</p>
        <form @submit.prevent="submitEnrollment" class="flex flex-wrap items-end gap-4">
          <div class="flex-1 min-w-[200px]">
            <jet-label for="trainee_id" :value="$t('words.recorded-course-trainee-id')" />
            <jet-input
              id="trainee_id"
              v-model="enrollmentForm.trainee_id"
              type="text"
              class="mt-1 block w-full"
              autocomplete="off"
            />
            <jet-input-error :message="enrollmentForm.error('trainee_id')" class="mt-2" />
          </div>
          <jet-button type="submit" :class="{ 'opacity-25': enrollmentForm.processing }" :disabled="enrollmentForm.processing">
            {{ $t("words.recorded-course-enroll-trainee") }}
          </jet-button>
        </form>
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
      weekdayValues: [0, 1, 2, 3, 4, 5, 6],
      enrollmentForm: this.$inertia.form({
        trainee_id: "",
      }),
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
    submitEnrollment() {
      this.enrollmentForm.post(
        this.route("back.settings.recorded-courses.enrollments.store", this.recordedCourse.id),
        {
          preserveScroll: true,
          onSuccess: () => {
            this.enrollmentForm.reset();
          },
        }
      );
    },
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
    buildPlainPayload() {
      return {
        name_ar: this.form.name_ar == null ? "" : String(this.form.name_ar),
        name_en: this.form.name_en == null ? "" : String(this.form.name_en),
        description:
          this.form.description == null ? "" : String(this.form.description),
        unlock_delay_hours: Number(this.form.unlock_delay_hours),
        allowed_weekdays: Array.isArray(this.form.allowed_weekdays)
          ? this.form.allowed_weekdays.map((d) => Number(d))
          : [],
        lessons: (this.form.lessons || []).map((lesson) => ({
          id: lesson.id || null,
          title_ar: lesson.title_ar == null ? "" : String(lesson.title_ar),
          title_en: lesson.title_en == null ? "" : String(lesson.title_en),
          video: lesson.video instanceof File ? lesson.video : null,
        })),
      };
    },
    submitForm() {
      Inertia.put(
        this.route(
          "back.settings.recorded-courses.update",
          this.recordedCourse.id
        ),
        this.buildPlainPayload(),
        {
          forceFormData: true,
          preserveScroll: true,
          onStart: () => {
            this.form.processing = true;
          },
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
          },
        }
      );
    },
  },
};
</script>
