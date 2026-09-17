<template>
  <app-layout>
    <div class="container px-6 mx-auto grid pt-6 max-w-4xl">
      <breadcrumb-container :crumbs="breadcrumbs" />

      <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
        <div>
          <p class="text-sm text-indigo-700 font-mono font-semibold mb-1">
            {{ disclosureRequest.number }}
          </p>
          <h1 class="font-bold text-2xl text-gray-900">
            {{ $t("words.training-disclosure-request") }}
          </h1>
        </div>
        <button
          type="button"
          class="text-sm text-red-600 hover:underline"
          @click="destroyRequest"
        >
          {{ $t("words.delete") }}
        </button>
      </div>

      <form class="bg-white rounded-lg border border-gray-200 shadow-sm p-5 space-y-6" @submit.prevent="submit">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            {{ $t("words.company") }}
          </label>
          <company-search-select
            :value="selectedCompany"
            :placeholder="$t('words.please-select')"
            @input="onCompanySelected"
          />
          <p v-if="form.errors.company_name" class="mt-1 text-sm text-red-600">
            {{ form.errors.company_name }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            {{ $t("words.training-disclosure-request-trainees-count") }}
          </label>
          <input
            v-model.number="form.trainees_count"
            type="number"
            min="1"
            class="w-40 border-gray-300 rounded-md shadow-sm text-sm"
          />
          <p v-if="form.errors.trainees_count" class="mt-1 text-sm text-red-600">
            {{ form.errors.trainees_count }}
          </p>
        </div>

        <div>
          <div class="flex items-center justify-between gap-3 mb-2">
            <label class="block text-sm font-medium text-gray-700">
              {{ $t("words.training-disclosure-request-trainees-draft") }}
            </label>
            <button type="button" class="btn-gray text-sm" @click="addTraineeRow">
              {{ $t("words.add") }}
            </button>
          </div>
          <div class="overflow-x-auto border rounded-md">
            <table class="min-w-full text-sm">
              <thead class="bg-gray-50 text-left rtl:text-right">
                <tr>
                  <th class="px-3 py-2 font-semibold text-gray-700">{{ $t("words.name") }}</th>
                  <th class="px-3 py-2 font-semibold text-gray-700">{{ $t("words.phone") }}</th>
                  <th class="px-3 py-2 font-semibold text-gray-700">{{ $t("words.email") }}</th>
                  <th class="px-3 py-2 w-16"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, index) in form.trainees" :key="index" class="border-t">
                  <td class="px-2 py-2">
                    <input
                      v-model="row.name"
                      type="text"
                      class="w-full border-gray-300 rounded-md shadow-sm text-sm"
                    />
                  </td>
                  <td class="px-2 py-2">
                    <input
                      v-model="row.phone"
                      type="text"
                      class="w-full border-gray-300 rounded-md shadow-sm text-sm"
                      dir="ltr"
                    />
                  </td>
                  <td class="px-2 py-2">
                    <input
                      v-model="row.email"
                      type="email"
                      class="w-full border-gray-300 rounded-md shadow-sm text-sm"
                      dir="ltr"
                    />
                  </td>
                  <td class="px-2 py-2 text-center">
                    <button
                      type="button"
                      class="text-red-600 text-xs hover:underline"
                      :disabled="form.trainees.length <= 1"
                      @click="removeTraineeRow(index)"
                    >
                      {{ $t("words.delete") }}
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-if="form.errors.trainees" class="mt-1 text-sm text-red-600">
            {{ form.errors.trainees }}
          </p>
        </div>

        <div class="flex flex-wrap gap-3">
          <button type="submit" class="btn-blue" :disabled="form.processing">
            {{ $t("words.save") }}
          </button>
          <inertia-link
            class="btn-gray"
            :href="route('back.training-disclosure.requests.index')"
          >
            {{ $t("words.go-back") }}
          </inertia-link>
        </div>
      </form>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout";
import BreadcrumbContainer from "@/Components/BreadcrumbContainer";
import CompanySearchSelect from "@/Components/CompanySearchSelect";

export default {
  metaInfo: { title: "Disclosure request" },
  components: {
    AppLayout,
    BreadcrumbContainer,
    CompanySearchSelect,
  },
  props: {
    disclosureRequest: { type: Object, required: true },
  },
  data() {
    const company = this.disclosureRequest.company
      ? {
          id: this.disclosureRequest.company.id,
          name_ar: this.disclosureRequest.company.name_ar || this.disclosureRequest.company_name,
          name_en: this.disclosureRequest.company.name_en || "",
        }
      : this.disclosureRequest.company_name
        ? {
            id: this.disclosureRequest.company_id || "draft",
            name_ar: this.disclosureRequest.company_name,
            name_en: "",
          }
        : null;

    const trainees =
      this.disclosureRequest.trainees && this.disclosureRequest.trainees.length
        ? this.disclosureRequest.trainees.map((t) => ({
            name: t.name || "",
            phone: t.phone || "",
            email: t.email || "",
          }))
        : [{ name: "", phone: "", email: "" }];

    return {
      selectedCompany: company,
      form: this.$inertia.form({
        company_id: this.disclosureRequest.company_id,
        company_name: this.disclosureRequest.company_name,
        trainees_count: this.disclosureRequest.trainees_count,
        trainees,
      }),
    };
  },
  computed: {
    breadcrumbs() {
      return [
        { title: "dashboard", link: this.route("dashboard") },
        { title: "training-disclosure", link: this.route("back.training-disclosure.index") },
        {
          title: "training-disclosure-requests",
          link: this.route("back.training-disclosure.requests.index"),
        },
        { title_raw: this.disclosureRequest.number },
      ];
    },
  },
  methods: {
    onCompanySelected(company) {
      this.selectedCompany = company;
      if (!company || company.id === "draft") {
        this.form.company_id = null;
        this.form.company_name = company && company.id === "draft" ? company.name_ar : "";
        if (!company) this.form.company_name = "";
        return;
      }
      this.form.company_id = company.id;
      this.form.company_name = company.name_ar || company.name_en || "";
    },
    addTraineeRow() {
      this.form.trainees.push({ name: "", phone: "", email: "" });
    },
    removeTraineeRow(index) {
      if (this.form.trainees.length <= 1) return;
      this.form.trainees.splice(index, 1);
    },
    submit() {
      this.form.put(
        this.route("back.training-disclosure.requests.update", this.disclosureRequest.id)
      );
    },
    destroyRequest() {
      if (!confirm(this.$t("words.are-you-sure"))) return;
      this.$inertia.delete(
        this.route("back.training-disclosure.requests.destroy", this.disclosureRequest.id)
      );
    },
  },
};
</script>
