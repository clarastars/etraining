<template>
  <app-layout>
    <div class="container px-6 mx-auto grid pt-6 max-w-3xl">
      <breadcrumb-container :crumbs="breadcrumbs" />
      <recorded-course-flash />

      <h1 class="font-bold text-2xl text-gray-900 mb-1">{{ courseTitle }}</h1>
      <p class="text-sm text-gray-600 mb-6">
        {{ $t("words.recorded-course-step-details") }}
      </p>

      <recorded-course-step-nav
        :course-id="recordedCourse.id"
        current-step="details"
        :readiness="readiness"
      />

      <jet-form-section @submitted="submitForm">
        <template #title>{{ $t("words.recorded-course-step-details") }}</template>
        <template #description>
          {{ $t("words.recorded-course-details-description") }}
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
            <jet-input-error :message="form.error('name_ar')" class="mt-2" />
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
            <jet-input-error :message="form.error('name_en')" class="mt-2" />
          </div>
          <div class="col-span-6 sm:col-span-4">
            <jet-label for="description" :value="$t('words.description')" />
            <textarea
              id="description"
              v-model="form.description"
              class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm"
              rows="4"
            />
            <jet-input-error :message="form.error('description')" class="mt-2" />
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
  metaInfo: { title: "Course details" },
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
      form: this.$inertia.form({
        name_ar: this.recordedCourse.name_ar,
        name_en: this.recordedCourse.name_en,
        description: this.recordedCourse.description || "",
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
        { title: "recorded-course-step-details" },
      ];
    },
  },
  methods: {
    submitForm() {
      this.form.put(
        this.route("back.settings.recorded-courses.details.update", this.recordedCourse.id),
        { preserveScroll: true }
      );
    },
  },
};
</script>
