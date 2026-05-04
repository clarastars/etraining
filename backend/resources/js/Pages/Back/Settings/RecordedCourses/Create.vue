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
          { title: 'recorded-course-new' },
        ]"
      ></breadcrumb-container>
      <div class="mt-4">
        <jet-form-section @submitted="submitForm">
          <template #title>{{ $t("words.recorded-course-new") }}</template>
          <template #description>
            <span>{{ $t("words.recorded-course-help-unlock") }}</span>
            <span class="block mt-2 text-gray-600">{{
              $t("words.recorded-course-create-videos-on-edit")
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
                :key="index"
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
  metaInfo: { title: "New recorded course" },
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
    defaultWeekdays: {
      type: Array,
      default: () => [0, 1, 2, 3, 4, 5, 6],
    },
  },
  data() {
    return {
      weekdayValues: [0, 1, 2, 3, 4, 5, 6],
      form: this.$inertia.form(
        {
          name_ar: "",
          name_en: "",
          description: "",
          unlock_delay_hours: 24,
          allowed_weekdays: [...this.defaultWeekdays],
          lessons: [
            {
              title_ar: "",
              title_en: "",
            },
          ],
        },
        {
          bag: "createRecordedCourse",
        }
      ),
    };
  },
  methods: {
    addLesson() {
      this.form.lessons.push({
        title_ar: "",
        title_en: "",
      });
    },
    removeLesson(index) {
      this.form.lessons.splice(index, 1);
    },
    buildPayload() {
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
          title_ar: lesson.title_ar == null ? "" : String(lesson.title_ar),
          title_en: lesson.title_en == null ? "" : String(lesson.title_en),
        })),
      };
    },
    submitForm() {
      Inertia.post(
        this.route("back.settings.recorded-courses.store"),
        this.buildPayload(),
        {
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
