<template>
  <app-layout>
    <div class="container px-6 mx-auto grid pt-6 max-w-3xl">
      <breadcrumb-container :crumbs="breadcrumbs" />
      <recorded-course-flash />

      <h1 class="font-bold text-2xl text-gray-900 mb-1">{{ courseTitle }}</h1>
      <p class="text-sm text-gray-600 mb-6">
        {{ $t("words.recorded-course-step-schedule") }}
      </p>

      <recorded-course-step-nav
        :course-id="recordedCourse.id"
        current-step="schedule"
        :readiness="readiness"
      />

      <jet-form-section @submitted="submitForm">
        <template #title>{{ $t("words.recorded-course-step-schedule") }}</template>
        <template #description>
          {{ $t("words.recorded-course-schedule-description") }}
        </template>
        <template #form>
          <div class="col-span-6 sm:col-span-3">
            <jet-label for="unlock_delay_hours" :value="$t('words.unlock-delay-hours')" />
            <jet-input
              id="unlock_delay_hours"
              v-model.number="form.unlock_delay_hours"
              type="number"
              min="1"
              max="8760"
              class="mt-1 block w-full"
            />
            <jet-input-error :message="form.error('unlock_delay_hours')" class="mt-2" />
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
            <jet-input-error :message="form.error('allowed_weekdays')" class="mt-2" />
          </div>
        </template>
        <template #actions>
          <inertia-link
            class="btn-gray mr-3"
            :href="route('back.settings.recorded-courses.show', recordedCourse.id)"
          >
            {{ $t("words.go-back") }}
          </inertia-link>
          <jet-action-message :on="form.recentlySuccessful" class="mr-3">
            {{ $t("words.saved-successfully") }}
          </jet-action-message>
          <jet-button :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
            {{ $t("words.save") }}
          </jet-button>
        </template>
      </jet-form-section>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout";
import BreadcrumbContainer from "@/Components/BreadcrumbContainer";
import RecordedCourseFlash from "@/Components/RecordedCourseFlash";
import RecordedCourseStepNav from "@/Components/RecordedCourseStepNav";
import JetActionMessage from "@/Jetstream/ActionMessage";
import JetButton from "@/Jetstream/Button";
import JetFormSection from "@/Jetstream/FormSection";
import JetInput from "@/Jetstream/Input";
import JetInputError from "@/Jetstream/InputError";
import JetLabel from "@/Jetstream/Label";

export default {
  metaInfo: { title: "Course schedule" },
  components: {
    AppLayout,
    BreadcrumbContainer,
    RecordedCourseFlash,
    RecordedCourseStepNav,
    JetActionMessage,
    JetButton,
    JetFormSection,
    JetInput,
    JetInputError,
    JetLabel,
  },
  props: {
    recordedCourse: { type: Object, required: true },
    readiness: { type: Object, required: true },
  },
  data() {
    return {
      weekdayValues: [0, 1, 2, 3, 4, 5, 6],
      form: this.$inertia.form({
        unlock_delay_hours: this.recordedCourse.unlock_delay_hours,
        allowed_weekdays: [...(this.recordedCourse.allowed_weekdays || [])],
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
        { title: "recorded-course-step-schedule" },
      ];
    },
  },
  methods: {
    submitForm() {
      this.form.put(
        this.route("back.settings.recorded-courses.schedule.update", this.recordedCourse.id),
        { preserveScroll: true }
      );
    },
  },
};
</script>
