<template>
    <div class="relative" :class="{ 'company-search-select--compact': compact }">
        <div class="relative">
            <input
                type="text"
                :placeholder="placeholder"
                v-model="searchQuery"
                @input="handleSearch"
                @focus="handleFocus"
                @blur="handleBlur"
                class="company-search-input w-full border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                :class="[
                    compact ? 'text-sm py-1.5 pl-3 pr-8' : 'py-3 pl-4 pr-12 text-right',
                    { 'border-blue-500': showDropdown },
                ]"
                :disabled="disabled"
            />

            <div v-if="loading" class="absolute right-2 top-1/2 transform -translate-y-1/2">
                <svg class="animate-spin h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>

            <button
                v-if="selectedCompany && !disabled && !loading"
                @click="clearSelection"
                type="button"
                class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div
            v-if="showDropdown && (dropdownItems.length > 0 || searchQuery.length > 0 || allowUnassigned)"
            class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto"
        >
            <div v-if="dropdownItems.length === 0 && searchQuery.length >= 2 && !loading" class="px-3 py-2 text-gray-500 text-center text-sm">
                {{ $t('words.no-records-have-been-found') }}
            </div>

            <div v-else>
                <div
                    v-for="(company, index) in dropdownItems"
                    :key="company.id"
                    @mousedown.prevent="selectCompany(company)"
                    @mouseenter="hoveredIndex = index"
                    @mouseleave="hoveredIndex = -1"
                    class="cursor-pointer transition-colors duration-150"
                    :class="[
                        compact ? 'px-3 py-2 text-sm' : 'px-4 py-3',
                        hoveredIndex === index ? 'bg-blue-50 text-blue-900' : 'bg-white',
                    ]"
                >
                    <div class="font-medium text-gray-900">{{ company.name_ar }}</div>
                    <div v-if="company.name_en && !company.isUnassigned" class="text-xs text-gray-500">{{ company.name_en }}</div>
                    <div v-if="company.code" class="text-xs text-gray-400">{{ company.code }}</div>
                </div>
            </div>
        </div>

        <div v-if="selectedCompany && !showDropdown && !compact" class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <div class="font-medium text-blue-900">{{ selectedCompany.name_ar }}</div>
                    <div v-if="selectedCompany.name_en" class="text-sm text-blue-700">{{ selectedCompany.name_en }}</div>
                    <div v-if="selectedCompany.code" class="text-xs text-blue-600">{{ selectedCompany.code }}</div>
                </div>
                <button
                    @click="clearSelection"
                    type="button"
                    class="text-blue-600 hover:text-blue-800"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import _ from 'lodash';

export default {
    props: {
        value: {
            type: Object,
            default: null,
        },
        placeholder: {
            type: String,
            default: 'اكتب للبحث عن شركة...',
        },
        disabled: {
            type: Boolean,
            default: false,
        },
        compact: {
            type: Boolean,
            default: false,
        },
        allowUnassigned: {
            type: Boolean,
            default: false,
        },
        searchRoute: {
            type: String,
            default: 'back.companies.search',
        },
    },
    data() {
        return {
            searchQuery: '',
            companies: [],
            selectedCompany: this.value,
            showDropdown: false,
            loading: false,
            hoveredIndex: -1,
        };
    },
    computed: {
        dropdownItems() {
            const items = [];

            if (this.allowUnassigned) {
                items.push({
                    id: 'none',
                    name_ar: this.$t('words.not-assigned-to-a-company'),
                    isUnassigned: true,
                });
            }

            return items.concat(this.companies);
        },
    },
    watch: {
        value(newVal) {
            this.selectedCompany = newVal;
            this.searchQuery = newVal ? newVal.name_ar : '';
        },
    },
    mounted() {
        if (this.selectedCompany) {
            this.searchQuery = this.selectedCompany.name_ar;
        }
    },
    methods: {
        handleFocus() {
            this.showDropdown = true;
            if (this.searchQuery.length >= 2) {
                this.handleSearch();
            }
        },
        handleSearch: _.debounce(function () {
            if (this.searchQuery.length < 2) {
                this.companies = [];
                this.showDropdown = true;
                return;
            }

            this.loading = true;
            this.showDropdown = true;

            axios.get(route(this.searchRoute), {
                params: {
                    search: this.searchQuery,
                },
            })
                .then((response) => {
                    this.companies = response.data;
                })
                .catch(() => {
                    this.companies = [];
                })
                .finally(() => {
                    this.loading = false;
                });
        }, 300),

        selectCompany(company) {
            this.selectedCompany = company;
            this.searchQuery = company.name_ar;
            this.showDropdown = false;
            this.$emit('input', company);
            this.$emit('change', company);
        },

        clearSelection() {
            this.selectedCompany = null;
            this.searchQuery = '';
            this.companies = [];
            this.showDropdown = false;
            this.$emit('input', null);
            this.$emit('change', null);
        },

        handleBlur() {
            setTimeout(() => {
                this.showDropdown = false;
                if (this.selectedCompany) {
                    this.searchQuery = this.selectedCompany.name_ar;
                }
            }, 200);
        },
    },
};
</script>

<style scoped>
.overflow-y-auto::-webkit-scrollbar {
    width: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>
