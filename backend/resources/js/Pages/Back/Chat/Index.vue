<template>
    <chat-layout>
        <div class="flex flex-col h-full w-full overflow-hidden">
            <div
                class="items-center justify-between gap-3 flex-wrap px-4 py-1.5 border-b bg-white flex-shrink-0"
                :class="selectedConversation ? 'hidden md:flex' : 'flex'"
            >
                <h2 class="font-semibold text-sm text-gray-800 leading-tight">
                    {{ $t('words.chat') }}
                </h2>
                <div class="flex items-center gap-2 flex-wrap">
                    <button
                        v-if="deferredInstallPrompt"
                        type="button"
                        @click="installChatPwa"
                        class="text-xs leading-tight text-green-700 hover:text-green-900 border border-green-300 bg-green-50 hover:bg-green-100 px-2 py-1 rounded-md font-medium transition whitespace-nowrap"
                    >
                        {{ $t('words.chat-install-app') }}
                    </button>
                    <button
                        v-if="pushSupported"
                        type="button"
                        @click="togglePushNotifications"
                        :disabled="pushBusy"
                        class="text-xs leading-tight border px-2 py-1 rounded-md font-medium transition whitespace-nowrap disabled:opacity-50"
                        :class="pushEnabled
                            ? 'text-gray-700 border-gray-300 bg-white hover:bg-gray-50'
                            : 'text-blue-700 border-blue-300 bg-blue-50 hover:bg-blue-100'"
                    >
                        {{ pushBusy
                            ? $t('words.chat-notifications-working')
                            : (pushEnabled
                                ? $t('words.chat-disable-notifications')
                                : $t('words.chat-enable-notifications')) }}
                    </button>
                    <inertia-link
                        v-can="'view-whatsapp-reports'"
                        href="/back/chat/reports"
                        class="text-xs leading-tight text-indigo-700 border border-indigo-300 bg-indigo-50 hover:bg-indigo-100 px-2 py-1 rounded-md font-medium transition whitespace-nowrap"
                    >
                        {{ $t('words.reports') }}
                    </inertia-link>
                    <inertia-link
                        v-if="!isChatOnlyShell"
                        :href="route('dashboard')"
                        class="text-xs leading-tight text-gray-600 hover:text-gray-900 border border-gray-300 bg-white hover:bg-gray-50 px-2 py-1 rounded-md font-medium transition whitespace-nowrap"
                    >
                        {{ $t('words.go-back-to-dashboard') }}
                    </inertia-link>
                    <whats-app-templates-manager
                        v-if="configured"
                        :can-manage="canManageTemplates"
                        :list-route="route('back.chat.templates')"
                        :store-route="route('back.chat.templates.store')"
                        :update-route-template="route('back.chat.templates.update', { contentSid: '__SID__' })"
                        :destroy-route-template="route('back.chat.templates.destroy', { contentSid: '__SID__' })"
                        @templates-updated="onTemplatesManaged"
                    />
                    <button
                        @click="openNewChatModal"
                        class="bg-green-600 hover:bg-green-700 text-white px-2.5 py-1 rounded-md text-xs leading-tight font-semibold flex items-center gap-1.5 transition-colors"
                    >
                        <ion-icon name="add-outline" class="w-4 h-4"></ion-icon>
                        {{ $t('words.new-chat') }}
                    </button>
                </div>
            </div>
            <p
                v-if="pwaErrorMessage"
                class="px-4 py-1 text-xs text-red-600 bg-red-50 border-b border-red-100 flex-shrink-0"
                :class="{ 'hidden md:block': !!selectedConversation }"
            >
                {{ pwaErrorMessage }}
            </p>

            <div class="flex flex-1 min-h-0 overflow-hidden bg-white border-t border-gray-200">
                
                <!-- Left Sidebar: Conversations List (full-screen on mobile until a chat is open) -->
                <div
                    class="w-full md:w-80 lg:w-96 border-r flex-col bg-white min-h-0"
                    :class="selectedConversation ? 'hidden md:flex' : 'flex'"
                >
                    <div class="p-3 border-b space-y-2">
                        <div class="flex gap-0.5 p-0.5 bg-gray-100 rounded-md">
                            <button
                                type="button"
                                @click="setStatusTab('open')"
                                class="flex-1 px-1 py-1 rounded text-xs leading-tight font-medium transition"
                                :class="statusTab === 'open' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                            >
                                {{ $t('words.chat-status-open') }}
                                <span class="opacity-60" dir="ltr">{{ conversationCounts.open }}</span>
                            </button>
                            <button
                                type="button"
                                @click="setStatusTab('pending')"
                                class="flex-1 px-1 py-1 rounded text-xs leading-tight font-medium transition"
                                :class="statusTab === 'pending' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                            >
                                {{ $t('words.chat-status-pending') }}
                                <span class="opacity-60" dir="ltr">{{ conversationCounts.pending }}</span>
                            </button>
                            <button
                                type="button"
                                @click="setStatusTab('closed')"
                                class="flex-1 px-1 py-1 rounded text-xs leading-tight font-medium transition"
                                :class="statusTab === 'closed' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                            >
                                {{ $t('words.chat-status-closed') }}
                                <span class="opacity-60" dir="ltr">{{ conversationCounts.closed }}</span>
                            </button>
                        </div>
                        <input
                            v-model="conversationSearch"
                            @input="onSearchInput"
                            type="text"
                            class="w-full form-input text-xs rounded-md border-gray-200"
                            :placeholder="$t('words.search') + '...'"
                        />
                        <div class="grid grid-cols-2 gap-2">
                            <select
                                :value="listFilter"
                                @change="setFilter($event.target.value)"
                                class="form-select text-xs rounded-md border-gray-200 py-1.5"
                            >
                                <option value="all">{{ $t('words.all') }}</option>
                                <option value="mine">{{ $t('words.chat-filter-mine') }}</option>
                                <option value="unassigned">{{ $t('words.chat-filter-unassigned') }} ({{ conversationCounts.unassigned }})</option>
                            </select>
                            <select
                                v-if="availableTags.length"
                                :value="selectedTagFilter"
                                @change="setTagFilter($event.target.value)"
                                class="form-select text-xs rounded-md border-gray-200 py-1.5"
                            >
                                <option value="">{{ $t('words.chat-filter-all-tags') }}</option>
                                <option
                                    v-for="tag in availableTags"
                                    :key="'filter-tag-' + tag.id"
                                    :value="tag.id"
                                >
                                    {{ tag.name }} ({{ tag.conversation_count || 0 }})
                                </option>
                            </select>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <ion-icon
                                name="swap-vertical-outline"
                                class="w-4 h-4 text-gray-400 flex-shrink-0"
                            ></ion-icon>
                            <div class="flex flex-1 gap-0.5 p-0.5 bg-gray-100 rounded-md min-w-0">
                                <button
                                    type="button"
                                    class="flex-1 px-1 py-1 rounded text-xs leading-tight font-medium transition"
                                    :class="conversationGroupMode === 'latest' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                                    @click="setConversationGroupMode('latest')"
                                >
                                    {{ $t('words.chat-group-latest') }}
                                </button>
                                <button
                                    type="button"
                                    class="flex-1 px-1 py-1 rounded text-xs leading-tight font-medium transition"
                                    :class="conversationGroupMode === 'company' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                                    @click="setConversationGroupMode('company')"
                                >
                                    {{ $t('words.chat-group-by-company') }}
                                </button>
                                <button
                                    type="button"
                                    class="flex-1 px-1 py-1 rounded text-xs leading-tight font-medium transition"
                                    :class="conversationGroupMode === 'agent' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                                    @click="setConversationGroupMode('agent')"
                                >
                                    {{ $t('words.chat-group-by-agent') }}
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="conversationGroupMode === 'company'"
                            class="rounded-md bg-amber-50 px-2.5 py-2 text-xs text-amber-900"
                        >
                            <button
                                v-if="!agentCompanyFilters.length"
                                type="button"
                                class="underline hover:text-amber-700 w-full text-start"
                                @click="openCompanyFilterModal()"
                            >
                                {{ $t('words.chat-company-filter-prompt') }}
                            </button>
                            <div v-else class="flex flex-wrap items-center gap-x-1 gap-y-1">
                                <button
                                    type="button"
                                    class="underline hover:text-amber-700"
                                    @click="openCompanyFilterModal()"
                                >
                                    {{ $t('words.chat-company-filter-edit') }} ({{ formatCompanyFilterCount(agentCompanyFilters.length) }})
                                </button>
                                <span>/</span>
                                <button
                                    type="button"
                                    class="underline hover:text-amber-700"
                                    :disabled="savingCompanyFilters"
                                    @click="clearCompanyFilters()"
                                >
                                    {{ $t('words.chat-company-filter-clear') }}
                                </button>
                                <span>/</span>
                                <button
                                    type="button"
                                    class="underline hover:text-amber-700"
                                    :class="companyFilterPaused ? 'font-semibold text-amber-700' : ''"
                                    @click="showAllCompanies()"
                                >
                                    {{ $t('words.chat-company-filter-show-all') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="totalPages > 1"
                        class="px-3 py-2 border-b bg-white flex items-center justify-between text-xs text-gray-500 flex-shrink-0"
                    >
                        <button
                            @click="goToPage(conversationPage - 1)"
                            :disabled="conversationPage === 1 || loadingConversations"
                            class="px-2.5 py-1 bg-gray-50 hover:bg-gray-100 rounded disabled:opacity-40"
                        >
                            {{ $t('words.previous') }}
                        </button>
                        <span dir="ltr">{{ conversationPage }} / {{ totalPages }}</span>
                        <button
                            @click="goToPage(conversationPage + 1)"
                            :disabled="conversationPage >= totalPages || loadingConversations"
                            class="px-2.5 py-1 bg-gray-50 hover:bg-gray-100 rounded disabled:opacity-40"
                        >
                            {{ $t('words.next') }}
                        </button>
                    </div>

                    <div class="conversation-list-scroll overflow-y-auto flex-1 bg-gray-50/40">
                        <div class="divide-y divide-gray-100">
                            <div
                                v-if="loadingConversations && conversations.length === 0"
                                class="p-4 text-center text-xs text-gray-500"
                            >
                                {{ $t('words.loading') }}...
                            </div>
                            <div v-else-if="!loadingConversations && conversations.length === 0" class="p-6 text-center text-xs text-gray-500">
                                {{ $t('words.no-results') }}
                            </div>
                            <template v-else>
                                <div
                                    v-for="group in conversationSidebarGroups"
                                    :key="'sidebar-group-' + group.key"
                                >
                                    <div
                                        v-if="group.label"
                                        class="sticky top-0 z-10 px-3 py-1.5 bg-gray-100 border-y border-gray-200"
                                    >
                                        <div class="text-xs font-semibold text-gray-600 truncate">
                                            {{ group.label }}
                                        </div>
                                    </div>
                                    <transition-group name="conv-sidebar" tag="div" class="divide-y divide-gray-100">
                                    <div
                                        v-for="conv in group.conversations"
                                        :key="conv.id || conv.phone"
                                        @click="selectConversation(conv)"
                                        class="px-3 py-2.5 cursor-pointer transition-colors border-l-2"
                                        :class="selectedConversation && selectedConversation.phone === conv.phone
                                            ? 'bg-green-50 border-green-600 hover:bg-green-50'
                                            : 'border-transparent hover:bg-gray-100'"
                                    >
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="font-medium text-xs text-gray-900 inline-flex items-center gap-1 min-w-0 flex-1">
                                                <span
                                                    v-if="conv.has_unread"
                                                    class="inline-flex items-center justify-center w-3 h-3 rounded-full bg-red-600 text-white text-xs font-bold flex-shrink-0 leading-none"
                                                    :title="$t('words.chat-unread')"
                                                >!</span>
                                                <span class="truncate min-w-0">
                                                    <template v-if="conv.trainee">{{ conv.trainee.name }}</template>
                                                    <span v-else dir="ltr">{{ conv.phone }}</span>
                                                </span>
                                                <span
                                                    v-if="conversationAccountStatusLabel(conv)"
                                                    class="text-xs font-medium flex-shrink-0"
                                                    :class="conversationAccountStatusClass(conv)"
                                                >{{ conversationAccountStatusLabel(conv) }}</span>
                                                <span
                                                    v-if="conv.trainee && Number(conv.unpaid_invoice_count) > 0"
                                                    class="text-red-600 text-xs font-semibold flex-shrink-0"
                                                    :title="$t('words.unpaid-invoices')"
                                                    dir="ltr"
                                                >({{ conv.unpaid_invoice_count }})</span>
                                            </span>
                                            <span class="inline-flex items-center gap-1 flex-shrink-0">
                                                <span class="text-xs text-gray-400">
                                                    {{ formatTimeShort(conv.last_message && conv.last_message.sent_at) }}
                                                </span>
                                                <span
                                                    class="inline-flex items-center justify-center w-4 h-4 rounded-full flex-shrink-0"
                                                    :class="isConversationBotPaused(conv) ? 'bg-orange-500 text-white' : 'bg-green-600 text-white'"
                                                    :title="isConversationBotPaused(conv) ? $t('words.bot-paused') : $t('words.bot-active')"
                                                >
                                                    <ion-icon
                                                        :name="isConversationBotPaused(conv) ? 'pause' : 'flash'"
                                                        class="w-3 h-3"
                                                    ></ion-icon>
                                                </span>
                                                <ion-icon
                                                    v-if="isConversationMessagingWindowLocked(conv)"
                                                    name="lock-closed-outline"
                                                    class="w-3.5 h-3.5 text-red-500"
                                                    :title="$t('words.whatsapp-window-locked')"
                                                ></ion-icon>
                                            </span>
                                        </div>
                                        <div
                                            v-if="conversationAccountReason(conv)"
                                            class="text-xs mt-0.5 truncate"
                                            :class="conversationAccountStatusClass(conv)"
                                            :title="conversationAccountReason(conv)"
                                        >
                                            {{ conversationAccountReason(conv) }}
                                        </div>
                                        <div class="flex items-center justify-between gap-2 mt-0.5">
                                            <span class="text-xs text-gray-500 truncate">
                                                <span v-if="conv.last_message && conv.last_message.is_note" class="text-amber-700">[{{ $t('words.internal-note') }}] </span>
                                                {{ conv.last_message && conv.last_message.body }}
                                            </span>
                                        </div>
                                        <div
                                            v-if="(conv.agents && conv.agents.length) || (conv.tags && conv.tags.length)"
                                            class="flex items-center flex-wrap gap-1 mt-1"
                                        >
                                            <span
                                                v-for="agent in (conv.agents || [])"
                                                :key="'la-' + conv.id + '-' + agent.id"
                                                class="inline-flex items-center max-w-[72px] truncate px-1 py-0.5 rounded-sm bg-gray-200 text-gray-700 text-xs font-medium leading-tight"
                                                :title="agent.name"
                                            >
                                                {{ agentFirstName(agent.name) }}
                                            </span>
                                            <span
                                                v-for="tag in (conv.tags || [])"
                                                :key="'lt-' + conv.id + '-' + tag.id"
                                                class="inline-flex max-w-[72px] truncate px-1.5 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-600"
                                                :style="tag.color ? { backgroundColor: tag.color, color: '#fff' } : null"
                                                :title="tag.name"
                                            >
                                                {{ tag.name }}
                                            </span>
                                        </div>
                                    </div>
                                    </transition-group>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Right Panel: Active Chat View + Trainee Details -->
                <div
                    class="flex-1 overflow-hidden bg-white min-w-0 min-h-0"
                    :class="selectedConversation ? 'flex' : 'hidden md:flex'"
                >
                    <div v-if="!selectedConversation" class="flex-1 flex flex-col items-center justify-center text-gray-400 p-8 text-center space-y-3">
                        <p class="text-sm text-gray-500">{{ $t('words.select-trainee') }}</p>
                        <button
                            @click="openNewChatModal"
                            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium"
                        >
                            {{ $t('words.new-chat') }}
                        </button>
                    </div>

                    <template v-else>
                        <div class="flex-1 flex flex-col overflow-hidden min-w-0">
                        <!-- Chat Header -->
                        <div class="px-3 sm:px-4 py-2 sm:py-3 border-b bg-white space-y-2">
                            <!-- Row 1: back + identity + view details (mobile) -->
                            <div class="flex items-center gap-2 min-w-0">
                                <button
                                    type="button"
                                    class="md:hidden flex-shrink-0 px-2 py-1.5 rounded-md text-xs font-medium text-gray-700 hover:bg-gray-100 border border-gray-200"
                                    @click="navigateChatBack"
                                >
                                    {{ $t('words.back') }}
                                </button>
                                <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-gray-700 text-white flex items-center justify-center font-semibold text-xs sm:text-sm flex-shrink-0">
                                    {{ selectedConversation.trainee ? selectedConversation.trainee.name.charAt(0) : 'W' }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="font-semibold text-gray-900 text-xs sm:text-sm inline-flex items-center gap-1.5 min-w-0 max-w-full">
                                        <span class="truncate min-w-0">
                                            {{ selectedConversation.trainee ? selectedConversation.trainee.name : selectedConversation.phone }}
                                        </span>
                                        <span
                                            v-if="selectedConversationAccountStatusLabel"
                                            class="text-xs font-medium flex-shrink-0"
                                            :class="selectedConversationAccountStatusClass"
                                        >{{ selectedConversationAccountStatusLabel }}</span>
                                    </div>
                                    <div
                                        v-if="selectedConversationAccountReason"
                                        class="text-xs mt-0.5 truncate"
                                        :class="selectedConversationAccountStatusClass"
                                        :title="selectedConversationAccountReason"
                                    >
                                        {{ selectedConversationAccountReason }}
                                    </div>
                                    <div
                                        v-if="selectedConversation.trainee"
                                        class="text-xs text-gray-500 mt-0.5 truncate text-right"
                                        dir="rtl"
                                    >
                                        <span dir="ltr">{{ selectedConversation.phone }}</span>
                                    </div>
                                    <div
                                        v-if="messagingWindowLabel"
                                        class="text-xs text-gray-400 mt-0.5 truncate"
                                        :title="$t('words.whatsapp-freeform-hint')"
                                        dir="auto"
                                    >
                                        {{ messagingWindowLabel }}
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="md:hidden flex-shrink-0 px-2 py-1.5 rounded-md text-xs font-medium text-blue-700 hover:bg-blue-50 border border-blue-200"
                                    @click="openTraineeDetailsMobile"
                                >
                                    {{ $t('words.chat-view-details') }}
                                </button>
                            </div>

                            <!-- Row 2: status / assign / profile -->
                            <div class="flex flex-wrap items-center gap-1.5">
                                    <select
                                        :value="selectedConversation.status || 'open'"
                                        @change="onStatusChange($event)"
                                        :disabled="updatingStatus"
                                        class="form-select text-xs rounded-md border-gray-200 py-1.5"
                                    >
                                        <option value="open">{{ $t('words.chat-status-open') }}</option>
                                        <option value="pending">{{ $t('words.chat-status-pending') }}</option>
                                        <option value="closed">{{ $t('words.chat-status-closed') }}</option>
                                    </select>
                                    <div class="relative inline-flex" dir="ltr" @click.stop>
                                        <button
                                            type="button"
                                            @click="toggleAssignMe"
                                            :disabled="assigningAgent"
                                            class="text-xs px-2.5 py-1.5 rounded-l-md font-medium border border-gray-200 transition disabled:opacity-50"
                                            :class="selectedConversation.is_assigned_to_me
                                                ? 'bg-gray-800 text-white border-gray-800'
                                                : 'bg-white text-gray-700 hover:bg-gray-50'"
                                        >
                                            {{ selectedConversation.is_assigned_to_me ? $t('words.chat-unassign-me') : $t('words.chat-assign-me') }}
                                        </button>
                                        <button
                                            type="button"
                                            @click="toggleAssignDropdown"
                                            :disabled="assigningAgent"
                                            class="text-xs px-1.5 py-1.5 rounded-r-md font-medium border border-l-0 border-gray-200 transition disabled:opacity-50"
                                            :class="selectedConversation.is_assigned_to_me
                                                ? 'bg-gray-800 text-white border-gray-800'
                                                : 'bg-white text-gray-700 hover:bg-gray-50'"
                                            :title="$t('words.chat-assign-colleague')"
                                            :aria-expanded="showAssignDropdown ? 'true' : 'false'"
                                        >
                                            <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                        <div
                                            v-if="showAssignDropdown"
                                            class="absolute top-full right-0 z-30 mt-1 w-56 max-h-56 overflow-y-auto rounded-md border border-gray-200 bg-white shadow-lg py-1"
                                            dir="auto"
                                        >
                                            <div class="px-3 py-1.5 text-[11px] font-medium text-gray-400 uppercase tracking-wide">
                                                {{ $t('words.chat-assign-colleague') }}
                                            </div>
                                            <div v-if="loadingAssignableAgents" class="px-3 py-2 text-xs text-gray-500">
                                                {{ $t('words.loading') }}
                                            </div>
                                            <div v-else-if="!assignableAgents.length" class="px-3 py-2 text-xs text-gray-500">
                                                {{ $t('words.chat-assign-no-colleagues') }}
                                            </div>
                                            <button
                                                v-for="agent in assignableAgents"
                                                :key="'assign-' + agent.id"
                                                type="button"
                                                class="w-full text-left px-3 py-2 text-xs text-gray-700 hover:bg-gray-50 flex items-center justify-between gap-2 disabled:opacity-50"
                                                :disabled="assigningAgent || isAgentAssigned(agent.id)"
                                                @click="assignColleague(agent)"
                                            >
                                                <span class="truncate">{{ agent.name }}</span>
                                                <span v-if="isAgentAssigned(agent.id)" class="shrink-0 text-[10px] text-gray-400">
                                                    {{ $t('words.chat-assigned') }}
                                                </span>
                                            </button>
                                        </div>
                                    </div>
                                    <a
                                        v-if="selectedConversation.trainee"
                                        :href="selectedConversation.trainee.show_url"
                                        target="_blank"
                                        class="text-xs bg-white border border-gray-200 hover:bg-gray-50 px-2.5 py-1.5 rounded-md font-medium text-gray-700 transition"
                                    >
                                        {{ $t('words.profile') }}
                                    </a>
                                    <button
                                        v-if="maqsamConfigured && selectedConversation.phone"
                                        type="button"
                                        class="text-xs px-2.5 py-1.5 rounded-md font-medium transition inline-flex items-center gap-1 disabled:opacity-50"
                                        :class="maqsamWindowOpen
                                            ? 'bg-green-600 hover:bg-green-700 text-white border border-green-600'
                                            : 'bg-white border border-green-200 hover:bg-green-50 text-green-700'"
                                        :disabled="maqsamDialing"
                                        @click="callSelectedConversation"
                                    >
                                        <ion-icon name="call-outline" class="w-3.5 h-3.5"></ion-icon>
                                        {{ maqsamCallButtonLabel }}
                                    </button>
                            </div>

                            <!-- Row 3: secondary — bot + tags -->
                            <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500">
                                <span :title="botStatusTitle">{{ botStatusLabel }}</span>
                                <button
                                    v-if="canPauseBot"
                                    type="button"
                                    class="text-xs text-gray-600 hover:text-gray-900 underline-offset-2 hover:underline disabled:opacity-50"
                                    :disabled="pausingBot"
                                    @click="pauseBot"
                                >
                                    {{ pausingBot ? $t('words.saving') : pauseBotButtonLabel }}
                                </button>
                                <button
                                    v-if="canResumeBot"
                                    type="button"
                                    class="text-xs text-gray-600 hover:text-gray-900 underline-offset-2 hover:underline disabled:opacity-50"
                                    :disabled="pausingBot"
                                    @click="resumeBot"
                                >
                                    {{ pausingBot ? $t('words.saving') : $t('words.resume-bot') }}
                                </button>
                                <span
                                    v-if="(selectedConversation.agents && selectedConversation.agents.length) || (selectedConversation.tags && selectedConversation.tags.length)"
                                    class="text-gray-300"
                                >·</span>
                                <span
                                    v-for="agent in (selectedConversation.agents || [])"
                                    :key="'ha-' + agent.id"
                                    class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 text-[11px]"
                                    :title="agent.name"
                                >
                                    {{ agent.name }}
                                </span>
                                <span
                                    v-for="tag in (selectedConversation.tags || [])"
                                    :key="'ht-' + tag.id"
                                    class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[11px] font-medium bg-gray-100 text-gray-600"
                                    :style="tag.color ? { backgroundColor: tag.color, color: '#fff' } : null"
                                >
                                    {{ tag.name }}
                                    <button type="button" class="opacity-70 hover:opacity-100" @click="detachTag(tag)">×</button>
                                </span>
                                <div class="inline-flex items-center gap-1 ml-auto">
                                    <select
                                        v-model="tagToAttach"
                                        class="form-select text-xs rounded-md border-gray-200 py-1"
                                    >
                                        <option value="">{{ $t('words.chat-add-tag') }}</option>
                                        <option v-for="tag in availableTags" :key="'pick-' + tag.id" :value="tag.id">
                                            {{ tag.name }}
                                        </option>
                                    </select>
                                    <button
                                        type="button"
                                        @click="attachSelectedTag"
                                        :disabled="!tagToAttach || attachingTag"
                                        class="text-xs text-gray-600 border border-gray-200 bg-white px-2 py-1 rounded-md disabled:opacity-40 hover:bg-gray-50"
                                    >
                                        {{ $t('words.add') }}
                                    </button>
                                    <button
                                        type="button"
                                        @click="createAndAttachTag"
                                        :disabled="attachingTag"
                                        class="text-xs text-gray-400 hover:text-gray-700 disabled:opacity-40"
                                    >
                                        {{ $t('words.chat-new-tag') }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Messages Container -->
                        <div class="relative flex-shrink-0 bg-gray-50" :style="{ height: threadHeight + 'px' }">
                            <div
                                ref="messagesContainer"
                                @scroll="onMessagesScroll"
                                class="h-full overflow-y-auto p-2 sm:p-4 space-y-2 sm:space-y-3"
                            >
                            <div v-if="hasMoreMessages" class="text-center mb-1">
                                <button
                                    type="button"
                                    @click="loadOlderMessages"
                                    :disabled="loadingOlderMessages"
                                    class="text-xs border border-gray-300 px-3 py-1.5 bg-white hover:bg-gray-50 disabled:opacity-50"
                                    style="border-radius: 2px;"
                                >
                                    {{ loadingOlderMessages ? ($t('words.loading') + '...') : $t('words.chat-load-older') }}
                                </button>
                            </div>
                            <div v-if="loadingMessages" class="text-center text-xs sm:text-sm text-gray-500 py-4">
                                {{ $t('words.loading') }}...
                            </div>

                            <div
                                v-for="message in messages"
                                :key="message.id || message.sid || (message.date_sent + '-' + message.body)"
                                class="flex"
                                :class="messageAlignmentClass(message)"
                            >
                                <!-- Internal Note -->
                                <div
                                    v-if="message.is_note"
                                    class="max-w-[85%] w-full bg-amber-50 rounded-lg px-2.5 py-1.5 sm:px-3.5 sm:py-2.5 text-xs sm:text-sm text-amber-950"
                                >
                                    <div class="flex items-center justify-between text-[10px] sm:text-[11px] text-amber-800/80 mb-1">
                                        <span>{{ $t('words.internal-note') }}<span v-if="message.author"> · {{ message.author.name }}</span></span>
                                        <span dir="ltr">{{ formatMessageTime(message.date_sent) }}</span>
                                    </div>
                                    <p class="whitespace-pre-wrap break-words leading-snug sm:leading-relaxed" dir="auto">{{ message.body }}</p>
                                </div>

                                <!-- Standard message bubble -->
                                <div
                                    v-else
                                    class="max-w-[80%] md:max-w-[70%] rounded-lg px-2.5 py-1.5 sm:px-3.5 sm:py-2.5 text-xs sm:text-sm"
                                    :class="message.status === 'delivery_failed' || message.status === 'failed'
                                        ? 'bg-red-50 text-red-950'
                                        : (isBotMessage(message)
                                        ? 'bg-slate-100 text-gray-900'
                                        : (isOutboundMessage(message)
                                            ? 'bg-green-50 text-gray-900'
                                            : 'bg-white text-gray-900 shadow-sm'))"
                                >
                                    <p
                                        v-if="messageBodyVisible(message)"
                                        class="whitespace-pre-wrap break-words leading-snug sm:leading-relaxed"
                                        dir="auto"
                                    >{{ message.body }}</p>

                                    <div v-if="messageAttachments(message).length" class="mt-2 space-y-2">
                                        <div
                                            v-for="(media, mediaIndex) in messageAttachments(message)"
                                            :key="media.id || media.url || mediaIndex"
                                        >
                                            <a
                                                v-if="isStickerAttachment(media) && media.url"
                                                :href="media.url"
                                                target="_blank"
                                                class="inline-block"
                                                :title="$t('words.whatsapp-sticker')"
                                            >
                                                <img
                                                    :src="media.url"
                                                    :alt="$t('words.whatsapp-sticker')"
                                                    class="w-32 h-32 object-contain"
                                                    loading="lazy"
                                                />
                                            </a>
                                            <div
                                                v-else-if="isStickerAttachment(media)"
                                                class="text-xs text-gray-500 italic"
                                            >
                                                {{ $t('words.whatsapp-sticker') }}
                                            </div>
                                            <a
                                                v-else-if="isImageAttachment(media) && media.url"
                                                :href="media.url"
                                                target="_blank"
                                                class="block"
                                            >
                                                <img
                                                    :src="media.url"
                                                    :alt="media.name || $t('words.attachment')"
                                                    class="max-w-full max-h-64 rounded-md object-contain bg-black/5"
                                                    loading="lazy"
                                                />
                                            </a>
                                            <video
                                                v-else-if="isVideoAttachment(media)"
                                                :src="media.url"
                                                controls
                                                class="max-w-full max-h-64 rounded-md bg-black"
                                            ></video>
                                            <audio
                                                v-else-if="isAudioAttachment(media)"
                                                :src="media.url"
                                                controls
                                                class="w-full"
                                            ></audio>
                                            <a
                                                v-else-if="media.url"
                                                :href="media.url"
                                                target="_blank"
                                                class="inline-flex items-center gap-1.5 text-sm font-medium text-blue-700 hover:underline"
                                            >
                                                <ion-icon name="document-attach-outline" class="w-4 h-4"></ion-icon>
                                                {{ media.name || $t('words.attachment') }}
                                            </a>
                                        </div>
                                    </div>

                                    <div class="text-[10px] sm:text-[11px] mt-1 text-gray-400 flex items-center gap-1 flex-wrap" dir="auto">
                                        <span v-if="isBotMessage(message)">{{ $t('words.whatsapp-bot-label') }}</span>
                                        <span v-else-if="isOutboundMessage(message) && message.author">{{ message.author.name }}</span>
                                        <span v-else-if="!isOutboundMessage(message)">{{ $t('words.trainee') }}</span>
                                        <span dir="ltr">· {{ formatMessageTime(message.date_sent) }}</span>
                                        <span v-if="message.status" dir="ltr">· {{ translateStatus(message.status) }}</span>
                                    </div>
                                </div>
                            </div>
                            </div>
                            <div
                                class="absolute bottom-0 left-0 right-0 h-2 cursor-ns-resize flex items-end justify-end pr-1 pb-0.5 select-none"
                                title="Resize"
                                @mousedown.prevent="startThreadResize"
                            >
                                <span class="w-3 h-3 border-r-2 border-b-2 border-gray-400 opacity-60"></span>
                            </div>
                        </div>

                        <!-- Composer -->
                        <div class="border-t bg-white flex-1 min-h-0 flex flex-col overflow-hidden">
                            <a
                                v-if="composerCompanyUrl"
                                :href="composerCompanyUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex-shrink-0 block px-3 py-1 border-b bg-gray-50 text-gray-500 hover:text-gray-800 hover:bg-gray-100 hover:underline truncate"
                                style="font-size: 10px; line-height: 1.3;"
                                dir="auto"
                                :title="composerCompanyName"
                            >{{ composerCompanyName }}</a>
                            <div
                                v-else-if="composerCompanyName"
                                class="flex-shrink-0 px-3 py-1 border-b bg-gray-50 text-gray-400 truncate"
                                style="font-size: 10px; line-height: 1.3;"
                                dir="auto"
                            >{{ composerCompanyName }}</div>
                            <div class="p-3 flex-1 min-h-0 flex flex-col overflow-hidden">
                            <div class="flex items-center justify-between gap-2 mb-3 flex-shrink-0">
                                <div class="flex gap-0.5 p-0.5 bg-gray-100 rounded-md w-fit flex-wrap">
                                    <button
                                        type="button"
                                        @click="setComposerMode('freeform')"
                                        class="px-3 py-1.5 rounded text-xs font-medium transition"
                                        :class="composerMode === 'freeform' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                                    >
                                        {{ $t('words.message') }}
                                    </button>
                                    <button
                                        type="button"
                                        @click="setComposerMode('template')"
                                        class="px-3 py-1.5 rounded text-xs font-medium transition"
                                        :class="composerMode === 'template' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                                    >
                                        {{ $t('words.whatsapp-templates') }}
                                    </button>
                                    <button
                                        type="button"
                                        @click="setComposerMode('note')"
                                        class="px-3 py-1.5 rounded text-xs font-medium transition"
                                        :class="composerMode === 'note' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                                    >
                                        {{ $t('words.internal-note') }}
                                    </button>
                                </div>
                                <div class="flex items-center gap-1.5 flex-shrink-0">
                                    <button
                                        v-for="action in composerStatusActions"
                                        :key="'status-action-' + action.status"
                                        type="button"
                                        class="px-3 py-1.5 rounded text-xs font-medium border transition disabled:opacity-50"
                                        :class="action.buttonClass"
                                        :disabled="updatingStatus"
                                        @click="setConversationStatus(action.status, $event)"
                                    >
                                        {{ action.label }}
                                    </button>
                                </div>
                            </div>

                            <div class="flex-1 min-h-0 overflow-y-auto">
                            <div v-if="composerMode === 'template'">
                                <div v-if="loadingTemplates" class="text-xs text-gray-500 mb-2">
                                    {{ $t('words.loading') }}...
                                </div>
                                <div
                                    v-else-if="!templates.length"
                                    class="text-xs text-gray-500 mb-2"
                                >
                                    {{ $t('words.no-results') }}
                                </div>
                                <div
                                    v-else
                                    class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-2 max-h-48 overflow-y-auto"
                                >
                                    <button
                                        v-for="template in templates"
                                        :key="template.sid"
                                        type="button"
                                        class="px-2.5 py-2 rounded-md border transition text-xs"
                                        :class="selectedTemplateSid === template.sid
                                            ? 'border-green-600 bg-green-50 text-gray-900 shadow-sm'
                                            : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300 hover:bg-gray-50'"
                                        @click="selectComposerTemplate(template.sid)"
                                    >
                                        <div class="font-semibold truncate">{{ template.friendly_name }}</div>
                                        <div class="text-gray-500 mt-0.5 truncate" dir="ltr">{{ template.language }}</div>
                                        <div
                                            v-if="template.body || template.body_display"
                                            class="mt-1 text-gray-600 whitespace-pre-wrap"
                                            style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"
                                        >{{ template.body_display || template.body }}</div>
                                    </button>
                                </div>

                                <div v-if="selectedTemplate" class="mb-2 p-2.5 bg-gray-50 rounded-md text-sm whitespace-pre-wrap text-gray-800">
                                    {{ previewTemplateBody }}
                                </div>

                                <div v-if="selectedTemplate && manualTemplateVariables.length" class="space-y-2 mb-2">
                                    <div
                                        v-for="variableKey in manualTemplateVariables"
                                        :key="variableKey"
                                    >
                                        <input
                                            v-model="templateVariables[variableKey]"
                                            type="text"
                                            class="w-full form-input text-xs rounded-md border-gray-200"
                                            :placeholder="templateVariableLabel(variableKey)"
                                        />
                                    </div>
                                </div>
                                <p
                                    v-else-if="selectedTemplate && selectedTemplate.variables && selectedTemplate.variables.length"
                                    class="text-xs text-gray-500 mb-2"
                                >
                                    {{ $t('words.whatsapp-auto-filled-variables') }}
                                </p>

                                <button
                                    @click="sendTemplate"
                                    type="button"
                                    :disabled="sending || !selectedTemplateSid"
                                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium disabled:opacity-50"
                                >
                                    {{ $t('words.send-whatsapp-template') }}
                                </button>
                            </div>

                            <div v-else>
                                <div
                                    v-if="composerMode === 'freeform' && messagingWindowIsOpen === false"
                                    class="mb-2 text-xs text-amber-800 bg-amber-50 px-3 py-2 rounded-md"
                                >
                                    {{ $t('words.whatsapp-window-locked-hint') }}
                                </div>
                                <p
                                    v-if="composerMode === 'note'"
                                    class="mb-2 text-xs text-amber-800 bg-amber-50 px-3 py-2 rounded-md"
                                >
                                    {{ $t('words.internal-note-hint') }}
                                </p>
                                <div class="relative z-50">
                                    <textarea
                                        ref="messageTextarea"
                                        v-model="messageBody"
                                        rows="3"
                                        class="w-full text-sm rounded-md p-2.5 pr-10 border border-gray-200 focus:border-gray-400 focus:ring-0"
                                        :class="composerMode === 'note' ? 'bg-amber-50/50' : 'bg-white'"
                                        :placeholder="$t('words.message') + '...'"
                                        :disabled="composerMode === 'freeform' && messagingWindowIsOpen === false"
                                        @keydown="onMessageKeydown"
                                    ></textarea>
                                    <div class="absolute top-2 right-2 z-50" v-if="composerMode === 'freeform' || composerMode === 'note'">
                                        <button
                                            ref="emojiButton"
                                            type="button"
                                            class="text-gray-400 hover:text-gray-700 p-1"
                                            :disabled="composerMode === 'freeform' && messagingWindowIsOpen === false"
                                            @click.stop="toggleEmojiPicker"
                                        >
                                            <ion-icon name="happy-outline" class="w-5 h-5"></ion-icon>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            </div>

                            <div
                                v-if="composerMode === 'freeform' || composerMode === 'note'"
                                class="flex items-center justify-between gap-3 mt-2 flex-shrink-0"
                            >
                                <label class="inline-flex items-center gap-2 text-xs text-gray-600 cursor-pointer select-none">
                                    <input
                                        v-model="pressEnterToSend"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-green-600 focus:ring-green-500"
                                        @change="persistPressEnterToSend"
                                    />
                                    <span>{{ $t('words.press-enter-to-send') }}</span>
                                </label>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <button
                                        type="button"
                                        class="px-2 py-1 rounded-md text-xs leading-tight font-medium border border-gray-200 text-gray-700 bg-white hover:bg-gray-50 transition"
                                        @click="openQuickRepliesModal"
                                    >
                                        {{ $t('words.quick-reply') }}
                                    </button>
                                    <button
                                        @click="sendMessageOrNote"
                                        type="button"
                                        :disabled="sending || !messageBody.trim() || (composerMode === 'freeform' && messagingWindowIsOpen === false)"
                                        class="px-2.5 py-1 rounded-md text-xs leading-tight font-medium text-white bg-green-600 hover:bg-green-700 disabled:opacity-50 transition"
                                    >
                                        {{ composerMode === 'note' ? $t('words.whatsapp-add-note') : $t('words.send') }}
                                    </button>
                                </div>
                            </div>

                            <p v-if="errorMessage" class="mt-2 text-xs text-red-600 flex-shrink-0">{{ errorMessage }}</p>
                            <p v-if="successMessage" class="mt-2 text-xs text-green-600 flex-shrink-0">{{ successMessage }}</p>
                            </div>
                        </div>
                        </div>

                        <!-- Trainee Details Sidebar (desktop) / drawer (mobile) -->
                        <div
                            v-if="showTraineeDetailsMobile"
                            class="md:hidden fixed top-0 right-0 bottom-0 left-0 z-40 bg-black bg-opacity-40"
                            @click="navigateChatBack"
                        ></div>
                        <aside
                            class="trainee-sidebar-accent bg-white flex-col overflow-hidden flex-shrink-0 text-xs"
                            :class="showTraineeDetailsMobile
                                ? 'trainee-details-drawer fixed top-0 bottom-0 z-50 flex shadow-xl'
                                : 'hidden md:flex w-64 lg:w-72'"
                        >
                            <div class="px-3 py-2 border-b flex items-center justify-between gap-2">
                                <h3 class="text-xs font-medium text-gray-700">{{ $t('words.trainee-details') }}</h3>
                                <button
                                    type="button"
                                    class="md:hidden text-xs text-gray-600 hover:text-gray-900 px-2 py-1 rounded border border-gray-200"
                                    @click="navigateChatBack"
                                >
                                    {{ $t('words.back') }}
                                </button>
                            </div>
                            <div class="flex-1 overflow-y-auto p-3 space-y-4">
                                <div v-if="!selectedConversation.trainee" class="text-xs text-gray-500">
                                    {{ $t('words.no-trainee-linked') }}
                                </div>
                                <div v-else-if="loadingTraineeContext" class="text-xs text-gray-500">
                                    {{ $t('words.loading') }}...
                                </div>
                                <template v-else-if="traineeContext">
                                    <div class="space-y-1">
                                        <button
                                            v-for="doc in traineeDocumentButtons"
                                            :key="doc.key"
                                            type="button"
                                            class="w-full text-xs leading-tight px-2 py-1 rounded-md border transition text-right"
                                            :class="doc.available
                                                ? 'border-gray-200 bg-white text-gray-800 hover:bg-gray-50'
                                                : 'border-gray-100 bg-gray-50 text-gray-400 cursor-not-allowed'"
                                            :disabled="!doc.available"
                                            @click="openTraineeDocument(doc)"
                                        >
                                            {{ doc.label }}
                                        </button>
                                    </div>

                                    <div>
                                        <div class="text-xs text-gray-500 mb-0.5">{{ $t('words.company') }}</div>
                                        <a
                                            v-if="traineeContext.trainee.company_show_url && traineeContext.trainee.company_name"
                                            :href="traineeContext.trainee.company_show_url"
                                            target="_blank"
                                            class="text-xs text-gray-900 hover:underline break-words"
                                        >
                                            {{ traineeContext.trainee.company_name }}
                                        </a>
                                        <div v-else class="text-xs text-gray-900">
                                            {{ traineeContext.trainee.company_name || '—' }}
                                        </div>
                                    </div>

                                    <div>
                                        <div class="text-xs text-gray-500 mb-0.5">{{ $t('words.registration-date') }}</div>
                                        <div class="text-xs text-gray-900 text-right">
                                            <span dir="ltr">{{ traineeContext.trainee.registration_date || '—' }}</span>
                                            <span v-if="accountStatusLabel" class="text-gray-400 mx-1">·</span>
                                            <span
                                                v-if="accountStatusLabel"
                                                :class="accountStatusInlineClass"
                                            >{{ accountStatusLabel }}</span>
                                        </div>
                                        <p
                                            v-if="traineeContext.account_status && traineeContext.account_status.reason"
                                            class="text-xs text-gray-500 mt-1 break-words"
                                        >
                                            {{ traineeContext.account_status.reason }}
                                        </p>
                                    </div>

                                    <div>
                                        <div class="text-xs text-gray-500 mb-0.5">{{ $t('words.gosi-status') }}</div>
                                        <div
                                            v-if="traineeContext.gosi_status && traineeContext.gosi_status.fetched_at"
                                            class="text-xs text-gray-900 space-y-0.5"
                                        >
                                            <div v-if="traineeContext.gosi_status.employer_name" class="break-words">
                                                {{ traineeContext.gosi_status.employer_name }}
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <span dir="ltr">{{ traineeContext.gosi_status.fetched_at }}</span>
                                            </div>
                                        </div>
                                        <div v-else class="text-xs text-gray-900">—</div>
                                    </div>

                                    <div>
                                        <div class="flex items-center justify-between gap-2 mb-1">
                                            <div class="text-xs text-gray-500">{{ $t('words.pending-invoices') }}</div>
                                            <span
                                                v-if="traineeContext.count"
                                                class="text-xs text-gray-600"
                                                dir="ltr"
                                            >
                                                {{ traineeContext.count }} · {{ formatAmount(traineeContext.total_owed) }}
                                            </span>
                                        </div>

                                        <ul
                                            v-if="traineeContext.invoices && traineeContext.invoices.length"
                                            class="divide-y divide-gray-100 border border-amber-100 rounded-md bg-amber-50/40 overflow-hidden"
                                        >
                                            <li
                                                v-for="invoice in traineeContext.invoices"
                                                :key="invoice.id"
                                                class="px-2.5 py-1.5 space-y-0.5 text-xs"
                                            >
                                                <div class="flex items-start justify-between gap-2">
                                                    <div class="min-w-0">
                                                        <a
                                                            :href="invoice.show_url"
                                                            target="_blank"
                                                            class="font-medium text-gray-900 hover:underline truncate block"
                                                        >
                                                            {{ invoice.number_formatted }}
                                                        </a>
                                                        <div class="text-gray-500 mt-0.5 truncate">
                                                            {{ invoice.company_name || traineeContext.trainee.company_name || '—' }}
                                                        </div>
                                                    </div>
                                                    <span class="font-medium text-gray-800 tabular-nums flex-shrink-0">
                                                        {{ formatAmount(invoice.grand_total) }}
                                                    </span>
                                                </div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <span
                                                        :class="Number(invoice.status) === 0
                                                            ? 'inline-block bg-red-600 text-white px-1.5 py-0.5'
                                                            : 'text-gray-500'"
                                                        :style="Number(invoice.status) === 0 ? { borderRadius: '2px' } : null"
                                                    >{{ invoice.status_formatted }}</span>
                                                    <button
                                                        type="button"
                                                        class="text-xs text-gray-600 hover:text-gray-900"
                                                        @click="copyInvoiceLink(invoice)"
                                                    >
                                                        {{ copiedInvoiceId === invoice.id ? $t('words.link-copied') : $t('words.copy-link') }}
                                                    </button>
                                                </div>
                                            </li>
                                        </ul>
                                        <div v-else class="text-xs text-gray-400">
                                            {{ $t('words.no-pending-invoices') }}
                                        </div>
                                    </div>

                                    <div>
                                        <div class="text-xs text-gray-500 mb-1">{{ $t('words.paid-invoices-history') }}</div>
                                        <ul
                                            v-if="traineeContext.paid_invoices && traineeContext.paid_invoices.length"
                                            class="divide-y divide-gray-100 border border-emerald-100 rounded-md bg-emerald-50/40 overflow-hidden"
                                        >
                                            <li
                                                v-for="invoice in traineeContext.paid_invoices"
                                                :key="'paid-' + invoice.id"
                                                class="px-2.5 py-1.5 space-y-0.5 text-xs"
                                            >
                                                <div class="flex items-start justify-between gap-2">
                                                    <div class="min-w-0">
                                                        <a
                                                            :href="invoice.show_url"
                                                            target="_blank"
                                                            class="font-medium text-gray-900 hover:underline truncate block"
                                                        >
                                                            {{ invoice.number_formatted }}
                                                        </a>
                                                        <div class="text-gray-500 mt-0.5 truncate">
                                                            {{ invoice.company_name || traineeContext.trainee.company_name || '—' }}
                                                        </div>
                                                    </div>
                                                    <span class="font-medium text-gray-800 tabular-nums flex-shrink-0">
                                                        {{ formatAmount(invoice.grand_total) }}
                                                    </span>
                                                </div>
                                                <div class="flex items-center justify-between gap-2 text-gray-500">
                                                    <span>{{ invoice.status_formatted }}</span>
                                                    <span v-if="invoice.paid_at" dir="ltr">{{ invoice.paid_at }}</span>
                                                </div>
                                            </li>
                                        </ul>
                                        <div v-else class="text-xs text-gray-400">
                                            {{ $t('words.no-paid-invoices') }}
                                        </div>
                                    </div>
                                </template>
                                <div v-else class="text-xs text-red-600">
                                    {{ $t('words.could-not-load-trainee-details') }}
                                </div>
                            </div>
                        </aside>
                    </template>
                </div>

            </div>
        </div>

        <!-- New Chat Search Modal -->
        <portal-target name="new-chat-modal"></portal-target>
        <portal-target name="company-filter-modal"></portal-target>
        <portal to="emoji-picker-portal">
            <div
                v-if="showEmojiPicker"
                class="fixed w-56 max-h-40 overflow-y-auto bg-white border border-gray-200 rounded-md shadow-xl p-2 grid grid-cols-8 gap-1"
                :style="emojiPickerStyle"
                @click.stop
            >
                <button
                    v-for="emoji in emojiList"
                    :key="emoji"
                    type="button"
                    class="text-base hover:bg-gray-100 rounded p-0.5"
                    @click="insertEmoji(emoji)"
                >
                    {{ emoji }}
                </button>
            </div>
        </portal>
        <portal-target name="emoji-picker-portal"></portal-target>
        <portal to="new-chat-modal">
            <modal name="newChatModal" :width="720" :height="'auto'" :scrollable="true">
                <div class="bg-white rounded-xl shadow-2xl p-6 flex flex-col max-h-[85vh]">
                    <div class="flex items-center justify-between pb-4 border-b mb-4">
                        <h3 class="text-lg font-bold text-gray-800">{{ $t('words.new-chat') }}</h3>
                        <button @click="$modal.hide('newChatModal')" class="text-gray-400 hover:text-gray-600">
                            <ion-icon name="close-outline" class="w-6 h-6"></ion-icon>
                        </button>
                    </div>

                    <div class="flex flex-wrap gap-0.5 p-0.5 bg-gray-100 rounded-md mb-4 w-fit">
                        <button
                            type="button"
                            class="px-3 py-1.5 rounded text-xs font-medium transition"
                            :class="newChatRecipientMode === 'search' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                            @click="setNewChatRecipientMode('search')"
                        >
                            {{ $t('words.search') }}
                        </button>
                        <button
                            type="button"
                            class="px-3 py-1.5 rounded text-xs font-medium transition"
                            :class="newChatRecipientMode === 'company' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                            @click="setNewChatRecipientMode('company')"
                        >
                            {{ $t('words.company') }}
                        </button>
                        <button
                            type="button"
                            class="px-3 py-1.5 rounded text-xs font-medium transition"
                            :class="newChatRecipientMode === 'custom' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                            @click="setNewChatRecipientMode('custom')"
                        >
                            {{ $t('words.custom-phone-number') }}
                        </button>
                        <button
                            type="button"
                            class="px-3 py-1.5 rounded text-xs font-medium transition"
                            :class="newChatRecipientMode === 'csv' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                            @click="setNewChatRecipientMode('csv')"
                        >
                            {{ $t('words.whatsapp-csv-list') }}
                        </button>
                    </div>

                    <div v-if="newChatRecipientMode === 'csv'">
                        <component :is="csvWizardComponent" :templates="templates" />
                    </div>

                    <div v-else class="space-y-4 mb-4 overflow-y-auto">
                        <div v-if="newChatRecipientMode === 'search'" class="space-y-3">
                            <div v-if="!newChatSelectedCompany">
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ $t('words.new-chat-search-hint') }}</label>
                                <input
                                    v-model="newChatSearch"
                                    @input="onNewChatSearchInput"
                                    type="text"
                                    class="w-full form-input text-sm rounded-md border-gray-200"
                                    :placeholder="$t('words.new-chat-search-placeholder')"
                                />

                                <div v-if="newChatSearching" class="text-xs text-gray-500 mt-2">{{ $t('words.loading') }}...</div>

                                <div v-else-if="newChatSearch.trim().length >= 2" class="mt-2 space-y-3">
                                    <div>
                                        <div class="text-xs text-gray-500 mb-1">{{ $t('words.trainees') }}</div>
                                        <div v-if="newChatTraineeResults.length === 0" class="text-xs text-gray-400">{{ $t('words.no-results') }}</div>
                                        <ul v-else class="border border-gray-200 rounded-md divide-y divide-gray-100 max-h-44 overflow-y-auto">
                                            <li
                                                v-for="trainee in newChatTraineeResults"
                                                :key="'nt-' + trainee.id"
                                            >
                                                <button
                                                    type="button"
                                                    class="w-full text-left px-3 py-2 hover:bg-gray-50"
                                                    :class="newChatSelectedTrainee && newChatSelectedTrainee.id === trainee.id ? 'bg-gray-100' : ''"
                                                    @click="selectNewChatTrainee(trainee)"
                                                >
                                                    <div class="text-sm font-medium text-gray-900 truncate">{{ trainee.name }}</div>
                                                    <div class="text-[11px] text-gray-500 truncate">
                                                        <span dir="ltr">{{ trainee.phone }}</span>
                                                        <span v-if="trainee.identity_number"> · {{ trainee.identity_number }}</span>
                                                        <span v-if="trainee.company_name"> · {{ trainee.company_name }}</span>
                                                    </div>
                                                </button>
                                            </li>
                                        </ul>
                                        <button
                                            v-if="newChatTraineesHasMore"
                                            type="button"
                                            class="mt-1 text-xs text-gray-600 hover:text-gray-900"
                                            :disabled="newChatLoadingMoreTrainees"
                                            @click="loadMoreNewChatTrainees"
                                        >
                                            {{ newChatLoadingMoreTrainees ? ($t('words.loading') + '...') : $t('words.load-more') }}
                                        </button>
                                    </div>

                                    <div>
                                        <div class="text-xs text-gray-500 mb-1">{{ $t('words.companies') }}</div>
                                        <div v-if="newChatCompanyResults.length === 0" class="text-xs text-gray-400">{{ $t('words.no-results') }}</div>
                                        <ul v-else class="border border-gray-200 rounded-md divide-y divide-gray-100 max-h-40 overflow-y-auto">
                                            <li
                                                v-for="company in newChatCompanyResults"
                                                :key="'nc-' + company.id"
                                            >
                                                <button
                                                    type="button"
                                                    class="w-full text-left px-3 py-2 hover:bg-gray-50"
                                                    @click="selectNewChatCompany(company)"
                                                >
                                                    <div class="text-sm font-medium text-gray-900 truncate">{{ company.name }}</div>
                                                    <div class="text-[11px] text-gray-500">
                                                        {{ company.trainees_with_phone_count }} {{ $t('words.trainees') }}
                                                    </div>
                                                </button>
                                            </li>
                                        </ul>
                                        <button
                                            v-if="newChatCompaniesHasMore"
                                            type="button"
                                            class="mt-1 text-xs text-gray-600 hover:text-gray-900"
                                            :disabled="newChatLoadingMoreCompanies"
                                            @click="loadMoreNewChatCompanies"
                                        >
                                            {{ newChatLoadingMoreCompanies ? ($t('words.loading') + '...') : $t('words.load-more') }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <button
                                        type="button"
                                        class="text-xs text-gray-600 hover:text-gray-900"
                                        @click="clearNewChatCompany"
                                    >
                                        ← {{ $t('words.back') }}
                                    </button>
                                    <div class="text-sm font-medium text-gray-900 truncate">{{ newChatSelectedCompany.name }}</div>
                                </div>
                                <input
                                    v-model="newChatCompanyTraineeSearch"
                                    @input="onNewChatCompanyTraineeSearchInput"
                                    type="text"
                                    class="w-full form-input text-xs rounded-md border-gray-200"
                                    :placeholder="$t('words.search-trainee')"
                                />
                                <div v-if="newChatLoadingCompanyTrainees" class="text-xs text-gray-500">{{ $t('words.loading') }}...</div>
                                <ul v-else class="border border-gray-200 rounded-md divide-y divide-gray-100 max-h-52 overflow-y-auto">
                                    <li v-if="newChatCompanyTrainees.length === 0" class="px-3 py-3 text-xs text-gray-400">
                                        {{ $t('words.no-results') }}
                                    </li>
                                    <li
                                        v-for="trainee in newChatCompanyTrainees"
                                        :key="'nct-' + trainee.id"
                                    >
                                        <button
                                            type="button"
                                            class="w-full text-left px-3 py-2 hover:bg-gray-50"
                                            :class="newChatSelectedTrainee && newChatSelectedTrainee.id === trainee.id ? 'bg-gray-100' : ''"
                                            @click="selectNewChatTrainee(trainee)"
                                        >
                                            <div class="text-sm font-medium text-gray-900 truncate">{{ trainee.name }}</div>
                                            <div class="text-[11px] text-gray-500 truncate">
                                                <span dir="ltr">{{ trainee.phone }}</span>
                                                <span v-if="trainee.identity_number"> · {{ trainee.identity_number }}</span>
                                            </div>
                                        </button>
                                    </li>
                                </ul>
                                <button
                                    v-if="newChatCompanyTraineesHasMore"
                                    type="button"
                                    class="text-xs text-gray-600 hover:text-gray-900"
                                    :disabled="newChatLoadingMoreCompanyTrainees"
                                    @click="loadMoreNewChatCompanyTrainees"
                                >
                                    {{ newChatLoadingMoreCompanyTrainees ? ($t('words.loading') + '...') : $t('words.load-more') }}
                                </button>
                            </div>

                            <div
                                v-if="newChatSelectedTrainee"
                                class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-700"
                            >
                                <div class="font-medium text-sm text-gray-900">{{ newChatSelectedTrainee.name }}</div>
                                <div dir="ltr">{{ newChatSelectedTrainee.phone }}</div>
                            </div>
                        </div>

                        <div v-else-if="newChatRecipientMode === 'company'" class="space-y-3">
                            <div v-if="!newChatBulkCompany">
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ $t('words.search-company') }}</label>
                                <input
                                    v-model="newChatBulkCompanySearch"
                                    @input="onNewChatBulkCompanySearchInput"
                                    type="text"
                                    class="w-full form-input text-sm rounded-md border-gray-200"
                                    :placeholder="$t('words.search-company')"
                                />
                                <div v-if="newChatBulkSearching" class="text-xs text-gray-500 mt-2">{{ $t('words.loading') }}...</div>
                                <ul
                                    v-else-if="newChatBulkCompanySearch.trim().length >= 2"
                                    class="mt-2 border border-gray-200 rounded-md divide-y divide-gray-100 max-h-52 overflow-y-auto"
                                >
                                    <li v-if="!newChatBulkCompanyResults.length" class="px-3 py-3 text-xs text-gray-400">
                                        {{ $t('words.no-results') }}
                                    </li>
                                    <li
                                        v-for="company in newChatBulkCompanyResults"
                                        :key="'nbc-' + company.id"
                                    >
                                        <button
                                            type="button"
                                            class="w-full text-left px-3 py-2 hover:bg-gray-50"
                                            @click="selectNewChatBulkCompany(company)"
                                        >
                                            <div class="text-sm font-medium text-gray-900 truncate">{{ company.name }}</div>
                                            <div class="text-[11px] text-gray-500">
                                                {{ $t('words.whatsapp-active-trainees-count', { count: company.active_trainees_count || 0 }) }}
                                            </div>
                                        </button>
                                    </li>
                                </ul>
                            </div>

                            <div v-else class="space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <button
                                        type="button"
                                        class="text-xs text-gray-600 hover:text-gray-900"
                                        @click="clearNewChatBulkCompany"
                                    >
                                        ← {{ $t('words.back') }}
                                    </button>
                                    <div class="text-sm font-medium text-gray-900 truncate">{{ newChatBulkCompany.name }}</div>
                                </div>
                                <p class="text-xs text-gray-500">{{ $t('words.whatsapp-company-bulk-hint') }}</p>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">
                                        {{ $t('words.whatsapp-pending-invoice-month') }}
                                    </label>
                                    <select
                                        v-model="newChatBulkPendingInvoiceMonth"
                                        class="w-full form-select text-sm rounded-md border-gray-200"
                                        :disabled="newChatBulkLoadingPendingMonths"
                                        @change="loadNewChatBulkActiveTrainees"
                                    >
                                        <option value="">{{ $t('words.whatsapp-all-active-trainees') }}</option>
                                        <option
                                            v-for="monthOption in newChatBulkPendingMonths"
                                            :key="'npm-' + monthOption.month"
                                            :value="monthOption.month"
                                        >
                                            {{ monthOption.label }} {{ monthOption.year }} ({{ monthOption.invoice_count }})
                                        </option>
                                    </select>
                                    <p
                                        v-if="newChatBulkLoadingPendingMonths"
                                        class="text-[11px] text-gray-400 mt-1"
                                    >
                                        {{ $t('words.loading') }}...
                                    </p>
                                    <p
                                        v-else-if="newChatBulkPendingMonthsError"
                                        class="text-[11px] text-red-500 mt-1"
                                    >
                                        {{ newChatBulkPendingMonthsError }}
                                    </p>
                                    <p
                                        v-else-if="!newChatBulkPendingMonths.length"
                                        class="text-[11px] text-gray-400 mt-1"
                                    >
                                        {{ $t('words.whatsapp-no-pending-invoice-months') }}
                                    </p>
                                </div>
                                <div class="text-sm text-gray-700">
                                    <span v-if="newChatBulkLoadingTrainees">{{ $t('words.loading') }}...</span>
                                    <span v-else-if="newChatBulkPendingInvoiceMonth">
                                        {{ $t('words.whatsapp-trainees-with-pending-count', { count: newChatBulkActiveCount }) }}
                                    </span>
                                    <span v-else>
                                        {{ $t('words.whatsapp-active-trainees-count', { count: newChatBulkActiveCount }) }}
                                    </span>
                                </div>
                                <ul
                                    v-if="!newChatBulkLoadingTrainees && newChatBulkActiveTrainees.length"
                                    class="border border-gray-200 rounded-md divide-y divide-gray-100 max-h-40 overflow-y-auto"
                                >
                                    <li
                                        v-for="trainee in newChatBulkActiveTrainees"
                                        :key="'nbat-' + trainee.id"
                                        class="px-3 py-2 text-sm flex items-center justify-between gap-2"
                                    >
                                        <span class="truncate font-medium text-gray-800">{{ trainee.name }}</span>
                                        <span class="text-xs text-gray-500 shrink-0" dir="ltr">{{ trainee.phone }}</span>
                                    </li>
                                </ul>
                                <div
                                    v-else-if="!newChatBulkLoadingTrainees"
                                    class="text-xs text-gray-400 px-1 py-2"
                                >
                                    {{ newChatBulkPendingInvoiceMonth
                                        ? $t('words.whatsapp-company-no-pending-invoice-trainees')
                                        : $t('words.whatsapp-company-no-active-trainees') }}
                                </div>
                            </div>
                        </div>

                        <div v-else>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ $t('words.phone-number') }}</label>
                            <input
                                v-model="newChatPhone"
                                type="text"
                                class="w-full form-input text-sm rounded-md border-gray-200"
                                placeholder="+9665xxxxxxxx"
                                dir="ltr"
                                @input="newChatSelectedTrainee = null"
                            />
                            <p class="text-[11px] text-gray-400 mt-1">{{ $t('words.custom-phone-hint') }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ $t('words.whatsapp-templates') }}</label>
                            <select
                                v-model="newChatTemplateSid"
                                @change="onNewChatTemplateChange"
                                class="w-full form-select text-sm rounded-md border-gray-200"
                            >
                                <option value="">{{ $t('words.select-template') }}</option>
                                <option
                                    v-for="template in templates"
                                    :key="template.sid"
                                    :value="template.sid"
                                >
                                    {{ template.friendly_name }} ({{ template.language }})
                                </option>
                            </select>
                        </div>

                        <div v-if="newChatTemplate" class="p-3 bg-gray-50 rounded-md text-sm whitespace-pre-wrap text-gray-800">
                            {{ previewNewChatTemplateBody }}
                        </div>

                        <div v-if="newChatTemplate && newChatManualVariables.length" class="space-y-2">
                            <div class="text-xs font-medium text-gray-700">{{ $t('words.template-variables') }}</div>
                            <div
                                v-for="variableKey in newChatManualVariables"
                                :key="variableKey"
                            >
                                <input
                                    v-model="newChatTemplateVariables[variableKey]"
                                    type="text"
                                    class="w-full form-input text-xs rounded-md border-gray-200"
                                    :placeholder="templateVariableLabel(variableKey, newChatTemplate)"
                                />
                            </div>
                            <p
                                v-if="newChatRecipientMode === 'company'"
                                class="text-[11px] text-gray-400"
                            >
                                {{ $t('words.whatsapp-company-auto-vars-hint') }}
                            </p>
                        </div>
                        <p
                            v-else-if="newChatTemplate && newChatTemplate.variables && newChatTemplate.variables.length"
                            class="text-xs text-gray-500"
                        >
                            {{ $t('words.whatsapp-auto-filled-variables') }}
                        </p>
                    </div>

                    <div v-if="newChatRecipientMode !== 'csv'" class="flex justify-end gap-2 pt-3 border-t">
                        <button
                            @click="$modal.hide('newChatModal')"
                            class="px-4 py-2 rounded-md text-sm font-medium bg-gray-100 hover:bg-gray-200 text-gray-700 transition"
                        >
                            {{ $t('words.cancel') }}
                        </button>
                        <button
                            v-if="newChatRecipientMode === 'company'"
                            @click="sendNewChatCompanyTemplate"
                            :disabled="sendingNewChat || !newChatBulkCompany || !newChatTemplateSid || !newChatBulkActiveCount || newChatBulkLoadingTrainees"
                            class="px-4 py-2 rounded-md text-sm font-medium bg-green-600 hover:bg-green-700 text-white disabled:opacity-50 transition"
                        >
                            {{ sendingNewChat ? $t('words.sending') : $t('words.whatsapp-send-template-to-company') }}
                        </button>
                        <button
                            v-else
                            @click="sendNewChatTemplate"
                            :disabled="sendingNewChat || !newChatPhone.trim() || !newChatTemplateSid"
                            class="px-4 py-2 rounded-md text-sm font-medium bg-green-600 hover:bg-green-700 text-white disabled:opacity-50 transition"
                        >
                            {{ $t('words.send') }}
                        </button>
                    </div>

                    <p v-if="newChatRecipientMode !== 'csv' && newChatError" class="mt-2 text-xs text-red-600">{{ newChatError }}</p>
                    <p v-if="newChatRecipientMode !== 'csv' && newChatSuccess" class="mt-2 text-xs text-green-600">{{ newChatSuccess }}</p>
                </div>
            </modal>

            <modal name="quickRepliesModal" :width="560" :height="'auto'" :scrollable="true">
                <div class="p-5">
                    <div class="flex items-center justify-between pb-4 border-b mb-4">
                        <h3 class="text-lg font-bold text-gray-800">{{ $t('words.quick-replies') }}</h3>
                        <button type="button" @click="closeQuickRepliesModal" class="text-gray-400 hover:text-gray-600">
                            <ion-icon name="close-outline" class="w-6 h-6"></ion-icon>
                        </button>
                    </div>

                    <div class="flex items-center gap-2 mb-3">
                        <input
                            v-model="quickReplySearch"
                            type="text"
                            class="flex-1 form-input text-sm rounded-md border-gray-200"
                            :placeholder="$t('words.search') + '...'"
                        />
                        <button
                            type="button"
                            class="text-sm border border-gray-200 px-3 py-2 rounded-md hover:bg-gray-50 whitespace-nowrap"
                            @click="showNewQuickReply = !showNewQuickReply"
                        >
                            {{ $t('words.new-quick-reply') }}
                        </button>
                    </div>

                    <div v-if="showNewQuickReply" class="border border-gray-200 rounded-md p-3 space-y-2 bg-gray-50 mb-3">
                        <input
                            v-model="newQuickReplyTitle"
                            type="text"
                            class="w-full form-input text-sm rounded-md border-gray-200"
                            :placeholder="$t('words.quick-reply-title')"
                        />
                        <textarea
                            v-model="newQuickReplyBody"
                            rows="3"
                            class="w-full form-input text-sm rounded-md border-gray-200"
                            :placeholder="$t('words.quick-reply-body')"
                        ></textarea>
                        <div class="flex justify-end gap-2">
                            <button
                                type="button"
                                class="text-sm text-gray-500 hover:text-gray-800"
                                @click="showNewQuickReply = false"
                            >
                                {{ $t('words.cancel') }}
                            </button>
                            <button
                                type="button"
                                class="text-sm bg-gray-800 text-white px-3 py-1.5 rounded-md disabled:opacity-50"
                                :disabled="savingQuickReply || !newQuickReplyTitle.trim() || !newQuickReplyBody.trim()"
                                @click="saveQuickReply"
                            >
                                {{ savingQuickReply ? $t('words.saving') : $t('words.save') }}
                            </button>
                        </div>
                    </div>

                    <div v-if="loadingQuickReplies" class="text-sm text-gray-500 py-6 text-center">
                        {{ $t('words.loading') }}...
                    </div>
                    <div v-else-if="filteredQuickReplies.length === 0" class="text-sm text-gray-400 py-6 text-center">
                        {{ $t('words.no-quick-replies') }}
                    </div>
                    <ul v-else class="divide-y divide-gray-100 border border-gray-200 rounded-md max-h-80 overflow-y-auto">
                        <li
                            v-for="reply in filteredQuickReplies"
                            :key="'qr-modal-' + reply.id"
                            class="px-3 py-2.5 hover:bg-gray-50 flex items-start justify-between gap-2"
                        >
                            <button
                                type="button"
                                class="text-left min-w-0 flex-1"
                                @click="useQuickReply(reply)"
                            >
                                <div class="text-sm font-medium text-gray-900 truncate">{{ reply.title }}</div>
                                <div class="text-xs text-gray-500 mt-0.5 whitespace-pre-wrap" style="display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;">{{ reply.body }}</div>
                            </button>
                            <button
                                type="button"
                                class="text-xs text-red-500 hover:text-red-700 flex-shrink-0"
                                :disabled="deletingQuickReplyId === reply.id"
                                @click.stop="deleteQuickReply(reply)"
                            >
                                {{ $t('words.delete') }}
                            </button>
                        </li>
                    </ul>
                </div>
            </modal>

            <modal
                name="traineeDocumentModal"
                :width="traineeDocumentModalWidth"
                :height="traineeDocumentModalHeight"
                :scrollable="false"
                :click-to-close="true"
                :adaptive="true"
            >
                <div class="h-full flex flex-col max-w-full overflow-hidden">
                    <div class="flex items-center justify-between gap-2 px-3 sm:px-5 py-3 border-b flex-shrink-0 bg-white sticky top-0 z-10">
                        <h3 class="text-sm sm:text-base font-bold text-gray-800 truncate min-w-0">
                            {{ traineeDocumentModalTitle }}
                        </h3>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <a
                                v-if="traineeDocumentPreviewUrl"
                                href="#"
                                class="text-xs text-gray-600 hover:text-gray-900 underline whitespace-nowrap"
                                @click.prevent="downloadTraineeDocument"
                            >
                                {{ $t('words.download') }}
                            </a>
                            <button
                                type="button"
                                @click="navigateChatBack"
                                class="text-gray-500 hover:text-gray-800 p-1 rounded border border-gray-200 bg-white"
                                :aria-label="$t('words.cancel')"
                            >
                                <ion-icon name="close-outline" class="w-6 h-6"></ion-icon>
                            </button>
                        </div>
                    </div>
                    <div class="flex-1 min-h-0 bg-gray-100 p-2 sm:p-3 overflow-auto">
                        <div
                            v-if="traineeDocumentLoading"
                            class="h-full flex items-center justify-center text-xs text-gray-500 py-10"
                        >
                            {{ $t('words.loading') }}...
                        </div>
                        <div
                            v-else-if="traineeDocumentError"
                            class="h-full flex flex-col items-center justify-center text-center px-4 py-8 space-y-3"
                        >
                            <p class="text-sm text-red-600">{{ traineeDocumentError }}</p>
                        </div>
                        <img
                            v-else-if="traineeDocumentModalIsImage && traineeDocumentPreviewUrl"
                            :src="traineeDocumentPreviewUrl"
                            :alt="traineeDocumentModalTitle"
                            class="max-w-full mx-auto object-contain"
                            style="max-height: calc(100vh - 140px);"
                        />
                        <iframe
                            v-else-if="traineeDocumentModalIsPdf && traineeDocumentPreviewUrl && !isNarrowViewport"
                            :src="traineeDocumentPreviewUrl"
                            class="w-full rounded-md bg-white border border-gray-200"
                            style="min-height: 420px; height: calc(100vh - 160px);"
                            title="trainee-document"
                        ></iframe>
                        <div
                            v-else-if="traineeDocumentModalIsPdf && traineeDocumentPreviewUrl"
                            class="h-full flex flex-col items-center justify-center text-center px-4 py-8 space-y-4"
                        >
                            <p class="text-sm font-medium text-gray-800 break-words">
                                {{ traineeDocumentModalTitle }}
                            </p>
                            <p class="text-xs text-gray-500">
                                {{ $t('words.chat-open-pdf-hint') }}
                            </p>
                            <button
                                type="button"
                                class="inline-flex items-center justify-center px-4 py-2 rounded-md text-sm font-medium text-white bg-green-600 hover:bg-green-700"
                                @click="shareOrDownloadTraineeDocument"
                            >
                                {{ $t('words.chat-open-file') }}
                            </button>
                            <button
                                type="button"
                                class="text-xs text-gray-600 underline"
                                @click="downloadTraineeDocument"
                            >
                                {{ $t('words.download') }}
                            </button>
                        </div>
                        <div
                            v-else-if="traineeDocumentPreviewUrl"
                            class="flex-1 flex flex-col items-center justify-center text-center px-4 py-8 space-y-4"
                        >
                            <p class="text-sm text-gray-700 break-words">
                                {{ traineeDocumentModal.name || traineeDocumentModalTitle }}
                            </p>
                            <button
                                type="button"
                                class="inline-flex items-center justify-center px-4 py-2 rounded-md text-sm font-medium text-white bg-green-600 hover:bg-green-700"
                                @click="shareOrDownloadTraineeDocument"
                            >
                                {{ $t('words.chat-open-file') }}
                            </button>
                        </div>
                    </div>
                </div>
            </modal>
        </portal>
        <portal to="company-filter-modal">
            <modal name="companyFilterModal" :width="560" :height="'auto'" :scrollable="true">
                <div class="bg-white rounded-xl shadow-2xl p-5 flex flex-col max-h-[85vh]">
                    <div class="flex items-center justify-between pb-4 border-b mb-4">
                        <h3 class="text-lg font-bold text-gray-800">{{ $t('words.chat-company-filter-modal-title') }}</h3>
                        <button type="button" @click="closeCompanyFilterModal()" class="text-gray-400 hover:text-gray-600">
                            <ion-icon name="close-outline" class="w-6 h-6"></ion-icon>
                        </button>
                    </div>

                    <div class="space-y-3 overflow-y-auto flex-1">
                        <div>
                            <input
                                v-model="companyFilterSearch"
                                type="text"
                                class="w-full form-input text-sm rounded-md border-gray-200"
                                :placeholder="$t('words.chat-company-filter-search-placeholder')"
                                @input="onCompanyFilterSearchInput"
                            />
                            <div v-if="companyFilterSearching" class="text-xs text-gray-500 mt-2">{{ $t('words.loading') }}...</div>
                            <ul
                                v-else-if="companyFilterSearch.trim().length >= 2"
                                class="mt-2 max-h-40 overflow-y-auto border border-gray-200 rounded-md divide-y divide-gray-100"
                            >
                                <li v-if="companyFilterResults.length === 0" class="px-3 py-2 text-xs text-gray-400">
                                    {{ $t('words.no-results') }}
                                </li>
                                <li
                                    v-for="company in companyFilterResults"
                                    :key="'company-filter-result-' + company.id"
                                >
                                    <button
                                        type="button"
                                        class="w-full text-start px-3 py-2 text-sm hover:bg-gray-50"
                                        :disabled="isCompanyInFilterDraft(company.id)"
                                        :class="isCompanyInFilterDraft(company.id) ? 'opacity-50 cursor-default' : ''"
                                        @click="addCompanyToFilterDraft(company)"
                                    >
                                        {{ company.name }}
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <div>
                            <p class="text-xs font-medium text-gray-600 mb-2">
                                {{ $t('words.chat-company-filter-edit') }}
                                <span v-if="companyFilterDraft.length">({{ formatCompanyFilterCount(companyFilterDraft.length) }})</span>
                            </p>
                            <ul
                                v-if="companyFilterDraft.length"
                                class="border border-gray-200 rounded-md divide-y divide-gray-100 max-h-48 overflow-y-auto"
                            >
                                <li
                                    v-for="company in companyFilterDraft"
                                    :key="'company-filter-draft-' + company.id"
                                    class="flex items-center justify-between gap-2 px-3 py-2 text-sm"
                                >
                                    <span class="truncate">{{ company.name }}</span>
                                    <button
                                        type="button"
                                        class="text-xs text-red-600 hover:underline flex-shrink-0"
                                        @click="removeCompanyFromFilterDraft(company.id)"
                                    >
                                        {{ $t('words.chat-company-filter-remove') }}
                                    </button>
                                </li>
                            </ul>
                            <p v-else class="text-xs text-gray-400">{{ $t('words.chat-company-filter-empty-selection') }}</p>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-4 border-t mt-4">
                        <button
                            type="button"
                            class="px-4 py-2 text-sm rounded-md border border-gray-200 hover:bg-gray-50"
                            :disabled="savingCompanyFilters"
                            @click="closeCompanyFilterModal()"
                        >
                            {{ $t('words.cancel') }}
                        </button>
                        <button
                            type="button"
                            class="px-4 py-2 text-sm rounded-md text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50"
                            :disabled="savingCompanyFilters"
                            @click="confirmCompanyFilterModal()"
                        >
                            <span v-if="savingCompanyFilters">{{ $t('words.chat-company-filter-saving') }}</span>
                            <span v-else>{{ $t('words.chat-company-filter-confirm') }}</span>
                        </button>
                    </div>
                </div>
            </modal>
        </portal>
        <maqsam-dialer-panel
            ref="maqsamDialer"
            :configured="maqsamConfigured"
            :agent-email="maqsamAgentEmail"
        />
    </chat-layout>
</template>

<script>
import ChatLayout from '@/Layouts/ChatLayout';
import WhatsAppTemplatesManager from '@/Components/WhatsAppTemplatesManager';
import FinanceWhatsAppCsvWizard from '@/Components/FinanceWhatsAppCsvWizard';
import MaqsamDialerPanel from '@/Components/MaqsamDialerPanel';
import axios from 'axios';
import throttle from 'lodash/throttle';
import moment from 'moment';
import 'moment/locale/ar';
import confetti from 'canvas-confetti';
import {
    getExistingPushSubscription,
    isChatPwaStandalone,
    registerChatServiceWorker,
    subscribeChatPush,
    syncChatAppBadge,
    unsubscribeChatPush,
} from '@/chat-pwa';

export default {
    metaInfo() {
        const base = this.$t('words.chat');
        const count = this.unreadConversationCount;

        return {
            title: count > 0 ? `(${count}) ${base}` : base,
        };
    },
    components: {
        ChatLayout,
        WhatsAppTemplatesManager,
        FinanceWhatsAppCsvWizard,
        MaqsamDialerPanel,
    },
    props: {
        configured: {
            type: Boolean,
            default: false,
        },
        canManageTemplates: {
            type: Boolean,
            default: false,
        },
        maqsamConfigured: {
            type: Boolean,
            default: false,
        },
        maqsamAgentEmail: {
            type: String,
            default: '',
        },
    },
        data() {
        return {
            conversations: [],
            conversationSearch: '',
            loadingConversations: false,
            selectedConversation: null,
            messages: [],
            loadingMessages: false,
            loadingOlderMessages: false,
            hasMoreMessages: false,
            nextBefore: null,
            nextBeforeId: null,
            sendMode: 'freeform',
            isNoteMode: false,
            composerMode: 'freeform',
            messageBody: '',
            pressEnterToSend: false,
            conversationGroupMode: 'latest',
            agentCompanyFilters: [],
            companyFilterPaused: false,
            companyFilterDraft: [],
            companyFilterSearch: '',
            companyFilterSearchDebounce: null,
            companyFilterSearching: false,
            companyFilterResults: [],
            savingCompanyFilters: false,
            threadHeight: 220,
            threadResizing: false,
            threadResizeStartY: 0,
            threadResizeStartHeight: 0,
            showEmojiPicker: false,
            emojiPickerStyle: {
                top: '0px',
                left: '0px',
                zIndex: 9999,
            },
            quickReplies: [],
            loadingQuickReplies: false,
            quickReplySearch: '',
            showNewQuickReply: false,
            newQuickReplyTitle: '',
            newQuickReplyBody: '',
            savingQuickReply: false,
            deletingQuickReplyId: null,
            emojiList: [
                '😀','😁','😂','🤣','😊','😍','😘','😎','🤔','😅',
                '😢','😭','😤','😡','👍','👎','👏','🙏','👌','✌️',
                '🤝','💪','🔥','✨','🎉','❤️','💔','✅','❌','⭐',
                '📌','📎','📞','💬','📱','🏠','🏢','💰','🧾','📅',
                '⏰','👋','🙂','😉','🤗','😴','🤒','😷','🙌','🫡',
                '💯','❗','❓','📝','🗂️','🔗','🟢','🔴','🟡','⚪',
                '🇸🇦','🤝','💼','📊','📈','🧾','🔑','🛡️','📬','🛠️',
            ],
            templates: [],
            loadingTemplates: false,
            selectedTemplateSid: '',
            selectedTemplate: null,
            templateVariables: {},
            sending: false,
            errorMessage: '',
            successMessage: '',
            newChatPhone: '',
            newChatTemplateSid: '',
            newChatTemplate: null,
            newChatTemplateVariables: {},
            sendingNewChat: false,
            newChatError: '',
            newChatRecipientMode: 'search',
            newChatSearch: '',
            newChatSearchDebounce: null,
            newChatSearching: false,
            newChatTraineeResults: [],
            newChatTraineePage: 1,
            newChatTraineesHasMore: false,
            newChatLoadingMoreTrainees: false,
            newChatCompanyResults: [],
            newChatCompanyPage: 1,
            newChatCompaniesHasMore: false,
            newChatLoadingMoreCompanies: false,
            newChatSelectedCompany: null,
            newChatCompanyTrainees: [],
            newChatCompanyTraineePage: 1,
            newChatCompanyTraineesHasMore: false,
            newChatLoadingCompanyTrainees: false,
            newChatLoadingMoreCompanyTrainees: false,
            newChatCompanyTraineeSearch: '',
            newChatCompanyTraineeSearchDebounce: null,
            newChatSelectedTrainee: null,
            newChatSuccess: '',
            newChatBulkCompanySearch: '',
            newChatBulkCompanySearchDebounce: null,
            newChatBulkSearching: false,
            newChatBulkCompanyResults: [],
            newChatBulkCompany: null,
            newChatBulkActiveTrainees: [],
            newChatBulkActiveCount: 0,
            newChatBulkLoadingTrainees: false,
            newChatBulkPendingInvoiceMonth: '',
            newChatBulkPendingMonths: [],
            newChatBulkPendingMonthsError: '',
            newChatBulkLoadingPendingMonths: false,
            conversationPage: 1,
            totalPages: 1,
            totalConversations: 0,
            conversationCounts: {
                open: 0,
                pending: 0,
                closed: 0,
                unassigned: 0,
            },
            listFilter: 'all',
            statusTab: 'open',
            selectedTagFilter: '',
            availableTags: [],
            tagToAttach: '',
            assigningAgent: false,
            showAssignDropdown: false,
            assignableAgents: [],
            loadingAssignableAgents: false,
            assignableAgentsLoaded: false,
            attachingTag: false,
            updatingStatus: false,
            pollInterval: null,
            conversationsReloadTimer: null,
            messagesRefreshTimer: null,
            searchDebounce: null,
            botStatus: null,
            botConfigured: false,
            isStandalonePwa: false,
            pushSupported: false,
            pushEnabled: false,
            pushBusy: false,
            pushConfigured: false,
            deferredInstallPrompt: null,
            pwaErrorMessage: '',
            showTraineeDetailsMobile: false,
            chatNavStack: [],
            pausingBot: false,
            windowNowMs: Date.now(),
            messagingWindowTimer: null,
            traineeContext: null,
            loadingTraineeContext: false,
            traineeDocumentModal: null,
            traineeDocumentPreviewUrl: null,
            traineeDocumentLoading: false,
            traineeDocumentError: null,
            copiedInvoiceId: null,
            copyLinkTimer: null,
            viewportWidth: typeof window !== 'undefined' ? window.innerWidth : 1024,
            maqsamDialing: false,
            maqsamWindowOpen: false,
            maqsamWindowTimer: null,
        };
    },
    computed: {
        csvWizardComponent() {
            return FinanceWhatsAppCsvWizard;
        },
        isChatOnlyShell() {
            const fromServer = !!(this.$page && this.$page.props && this.$page.props.chatAppMode);
            return !!(this.isStandalonePwa || fromServer);
        },
        maqsamCallButtonLabel() {
            if (this.maqsamDialing) {
                return this.$t('words.caller-connecting');
            }
            if (this.maqsamWindowOpen) {
                return this.$t('words.caller-call-active');
            }
            return this.$t('words.caller-call-login');
        },
        composerCompanyName() {
            const fromContext = this.traineeContext && this.traineeContext.trainee
                ? String(this.traineeContext.trainee.company_name || '').trim()
                : '';
            if (fromContext) {
                return fromContext;
            }
            const trainee = this.selectedConversation && this.selectedConversation.trainee;
            return trainee ? String(trainee.company_name || '').trim() : '';
        },
        composerCompanyUrl() {
            const fromContext = this.traineeContext && this.traineeContext.trainee
                ? this.traineeContext.trainee.company_show_url
                : null;
            if (fromContext) {
                return fromContext;
            }
            const trainee = this.selectedConversation && this.selectedConversation.trainee;
            return (trainee && trainee.company_show_url) || null;
        },
        isNarrowViewport() {
            return this.viewportWidth < 768;
        },
        traineeDocumentModalWidth() {
            if (typeof window === 'undefined') {
                return 860;
            }
            return Math.max(280, Math.min(860, window.innerWidth - 24));
        },
        traineeDocumentModalHeight() {
            if (typeof window === 'undefined') {
                return 720;
            }
            return Math.max(320, Math.min(720, window.innerHeight - 32));
        },
        traineeDocumentButtons() {
            const docs = (this.traineeContext && this.traineeContext.documents) || {};
            return [
                {
                    key: 'non_registration_proof',
                    label: this.$t('words.non-registration-proof'),
                    available: !!(docs.non_registration_proof && docs.non_registration_proof.url),
                    document: docs.non_registration_proof || null,
                },
                {
                    key: 'gosi_certificate',
                    label: this.$t('words.gosi-certificate'),
                    available: !!(docs.gosi_certificate && docs.gosi_certificate.url),
                    document: docs.gosi_certificate || null,
                },
                {
                    key: 'qiwa_contract',
                    label: this.$t('words.qiwa-contract'),
                    available: !!(docs.qiwa_contract && docs.qiwa_contract.url),
                    document: docs.qiwa_contract || null,
                },
            ];
        },
        traineeDocumentModalTitle() {
            return (this.traineeDocumentModal && this.traineeDocumentModal.label) || '';
        },
        traineeDocumentDownloadName() {
            const doc = this.traineeDocumentModal;
            if (!doc) {
                return 'document.pdf';
            }
            let name = String(doc.name || doc.label || 'document').trim() || 'document';
            if (this.traineeDocumentModalIsPdf && !/\.pdf$/i.test(name)) {
                name += '.pdf';
            }
            return name;
        },
        traineeDocumentModalIsImage() {
            const doc = this.traineeDocumentModal;
            if (!doc || !doc.url) {
                return false;
            }
            const mime = (doc.mime_type || '').toLowerCase();
            if (mime.startsWith('image/')) {
                return true;
            }
            return this.guessMediaType(doc.url || doc.name).startsWith('image/');
        },
        traineeDocumentModalIsPdf() {
            const doc = this.traineeDocumentModal;
            if (!doc || !doc.url) {
                return false;
            }
            const mime = (doc.mime_type || '').toLowerCase();
            if (mime === 'application/pdf' || mime.includes('pdf')) {
                return true;
            }
            const source = String(doc.url || doc.name || '').toLowerCase();
            return /\.pdf(\?|$)/.test(source);
        },
        messagingWindow() {
            return (this.selectedConversation && this.selectedConversation.messaging_window) || null;
        },
        messagingWindowRemainingSeconds() {
            const now = this.windowNowMs;
            const window = this.messagingWindow;
            if (!window) {
                return 0;
            }
            let expiresMs = null;
            if (window.expires_at) {
                const parsed = Date.parse(window.expires_at);
                if (!Number.isNaN(parsed)) {
                    expiresMs = parsed;
                }
            }
            if (expiresMs === null && window.last_inbound_at) {
                const lastMs = Date.parse(window.last_inbound_at);
                if (!Number.isNaN(lastMs)) {
                    expiresMs = lastMs + (24 * 60 * 60 * 1000);
                }
            }
            if (expiresMs === null) {
                return 0;
            }
            return Math.max(0, Math.floor((expiresMs - now) / 1000));
        },
        messagingWindowIsOpen() {
            if (!this.selectedConversation) {
                return null;
            }
            return this.messagingWindowRemainingSeconds > 0;
        },
        messagingWindowLabel() {
            if (!this.selectedConversation) {
                return '';
            }
            if (this.messagingWindowRemainingSeconds <= 0) {
                return this.$t('words.whatsapp-window-locked');
            }
            return this.$t('words.whatsapp-window-open') + ' · ' + this.formatCountdown(this.messagingWindowRemainingSeconds);
        },
        messagingWindowBadgeClass() {
            if (this.messagingWindowIsOpen === false) {
                return 'bg-red-50 border-red-200 text-red-800';
            }
            if (this.messagingWindowRemainingSeconds <= 3600) {
                return 'bg-amber-50 border-amber-200 text-amber-900';
            }
            return 'bg-emerald-50 border-emerald-200 text-emerald-800';
        },
        composerStatusActions() {
            const status = (this.selectedConversation && this.selectedConversation.status) || 'open';
            const actions = {
                open: {
                    status: 'open',
                    label: this.$t('words.chat-action-open'),
                    buttonClass: 'bg-white text-green-700 border-green-300 hover:bg-green-50',
                },
                pending: {
                    status: 'pending',
                    label: this.$t('words.chat-action-pending'),
                    buttonClass: 'bg-white text-orange-700 border-orange-300 hover:bg-orange-50',
                },
                closed: {
                    status: 'closed',
                    label: this.$t('words.chat-action-close'),
                    buttonClass: 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50',
                },
            };

            if (status === 'closed') {
                return [actions.open, actions.pending];
            }
            if (status === 'pending') {
                return [actions.closed, actions.open];
            }
            return [actions.closed, actions.pending];
        },
        conversationsGroupedByCompany() {
            const groupsMap = {};
            const order = [];
            const unassignedKey = '__unassigned__';
            const unassignedLabel = this.$t('words.not-assigned-to-a-company');

            (this.conversations || []).forEach((conv) => {
                const companyName = conv && conv.trainee && conv.trainee.company_name
                    ? String(conv.trainee.company_name).trim()
                    : '';
                const key = companyName || unassignedKey;
                const label = companyName || unassignedLabel;

                if (!groupsMap[key]) {
                    groupsMap[key] = {
                        key,
                        label,
                        conversations: [],
                    };
                    order.push(key);
                }

                groupsMap[key].conversations.push(conv);
            });

            return order
                .sort((a, b) => {
                    if (a === unassignedKey) {
                        return 1;
                    }
                    if (b === unassignedKey) {
                        return -1;
                    }
                    return groupsMap[a].label.localeCompare(groupsMap[b].label, undefined, { sensitivity: 'base' });
                })
                .map((key) => groupsMap[key]);
        },
        conversationsGroupedByAgent() {
            const groupsMap = {};
            const order = [];
            const unassignedKey = '__unassigned__';
            const unassignedLabel = this.$t('words.chat-filter-unassigned');

            (this.conversations || []).forEach((conv) => {
                const primaryAgent = conv && conv.agents && conv.agents[0];
                const key = primaryAgent ? String(primaryAgent.id) : unassignedKey;
                const name = primaryAgent ? String(primaryAgent.name).trim() : unassignedLabel;

                if (!groupsMap[key]) {
                    groupsMap[key] = {
                        key,
                        label: name,
                        conversations: [],
                    };
                    order.push(key);
                }

                groupsMap[key].conversations.push(conv);
            });

            return order
                .sort((a, b) => {
                    if (a === unassignedKey) {
                        return 1;
                    }
                    if (b === unassignedKey) {
                        return -1;
                    }
                    return groupsMap[a].label.localeCompare(groupsMap[b].label, undefined, { sensitivity: 'base' });
                })
                .map((key) => {
                    const group = groupsMap[key];

                    return {
                        ...group,
                        label: `${group.label} (${group.conversations.length})`,
                    };
                });
        },
        conversationSidebarGroups() {
            if (this.conversationGroupMode === 'company') {
                return this.conversationsGroupedByCompany;
            }
            if (this.conversationGroupMode === 'agent') {
                return this.conversationsGroupedByAgent;
            }

            return [{
                key: 'latest',
                label: null,
                conversations: this.conversations || [],
            }];
        },
        botStatusLabel() {
            if (!this.botStatus) {
                return this.$t('words.loading') + '...';
            }
            if (!this.botStatus.workflow_assigned && !this.botStatus.ai_enabled) {
                return this.$t('words.bot-not-assigned');
            }
            if (this.botStatus.is_paused) {
                return this.$t('words.bot-paused');
            }
            return this.$t('words.bot-active');
        },
        botStatusTitle() {
            if (!this.botStatus) {
                return '';
            }
            if (this.botStatus.workflow_name) {
                const name = this.botStatus.workflow_name;
                if (this.botStatus.is_paused && this.botStatus.paused_until) {
                    return name + ' · ' + this.formatPausedUntil(this.botStatus.paused_until);
                }
                return name;
            }
            if (this.botStatus.is_paused && this.botStatus.paused_until) {
                return this.formatPausedUntil(this.botStatus.paused_until);
            }
            return '';
        },
        botStatusBadgeClass() {
            if (!this.botStatus) {
                return 'bg-gray-100 border-gray-200 text-gray-500';
            }
            if (!this.botStatus.workflow_assigned && !this.botStatus.ai_enabled) {
                return 'bg-gray-100 border-gray-200 text-gray-600';
            }
            if (this.botStatus.is_paused) {
                return 'bg-orange-100 border-orange-200 text-orange-900';
            }
            return 'bg-green-100 border-green-200 text-green-800';
        },
        canPauseBot() {
            if (!this.botStatus) {
                return false;
            }
            if (typeof this.botStatus.can_pause === 'boolean') {
                return this.botStatus.can_pause;
            }
            return !!((this.botStatus.workflow_assigned || this.botStatus.ai_enabled) && !this.botStatus.is_paused);
        },
        canResumeBot() {
            if (!this.botStatus) {
                return false;
            }
            if (typeof this.botStatus.can_resume === 'boolean') {
                return this.botStatus.can_resume;
            }
            return !!((this.botStatus.workflow_assigned || this.botStatus.ai_enabled) && this.botStatus.is_paused);
        },
        pauseBotButtonLabel() {
            const minutes = Number(this.botStatus?.pause_minutes) || 720;
            const hours = this.botStatus?.pause_hours != null
                ? Number(this.botStatus.pause_hours)
                : (minutes >= 60 && minutes % 60 === 0 ? minutes / 60 : null);

            if (hours) {
                return this.$t('words.pause-bot-hours', { hours });
            }

            return this.$t('words.pause-bot-minutes', { minutes });
        },
        accountStatusLabel() {
            const status = this.traineeContext && this.traineeContext.account_status;
            if (!status) {
                return '';
            }
            if (status.is_suspended) {
                return this.$t('words.account-suspended');
            }
            if (status.is_blocked) {
                return this.$t('words.account-blocked');
            }
            return this.$t('words.account-active');
        },
        accountStatusInlineClass() {
            const status = this.traineeContext && this.traineeContext.account_status;
            if (!status) {
                return 'text-gray-700';
            }
            if (status.is_suspended) {
                return 'text-red-700 font-medium';
            }
            if (status.is_blocked) {
                return 'text-orange-800 font-medium';
            }
            return 'text-green-700';
        },
        selectedConversationAccountStatusLabel() {
            return this.conversationAccountStatusLabel(this.selectedConversation);
        },
        selectedConversationAccountStatusClass() {
            return this.conversationAccountStatusClass(this.selectedConversation);
        },
        selectedConversationAccountReason() {
            return this.conversationAccountReason(this.selectedConversation);
        },
        manualTemplateVariables() {
            if (!this.selectedTemplate) {
                return [];
            }
            return this.selectedTemplate.manual_variables || this.selectedTemplate.variables || [];
        },
        newChatManualVariables() {
            if (!this.newChatTemplate) {
                return [];
            }
            return this.newChatTemplate.manual_variables || this.newChatTemplate.variables || [];
        },
        previewTemplateBody() {
            if (!this.selectedTemplate) {
                return '';
            }
            let body = this.selectedTemplate.body_display || this.selectedTemplate.body;
            const values = this.previewVariableValues(this.selectedTemplate, this.templateVariables);
            Object.keys(values).forEach((key) => {
                const val = values[key] || `{{${key}}}`;
                body = body.replace(new RegExp(`\\{\\{\\s*${key}\\s*\\}\\}`, 'g'), val);
            });
            return body;
        },
        previewNewChatTemplateBody() {
            if (!this.newChatTemplate) {
                return '';
            }
            let body = this.newChatTemplate.body_display || this.newChatTemplate.body;
            const values = this.previewVariableValues(this.newChatTemplate, this.newChatTemplateVariables);
            Object.keys(values).forEach((key) => {
                const val = values[key] || `{{${key}}}`;
                body = body.replace(new RegExp(`\\{\\{\\s*${key}\\s*\\}\\}`, 'g'), val);
            });
            return body;
        },
        filteredQuickReplies() {
            const q = String(this.quickReplySearch || '').trim().toLowerCase();
            if (!q) {
                return this.quickReplies;
            }
            return this.quickReplies.filter((reply) => {
                return String(reply.title || '').toLowerCase().includes(q)
                    || String(reply.body || '').toLowerCase().includes(q);
            });
        },
        unreadConversationCount() {
            return (this.conversations || []).filter((conv) => !!conv.has_unread).length;
        },
        companyFilterIsActive() {
            return this.conversationGroupMode === 'company'
                && !this.companyFilterPaused
                && this.agentCompanyFilters.length > 0;
        },
    },
    watch: {
        unreadConversationCount: {
            immediate: true,
            handler(count) {
                syncChatAppBadge(count);
            },
        },
    },
    mounted() {
        this.initThreadHeight();
        this.loadPressEnterToSendPreference();
        this.loadGroupConversationsPreference();
        this.loadTags();
        this.loadAgentCompanyFilters().finally(() => {
            this.loadConversations();
        });
        this.loadQuickReplies();
        this.subscribeEcho();
        this.startMessagingWindowTicker();
        this.initChatPwa();
        this.updateViewportWidth();
        document.addEventListener('click', this.handleGlobalClick);
        document.addEventListener('keydown', this.handleGlobalKeydown);
        window.addEventListener('beforeinstallprompt', this.onBeforeInstallPrompt);
        window.addEventListener('resize', this.updateViewportWidth);
        window.addEventListener('popstate', this.handleChatPopState);
        if (this.configured) {
            this.loadTemplates();
        }
        if (this.maqsamConfigured) {
            this.startMaqsamWindowWatcher();
        }
    },
    beforeDestroy() {
        this.unsubscribeEcho();
        this.stopPolling();
        this.stopMessagingWindowTicker();
        this.stopMaqsamWindowWatcher();
        this.stopThreadResize();
        document.removeEventListener('click', this.handleGlobalClick);
        document.removeEventListener('keydown', this.handleGlobalKeydown);
        window.removeEventListener('beforeinstallprompt', this.onBeforeInstallPrompt);
        window.removeEventListener('resize', this.updateViewportWidth);
        window.removeEventListener('popstate', this.handleChatPopState);
        if (this.messagesRefreshTimer) {
            clearTimeout(this.messagesRefreshTimer);
            this.messagesRefreshTimer = null;
        }
        if (this.conversationsReloadTimer) {
            clearTimeout(this.conversationsReloadTimer);
            this.conversationsReloadTimer = null;
        }
        if (this.searchDebounce) {
            clearTimeout(this.searchDebounce);
        }
    },
    methods: {
        conversationAccountStatus(conv) {
            return conv && conv.trainee && conv.trainee.account_status
                ? conv.trainee.account_status
                : null;
        },
        conversationAccountStatusLabel(conv) {
            const status = this.conversationAccountStatus(conv);
            if (!status) {
                return '';
            }
            if (status.is_suspended) {
                return this.$t('words.account-suspended');
            }
            if (status.is_blocked) {
                return this.$t('words.account-blocked');
            }
            return '';
        },
        conversationAccountStatusClass(conv) {
            const status = this.conversationAccountStatus(conv);
            if (!status) {
                return 'text-gray-700';
            }
            if (status.is_suspended) {
                return 'text-red-700';
            }
            if (status.is_blocked) {
                return 'text-orange-800';
            }
            return 'text-gray-700';
        },
        conversationAccountReason(conv) {
            const status = this.conversationAccountStatus(conv);
            if (!status || (!status.is_suspended && !status.is_blocked)) {
                return '';
            }
            return status.reason ? String(status.reason) : '';
        },
        startMaqsamWindowWatcher() {
            this.stopMaqsamWindowWatcher();
            this.syncMaqsamWindow();
            this.maqsamWindowTimer = setInterval(this.syncMaqsamWindow, 1000);
        },
        stopMaqsamWindowWatcher() {
            if (this.maqsamWindowTimer) {
                clearInterval(this.maqsamWindowTimer);
                this.maqsamWindowTimer = null;
            }
        },
        async syncMaqsamWindow() {
            const dialer = this.$refs.maqsamDialer;
            if (!dialer || typeof dialer.isWindowOpen !== 'function') {
                this.maqsamWindowOpen = false;
                return;
            }

            try {
                this.maqsamWindowOpen = !!(await dialer.isWindowOpen());
            } catch (error) {
                this.maqsamWindowOpen = false;
            }
        },
        async callSelectedConversation() {
            const phone = this.selectedConversation && this.selectedConversation.phone;
            if (!phone || this.maqsamDialing) {
                return;
            }

            const dialer = this.$refs.maqsamDialer;
            if (!dialer) {
                return;
            }

            this.maqsamDialing = true;
            this.errorMessage = '';
            try {
                await this.syncMaqsamWindow();
                if (!this.maqsamWindowOpen) {
                    await dialer.connectDialer();
                    await this.syncMaqsamWindow();
                    if (dialer.errorMessage) {
                        this.errorMessage = dialer.errorMessage;
                    }
                    return;
                }

                await dialer.dial(phone);
                await this.syncMaqsamWindow();
                if (dialer.errorMessage) {
                    this.errorMessage = dialer.errorMessage;
                }
            } finally {
                this.maqsamDialing = false;
            }
        },
        formatCountdown(totalSeconds) {
            const seconds = Math.max(0, Math.floor(Number(totalSeconds) || 0));
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;
            const pad = (value) => String(value).padStart(2, '0');
            return `${pad(hours)}:${pad(minutes)}:${pad(secs)}`;
        },
        startMessagingWindowTicker() {
            this.stopMessagingWindowTicker();
            this.windowNowMs = Date.now();
            this.messagingWindowTimer = setInterval(() => {
                this.windowNowMs = Date.now();
            }, 1000);
        },
        stopMessagingWindowTicker() {
            if (this.messagingWindowTimer) {
                clearInterval(this.messagingWindowTimer);
                this.messagingWindowTimer = null;
            }
        },
        refreshMessagingWindowFromInbound(message) {
            if (!this.selectedConversation || !message || this.isOutboundMessage(message) || message.is_note) {
                return;
            }
            const sentAt = message.sent_at || message.created_at;
            if (!sentAt) {
                return;
            }
            const lastMs = Date.parse(sentAt);
            if (Number.isNaN(lastMs)) {
                return;
            }
            const expiresMs = lastMs + (24 * 60 * 60 * 1000);
            const remaining = Math.max(0, Math.floor((expiresMs - Date.now()) / 1000));
            this.selectedConversation = {
                ...this.selectedConversation,
                messaging_window: {
                    last_inbound_at: new Date(lastMs).toISOString(),
                    expires_at: new Date(expiresMs).toISOString(),
                    remaining_seconds: remaining,
                    is_open: remaining > 0,
                },
            };
        },
        conversationParams() {
            const params = {
                page: this.conversationPage,
                status: this.statusTab || 'open',
            };
            if (this.conversationSearch.trim()) {
                params.q = this.conversationSearch.trim();
            }
            if (this.listFilter === 'mine') {
                params.mine = 1;
            }
            if (this.listFilter === 'unassigned') {
                params.unassigned = 1;
            }
            if (this.selectedTagFilter) {
                params.tag_id = this.selectedTagFilter;
            }
            if (this.companyFilterIsActive) {
                params.company_ids = this.agentCompanyFilters.map((company) => company.id);
            }
            return params;
        },
        setStatusTab(status) {
            this.statusTab = status;
            this.resetConversationState();
            this.chatNavStack = [];
            this.reloadConversationsFromStart();
        },
        pushChatLayer(layer) {
            if (typeof window === 'undefined' || !window.history || !window.history.pushState) {
                return;
            }
            this.chatNavStack.push(layer);
            try {
                window.history.pushState({ chatLayer: layer }, '');
            } catch (e) {}
        },
        navigateChatBack() {
            if (this.chatNavStack.length > 0 && typeof window !== 'undefined' && window.history) {
                window.history.back();
                return;
            }
            this.handleChatPopState();
        },
        handleChatPopState() {
            const layer = this.chatNavStack.length ? this.chatNavStack.pop() : null;

            if (layer === 'document') {
                this.closeTraineeDocumentModal({ skipHistory: true });
                return;
            }

            if (layer === 'details') {
                this.showTraineeDetailsMobile = false;
                return;
            }

            if (layer === 'conversation') {
                this.resetConversationState();
                return;
            }

            // Fallback if stack drifted: close topmost UI.
            if (this.traineeDocumentModal || this.traineeDocumentLoading) {
                this.closeTraineeDocumentModal({ skipHistory: true });
                return;
            }
            if (this.showTraineeDetailsMobile) {
                this.showTraineeDetailsMobile = false;
                return;
            }
            if (this.selectedConversation) {
                this.resetConversationState();
            }
        },
        openTraineeDetailsMobile() {
            if (this.showTraineeDetailsMobile) {
                return;
            }
            this.showTraineeDetailsMobile = true;
            this.pushChatLayer('details');
        },
        resetConversationState() {
            this.selectedConversation = null;
            this.messages = [];
            this.hasMoreMessages = false;
            this.nextBefore = null;
            this.nextBeforeId = null;
            this.botStatus = null;
            this.traineeContext = null;
            this.errorMessage = '';
            this.successMessage = '';
            this.showAssignDropdown = false;
            this.showTraineeDetailsMobile = false;
            this.closeTraineeDocumentModal({ skipHistory: true });
            this.stopPolling();
        },
        closeConversation() {
            this.navigateChatBack();
        },
        setFilter(filter) {
            this.listFilter = filter;
            this.reloadConversationsFromStart();
        },
        setTagFilter(tagId) {
            this.selectedTagFilter = tagId || '';
            this.reloadConversationsFromStart();
        },
        tagBoxStyle(tag, selected) {
            if (!tag || !tag.color) {
                return selected ? { backgroundColor: '#1f2937', borderColor: '#1f2937', color: '#fff' } : null;
            }
            if (selected) {
                return {
                    backgroundColor: tag.color,
                    borderColor: tag.color,
                    color: '#fff',
                };
            }
            return {
                backgroundColor: tag.color + '22',
                borderColor: tag.color,
                color: '#111827',
            };
        },
        onSearchInput() {
            if (this.searchDebounce) {
                clearTimeout(this.searchDebounce);
            }
            this.searchDebounce = setTimeout(() => {
                this.reloadConversationsFromStart();
            }, 300);
        },
        reloadConversationsFromStart() {
            this.conversationPage = 1;
            this.loadConversations();
        },
        goToPage(page) {
            if (page < 1 || page > this.totalPages) {
                return;
            }
            this.conversationPage = page;
            this.loadConversations();
        },
        agentFirstName(name) {
            const parts = String(name || '').trim().split(/\s+/).filter(Boolean);

            return parts[0] || '?';
        },
        patchConversation(updated) {
            if (!updated || !updated.id) {
                return;
            }
            const currentUserId = this.$page && this.$page.props && this.$page.props.user
                ? this.$page.props.user.id
                : null;
            const merged = {
                ...updated,
                status: updated.status || 'open',
                is_assigned_to_me: currentUserId
                    ? (updated.agents || []).some((agent) => agent.id === currentUserId)
                    : false,
                is_unassigned: !(updated.agents && updated.agents.length),
            };

            const matchesTab = (merged.status || 'open') === this.statusTab;
            const index = this.conversations.findIndex((c) => c.id === merged.id || c.phone === merged.phone);

            if (!matchesTab) {
                if (index !== -1) {
                    this.conversations.splice(index, 1);
                }
                if (this.selectedConversation && (this.selectedConversation.id === merged.id || this.selectedConversation.phone === merged.phone)) {
                    this.selectedConversation = { ...this.selectedConversation, ...merged };
                }
                return;
            }

            if (index !== -1) {
                const existing = this.conversations[index] || {};
                if (merged.unpaid_invoice_count == null && existing.unpaid_invoice_count != null) {
                    merged.unpaid_invoice_count = existing.unpaid_invoice_count;
                }
                this.$set(this.conversations, index, { ...existing, ...merged });
            } else {
                this.conversations.unshift(merged);
            }

            if (this.selectedConversation && (this.selectedConversation.id === merged.id || this.selectedConversation.phone === merged.phone)) {
                this.selectedConversation = { ...this.selectedConversation, ...merged };
            }
        },
        async onStatusChange(event) {
            const status = event.target.value;
            try {
                await this.setConversationStatus(status, event);
            } catch (error) {
                event.target.value = (this.selectedConversation && this.selectedConversation.status) || 'open';
            }
        },
        async setConversationStatus(status, event = null) {
            if (!this.selectedConversation || !this.selectedConversation.id) {
                return;
            }
            if (!status || status === this.selectedConversation.status) {
                return;
            }
            const originElement = event && event.currentTarget
                ? event.currentTarget
                : (event && event.target ? event.target : null);
            this.updatingStatus = true;
            try {
                const { data } = await axios.patch(
                    route('back.chat.conversations.status', this.selectedConversation.id),
                    { status }
                );
                this.patchConversation(data.conversation);
                const leavingTab = (data.conversation.status || 'open') !== this.statusTab;
                if (status === 'closed' || status === 'pending') {
                    await this.celebrateStatusChange(originElement);
                }
                if (leavingTab) {
                    this.resetConversationState();
                    this.chatNavStack = [];
                    await this.loadConversations();
                } else {
                    await this.refreshConversationCounts();
                }
            } catch (error) {
                this.errorMessage = error.response?.data?.message || this.$t('words.chat-status-failed');
                throw error;
            } finally {
                this.updatingStatus = false;
            }
        },
        celebrateStatusChange(originElement = null) {
            const rect = originElement && typeof originElement.getBoundingClientRect === 'function'
                ? originElement.getBoundingClientRect()
                : null;

            const origin = rect
                ? {
                    x: (rect.left + rect.width / 2) / window.innerWidth,
                    y: (rect.top + rect.height / 2) / window.innerHeight,
                }
                : { x: 0.5, y: 0.65 };

            confetti({
                particleCount: 90,
                spread: 75,
                startVelocity: 38,
                gravity: 0.9,
                ticks: 180,
                origin,
                zIndex: 9999,
                disableForReducedMotion: true,
            });

            // Let the burst play out before the pane clears (~ticks/60fps + small beat).
            const settleMs = 1000;
            return new Promise((resolve) => setTimeout(resolve, settleMs));
        },
        subscribeEcho() {
            this.stopPolling();

            if (!window.Echo) {
                console.warn('[Chat] Echo unavailable — using slow polling fallback');
                this.startPollingFallback();
                return;
            }

            console.log('[Chat] Subscribing to channel whatsapp-chat');

            window.Echo.channel('whatsapp-chat')
                .listen('.WhatsAppMessageReceived', (event) => {
                    console.log('[Chat] WhatsAppMessageReceived', event && event.message);
                    const message = event.message;
                    const isForSelected = !!(
                        this.selectedConversation
                        && message
                        && this.normalizePhone(message.phone) === this.normalizePhone(this.selectedConversation.phone)
                    );

                    if (message && !this.isOutboundMessage(message) && !message.is_note) {
                        this.playNewMessageSound();
                        if (!isForSelected) {
                            this.markConversationUnreadByPhone(message.phone);
                        }
                    }
                    this.scheduleConversationsReload();
                    if (isForSelected) {
                        console.log('[Chat] Appending message to open conversation');
                        this.mergeIncomingMessage(message);
                        if (!this.isOutboundMessage(message) && !message.is_note) {
                            this.markSelectedConversationRead();
                        }
                        // One delayed catch-up for bot replies that race the first event.
                        if (!this.isOutboundMessage(message) || this.isBotMessage(message)) {
                            this.scheduleOpenConversationRefresh();
                        }
                    }
                })
                .listen('.WhatsAppConversationUpdated', (event) => {
                    console.log('[Chat] WhatsAppConversationUpdated', event && event.conversation);
                    if (event && event.conversation) {
                        this.patchConversation(event.conversation);
                        this.refreshConversationCounts();
                    } else {
                        this.scheduleConversationsReload();
                    }
                });
        },
        async initChatPwa() {
            this.isStandalonePwa = isChatPwaStandalone();
            this.pushSupported = typeof window !== 'undefined'
                && 'serviceWorker' in navigator
                && 'PushManager' in window
                && typeof Notification !== 'undefined';

            await registerChatServiceWorker();

            if (!this.pushSupported) {
                return;
            }

            try {
                const { data } = await axios.get(route('back.chat.push-vapid-public-key'));
                this.pushConfigured = !!(data && data.configured && data.public_key);
            } catch (error) {
                this.pushConfigured = false;
            }

            if (!this.pushConfigured) {
                this.pushSupported = false;
                return;
            }

            const existing = await getExistingPushSubscription();
            this.pushEnabled = !!existing;
        },
        onBeforeInstallPrompt(event) {
            event.preventDefault();
            this.deferredInstallPrompt = event;
        },
        async installChatPwa() {
            if (!this.deferredInstallPrompt) {
                return;
            }
            const promptEvent = this.deferredInstallPrompt;
            this.deferredInstallPrompt = null;
            await promptEvent.prompt();
        },
        async togglePushNotifications() {
            if (this.pushBusy || !this.pushSupported) {
                return;
            }
            this.pushBusy = true;
            this.pwaErrorMessage = '';
            try {
                if (this.pushEnabled) {
                    await unsubscribeChatPush({
                        destroyUrl: route('back.chat.push-subscriptions.destroy'),
                    });
                    this.pushEnabled = false;
                } else {
                    const { data } = await axios.get(route('back.chat.push-vapid-public-key'));
                    if (!data || !data.public_key) {
                        this.pwaErrorMessage = this.$t('words.chat-notifications-unavailable');
                        return;
                    }
                    await subscribeChatPush({
                        vapidPublicKey: data.public_key,
                        storeUrl: route('back.chat.push-subscriptions.store'),
                    });
                    this.pushEnabled = true;
                }
            } catch (error) {
                if (error && error.code === 'permission-denied') {
                    this.pwaErrorMessage = this.$t('words.chat-notifications-denied');
                } else {
                    this.pwaErrorMessage = this.$t('words.chat-notifications-failed');
                }
            } finally {
                this.pushBusy = false;
            }
        },
        unsubscribeEcho() {
            if (window.Echo) {
                console.log('[Chat] Leaving channel whatsapp-chat');
                window.Echo.leave('whatsapp-chat');
            }
        },
        scheduleConversationsReload() {
            if (this.conversationsReloadTimer) {
                clearTimeout(this.conversationsReloadTimer);
            }
            this.conversationsReloadTimer = setTimeout(() => {
                this.conversationsReloadTimer = null;
                this.loadConversations({ silent: true });
            }, 400);
        },
        normalizePhone(phone) {
            return String(phone || '').replace(/\D+/g, '');
        },
        markConversationUnreadByPhone(phone) {
            const normalized = this.normalizePhone(phone);
            if (!normalized) {
                return;
            }

            const index = this.conversations.findIndex(
                (conv) => this.normalizePhone(conv.phone) === normalized
            );
            if (index === -1) {
                return;
            }

            const conversation = this.conversations[index];
            if (conversation.has_unread) {
                return;
            }

            this.$set(this.conversations, index, {
                ...conversation,
                has_unread: true,
            });
        },
        mergeIncomingMessage(message) {
            if (!message) {
                return;
            }

            const messageKeys = this.messageIdentityKeys(message);
            const existingIndex = this.messages.findIndex((item) => {
                const itemKeys = this.messageIdentityKeys(item);
                return messageKeys.some((key) => itemKeys.includes(key));
            });

            if (existingIndex !== -1) {
                this.$set(this.messages, existingIndex, { ...this.messages[existingIndex], ...message });
                this.refreshMessagingWindowFromInbound(message);
                return;
            }

            this.messages.push(message);
            this.refreshMessagingWindowFromInbound(message);
            this.$nextTick(() => this.scrollToBottom());
        },
        messageIdentityKeys(message) {
            return [message.sid, message.id]
                .filter(Boolean)
                .map((value) => String(value));
        },
        async loadTags() {
            try {
                const { data } = await axios.get(route('back.chat.tags'));
                this.availableTags = data.tags || [];
            } catch (e) {
                this.availableTags = [];
            }
        },
        async loadConversations({ silent = false } = {}) {
            if (!silent) {
                this.loadingConversations = true;
            }
            try {
                const { data } = await axios.get(route('back.chat.conversations'), {
                    params: this.conversationParams(),
                });
                this.conversations = data.data || [];
                this.conversationPage = data.current_page || 1;
                this.totalPages = data.last_page || 1;
                this.totalConversations = data.total || 0;
                this.botConfigured = !!data.bot_configured;
                this.applyConversationCounts(data.counts);
                this.applyTagCounts(data.tag_counts);
            } catch (e) {
                if (!silent) {
                    this.conversations = [];
                    this.totalPages = 1;
                    this.totalConversations = 0;
                }
            } finally {
                if (!silent) {
                    this.loadingConversations = false;
                }
            }
        },
        applyConversationCounts(counts) {
            if (!counts || typeof counts !== 'object') {
                return;
            }
            this.conversationCounts = {
                open: Number(counts.open) || 0,
                pending: Number(counts.pending) || 0,
                closed: Number(counts.closed) || 0,
                unassigned: Number(counts.unassigned) || 0,
            };
        },
        applyTagCounts(tagCounts) {
            if (!tagCounts || typeof tagCounts !== 'object') {
                return;
            }
            this.availableTags = (this.availableTags || []).map((tag) => ({
                ...tag,
                conversation_count: Number(tagCounts[tag.id]) || 0,
            }));
        },
        async refreshConversationCounts() {
            try {
                const { data } = await axios.get(route('back.chat.conversations'), {
                    params: {
                        ...this.conversationParams(),
                        page: 1,
                    },
                });
                this.applyConversationCounts(data.counts);
                this.applyTagCounts(data.tag_counts);
            } catch (e) {
                // keep current counts
            }
        },
        async selectConversation(conv) {
            if (this.showTraineeDetailsMobile) {
                this.showTraineeDetailsMobile = false;
            }
            this.closeTraineeDocumentModal({ skipHistory: true });
            while (
                this.chatNavStack.length
                && this.chatNavStack[this.chatNavStack.length - 1] !== 'conversation'
            ) {
                this.chatNavStack.pop();
            }

            this.selectedConversation = {
                ...conv,
                has_unread: false,
            };
            this.errorMessage = '';
            this.successMessage = '';
            this.tagToAttach = '';
            this.botStatus = null;
            this.traineeContext = null;
            this.copiedInvoiceId = null;
            this.setComposerMode('freeform');
            this.markSelectedConversationRead();
            if (!this.chatNavStack.includes('conversation')) {
                this.pushChatLayer('conversation');
            }
            await Promise.all([
                this.loadMessages(),
                this.loadBotStatus(),
                this.loadTraineeContext(),
            ]);
            // Live updates come from Echo. Poll only if realtime is unavailable.
            if (!window.Echo) {
                this.startPollingFallback();
            } else {
                this.stopPolling();
            }
        },
        async markSelectedConversationRead() {
            if (!this.selectedConversation || !this.selectedConversation.id) {
                return;
            }

            if (this.selectedConversation.has_unread) {
                this.patchConversation({
                    ...this.selectedConversation,
                    has_unread: false,
                });
            } else {
                const listed = this.conversations.find((item) => item.id === this.selectedConversation.id);
                if (listed && listed.has_unread) {
                    this.patchConversation({
                        ...listed,
                        has_unread: false,
                    });
                }
            }

            try {
                const { data } = await axios.post(
                    route('back.chat.conversations.read', this.selectedConversation.id)
                );
                if (data.conversation) {
                    this.patchConversation(data.conversation);
                }
            } catch (error) {
                // Keep local clear; unread will resync on next conversation reload if needed.
            }
        },
        async loadTraineeContext() {
            if (!this.selectedConversation || !this.selectedConversation.trainee || !this.selectedConversation.trainee.id) {
                this.traineeContext = null;
                this.loadingTraineeContext = false;
                return;
            }

            this.loadingTraineeContext = true;
            try {
                const { data } = await axios.get(
                    route('back.chat.trainees.context', this.selectedConversation.trainee.id)
                );
                this.traineeContext = data;
            } catch (error) {
                this.traineeContext = null;
            } finally {
                this.loadingTraineeContext = false;
            }
        },
        async openTraineeDocument(doc) {
            if (!doc || !doc.available || !doc.document || !doc.document.url) {
                return;
            }

            this.revokeTraineeDocumentPreview();
            this.traineeDocumentModal = {
                ...doc.document,
                label: doc.label,
            };
            this.traineeDocumentLoading = true;
            this.traineeDocumentError = null;
            this.updateViewportWidth();
            this.$modal.show('traineeDocumentModal');
            this.pushChatLayer('document');

            try {
                const streamUrl = this.withStreamParam(doc.document.url);
                const response = await axios.get(streamUrl, {
                    responseType: 'blob',
                    validateStatus: (status) => status >= 200 && status < 300,
                });
                const headerType = String((response.headers && (response.headers['content-type'] || response.headers['Content-Type'])) || '');
                if (headerType.includes('application/json') || headerType.includes('text/html')) {
                    throw new Error('unexpected-content-type');
                }
                const mime = doc.document.mime_type || (response.data && response.data.type) || headerType || 'application/octet-stream';
                const blob = response.data instanceof Blob
                    ? response.data
                    : new Blob([response.data], { type: mime });
                if (!blob || blob.size < 1) {
                    throw new Error('empty-file');
                }
                const typedBlob = (blob.type && blob.type !== 'application/octet-stream')
                    ? blob
                    : new Blob([blob], { type: mime });
                this.traineeDocumentPreviewUrl = URL.createObjectURL(typedBlob);
            } catch (error) {
                this.traineeDocumentError = this.$t('words.chat-document-load-failed');
            } finally {
                this.traineeDocumentLoading = false;
            }
        },
        closeTraineeDocumentModal() {
            this.$modal.hide('traineeDocumentModal');
            this.revokeTraineeDocumentPreview();
            this.traineeDocumentModal = null;
            this.traineeDocumentLoading = false;
            this.traineeDocumentError = null;
        },
        downloadTraineeDocument() {
            if (!this.traineeDocumentPreviewUrl) {
                return;
            }
            const link = document.createElement('a');
            link.href = this.traineeDocumentPreviewUrl;
            link.download = this.traineeDocumentDownloadName;
            link.rel = 'noopener';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        },
        async shareOrDownloadTraineeDocument() {
            if (!this.traineeDocumentPreviewUrl) {
                return;
            }

            try {
                const response = await fetch(this.traineeDocumentPreviewUrl);
                const blob = await response.blob();
                const type = blob.type || (this.traineeDocumentModalIsPdf ? 'application/pdf' : 'application/octet-stream');
                const file = new File([blob], this.traineeDocumentDownloadName, { type });

                if (navigator.canShare && navigator.canShare({ files: [file] })) {
                    await navigator.share({
                        files: [file],
                        title: this.traineeDocumentModalTitle || this.traineeDocumentDownloadName,
                    });
                    return;
                }
            } catch (error) {
                // Fall through to download.
            }

            this.downloadTraineeDocument();
        },
        revokeTraineeDocumentPreview() {
            if (this.traineeDocumentPreviewUrl) {
                URL.revokeObjectURL(this.traineeDocumentPreviewUrl);
                this.traineeDocumentPreviewUrl = null;
            }
        },
        withStreamParam(url) {
            const raw = String(url || '');
            if (!raw) {
                return raw;
            }
            return raw + (raw.indexOf('?') === -1 ? '?' : '&') + 'stream=1';
        },
        updateViewportWidth() {
            if (typeof window !== 'undefined') {
                this.viewportWidth = window.innerWidth;
            }
        },
        formatAmount(amount) {
            const value = Number(amount) || 0;
            return value.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },
        async copyInvoiceLink(invoice) {
            if (!invoice || !invoice.show_url) {
                return;
            }

            try {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    await navigator.clipboard.writeText(invoice.show_url);
                } else {
                    const input = document.createElement('input');
                    input.value = invoice.show_url;
                    document.body.appendChild(input);
                    input.select();
                    document.execCommand('copy');
                    document.body.removeChild(input);
                }
                this.copiedInvoiceId = invoice.id;
                if (this.copyLinkTimer) {
                    clearTimeout(this.copyLinkTimer);
                }
                this.copyLinkTimer = setTimeout(() => {
                    this.copiedInvoiceId = null;
                    this.copyLinkTimer = null;
                }, 2000);
            } catch (e) {
                this.errorMessage = this.$t('words.could-not-copy-link');
            }
        },
        formatPausedUntil(iso) {
            if (!iso) {
                return '';
            }
            try {
                const date = new Date(iso);
                if (Number.isNaN(date.getTime())) {
                    return iso;
                }
                return this.$t('words.bot-paused-until') + ' ' + date.toLocaleString();
            } catch (e) {
                return iso;
            }
        },
        async loadBotStatus() {
            if (!this.selectedConversation || !this.selectedConversation.phone) {
                this.botStatus = null;
                return;
            }

            try {
                const { data } = await axios.get(route('back.chat.bot-status'), {
                    params: { phone: this.selectedConversation.phone },
                });
                this.botStatus = data;
                if (data && (data.workflow_assigned || data.ai_enabled)) {
                    this.botConfigured = true;
                }
                this.patchSelectedConversationBotState(!!(data && data.is_paused), data && data.paused_until);
            } catch (error) {
                this.botStatus = {
                    workflow_assigned: false,
                    workflow_name: null,
                    ai_enabled: false,
                    is_paused: false,
                    is_active: false,
                    paused_until: null,
                    pause_minutes: 720,
                    pause_hours: 12,
                    can_pause: false,
                    can_resume: false,
                };
            }
        },
        async pauseBot() {
            if (!this.selectedConversation || !this.selectedConversation.phone || this.pausingBot) {
                return;
            }

            this.pausingBot = true;
            this.errorMessage = '';
            this.successMessage = '';

            try {
                const { data } = await axios.post(route('back.chat.bot-pause'), {
                    phone: this.selectedConversation.phone,
                });
                this.botStatus = data.bot || null;
                this.successMessage = data.message || this.$t('words.whatsapp-bot-paused-hours', { hours: this.botStatus?.pause_hours || 12 });
                this.patchSelectedConversationBotState(true, data.bot && data.bot.paused_until);
            } catch (error) {
                this.errorMessage = (error.response && error.response.data && error.response.data.message)
                    || this.$t('words.whatsapp-bot-pause-failed');
            } finally {
                this.pausingBot = false;
            }
        },
        async resumeBot() {
            if (!this.selectedConversation || !this.selectedConversation.phone || this.pausingBot) {
                return;
            }

            this.pausingBot = true;
            this.errorMessage = '';
            this.successMessage = '';

            try {
                const { data } = await axios.post(route('back.chat.bot-resume'), {
                    phone: this.selectedConversation.phone,
                });
                this.botStatus = data.bot || null;
                this.successMessage = data.message || this.$t('words.whatsapp-bot-resumed');
                this.patchSelectedConversationBotState(false, null);
            } catch (error) {
                this.errorMessage = (error.response && error.response.data && error.response.data.message)
                    || this.$t('words.whatsapp-bot-resume-failed');
            } finally {
                this.pausingBot = false;
            }
        },
        async loadMessages() {
            if (!this.selectedConversation) return;
            this.loadingMessages = true;
            this.hasMoreMessages = false;
            this.nextBefore = null;
            this.nextBeforeId = null;
            try {
                const { data } = await axios.get(route('back.chat.messages'), {
                    params: {
                        phone: this.selectedConversation.phone,
                        limit: 5,
                    },
                });
                this.messages = data.messages || [];
                this.hasMoreMessages = !!data.has_more;
                this.nextBefore = data.next_before || null;
                this.nextBeforeId = data.next_before_id || null;
                this.$nextTick(() => this.scrollToBottom());
            } catch (e) {
                this.messages = [];
            } finally {
                this.loadingMessages = false;
            }
        },
        async loadOlderMessages() {
            if (!this.selectedConversation || !this.hasMoreMessages || this.loadingOlderMessages) {
                return;
            }
            this.loadingOlderMessages = true;
            const container = this.$refs.messagesContainer;
            const previousHeight = container ? container.scrollHeight : 0;
            try {
                const { data } = await axios.get(route('back.chat.messages'), {
                    params: {
                        phone: this.selectedConversation.phone,
                        limit: 20,
                        before: this.nextBefore,
                        before_id: this.nextBeforeId,
                    },
                });
                const older = data.messages || [];
                this.messages = [...older, ...this.messages];
                this.hasMoreMessages = !!data.has_more;
                this.nextBefore = data.next_before || null;
                this.nextBeforeId = data.next_before_id || null;
                this.$nextTick(() => {
                    if (container) {
                        container.scrollTop = container.scrollHeight - previousHeight;
                    }
                });
            } catch (e) {
                // keep current messages
            } finally {
                this.loadingOlderMessages = false;
            }
        },
        onMessagesScroll() {
            const container = this.$refs.messagesContainer;
            if (!container || !this.hasMoreMessages || this.loadingOlderMessages) {
                return;
            }
            if (container.scrollTop <= 40) {
                this.loadOlderMessages();
            }
        },
        async toggleAssignMe() {
            if (!this.selectedConversation || !this.selectedConversation.id) {
                return;
            }
            this.showAssignDropdown = false;
            this.assigningAgent = true;
            try {
                const routeName = this.selectedConversation.is_assigned_to_me
                    ? 'back.chat.conversations.agents.unassign'
                    : 'back.chat.conversations.agents.assign';
                const method = this.selectedConversation.is_assigned_to_me ? 'delete' : 'post';
                const { data } = await axios[method](route(routeName, this.selectedConversation.id));
                this.patchConversation(data.conversation);
                await this.refreshConversationCounts();
            } catch (error) {
                this.errorMessage = error.response?.data?.message || this.$t('words.chat-assign-failed');
            } finally {
                this.assigningAgent = false;
            }
        },
        async toggleAssignDropdown() {
            if (this.showAssignDropdown) {
                this.showAssignDropdown = false;
                return;
            }
            this.showAssignDropdown = true;
            if (!this.assignableAgentsLoaded) {
                await this.loadAssignableAgents();
            }
        },
        async loadAssignableAgents() {
            this.loadingAssignableAgents = true;
            try {
                const { data } = await axios.get(route('back.chat.assignable-agents'));
                this.assignableAgents = data.agents || [];
                this.assignableAgentsLoaded = true;
            } catch (error) {
                this.assignableAgents = [];
                this.errorMessage = error.response?.data?.message || this.$t('words.chat-assign-failed');
            } finally {
                this.loadingAssignableAgents = false;
            }
        },
        isAgentAssigned(userId) {
            const agents = (this.selectedConversation && this.selectedConversation.agents) || [];
            return agents.some((agent) => agent.id === userId);
        },
        async assignColleague(agent) {
            if (!this.selectedConversation || !this.selectedConversation.id || !agent || !agent.id) {
                return;
            }
            if (this.isAgentAssigned(agent.id)) {
                return;
            }
            this.assigningAgent = true;
            try {
                const { data } = await axios.post(
                    route('back.chat.conversations.agents.assign', this.selectedConversation.id),
                    { user_id: agent.id }
                );
                this.patchConversation(data.conversation);
                this.showAssignDropdown = false;
                await this.refreshConversationCounts();
            } catch (error) {
                this.errorMessage = error.response?.data?.message || this.$t('words.chat-assign-failed');
            } finally {
                this.assigningAgent = false;
            }
        },
        async attachSelectedTag() {
            if (!this.selectedConversation || !this.selectedConversation.id || !this.tagToAttach) {
                return;
            }
            this.attachingTag = true;
            try {
                const { data } = await axios.post(
                    route('back.chat.conversations.tags.attach', this.selectedConversation.id),
                    { tag_id: this.tagToAttach }
                );
                this.patchConversation(data.conversation);
                this.tagToAttach = '';
                if (data.tag && !this.availableTags.find((t) => t.id === data.tag.id)) {
                    this.availableTags.push({
                        ...data.tag,
                        conversation_count: data.tag.conversation_count || 0,
                    });
                }
                this.refreshConversationCounts();
            } catch (error) {
                this.errorMessage = error.response?.data?.message || this.$t('words.chat-tag-failed');
            } finally {
                this.attachingTag = false;
            }
        },
        async createAndAttachTag() {
            if (!this.selectedConversation || !this.selectedConversation.id) {
                return;
            }
            const name = window.prompt(this.$t('words.chat-new-tag-prompt'));
            if (!name || !name.trim()) {
                return;
            }
            this.attachingTag = true;
            try {
                const { data } = await axios.post(
                    route('back.chat.conversations.tags.attach', this.selectedConversation.id),
                    { name: name.trim() }
                );
                this.patchConversation(data.conversation);
                if (data.tag && !this.availableTags.find((t) => t.id === data.tag.id)) {
                    this.availableTags.push({
                        ...data.tag,
                        conversation_count: data.tag.conversation_count || 0,
                    });
                    this.availableTags.sort((a, b) => a.name.localeCompare(b.name));
                }
                this.refreshConversationCounts();
            } catch (error) {
                this.errorMessage = error.response?.data?.message || this.$t('words.chat-tag-failed');
            } finally {
                this.attachingTag = false;
            }
        },
        async detachTag(tag) {
            if (!this.selectedConversation || !this.selectedConversation.id || !tag) {
                return;
            }
            try {
                const { data } = await axios.delete(
                    route('back.chat.conversations.tags.detach', {
                        conversation: this.selectedConversation.id,
                        tag: tag.id,
                    })
                );
                this.patchConversation(data.conversation);
                this.refreshConversationCounts();
            } catch (error) {
                this.errorMessage = error.response?.data?.message || this.$t('words.chat-tag-failed');
            }
        },
        async loadTemplates() {
            this.loadingTemplates = true;
            try {
                const { data } = await axios.get(route('back.chat.templates'));
                this.templates = data.templates;
            } catch (e) {
                this.templates = [];
            } finally {
                this.loadingTemplates = false;
            }
        },
        onTemplatesManaged(templates) {
            this.templates = templates || [];
            if (this.selectedTemplateSid && !this.templates.some((t) => t.sid === this.selectedTemplateSid)) {
                this.selectedTemplateSid = '';
                this.selectedTemplate = null;
                this.templateVariables = {};
            }
            if (this.newChatTemplateSid && !this.templates.some((t) => t.sid === this.newChatTemplateSid)) {
                this.newChatTemplateSid = '';
                this.newChatTemplate = null;
                this.newChatTemplateVariables = {};
            }
        },
        async onTemplateChange() {
            this.templateVariables = {};
            this.selectedTemplate = null;
            if (!this.selectedTemplateSid) return;
            try {
                const { data } = await axios.get(route('back.chat.templates.show', this.selectedTemplateSid));
                this.selectedTemplate = data.template;
                this.applyTemplateVariableDefaults(this.selectedTemplate, this.templateVariables);
            } catch (e) {
                this.errorMessage = 'Failed to load template details.';
            }
        },
        selectComposerTemplate(sid) {
            if (!sid || sid === this.selectedTemplateSid) {
                return;
            }
            this.selectedTemplateSid = sid;
            this.onTemplateChange();
        },
        openNewChatModal() {
            this.resetNewChatRecipientState();
            this.newChatTemplateSid = '';
            this.newChatTemplate = null;
            this.newChatTemplateVariables = {};
            this.newChatError = '';
            this.newChatSuccess = '';
            this.newChatRecipientMode = 'search';
            if (this.configured && (!this.templates || !this.templates.length)) {
                this.loadTemplates();
            }
            this.$modal.show('newChatModal');
        },
        resetNewChatRecipientState() {
            this.newChatPhone = '';
            this.newChatSearch = '';
            this.newChatSearching = false;
            this.newChatTraineeResults = [];
            this.newChatTraineePage = 1;
            this.newChatTraineesHasMore = false;
            this.newChatLoadingMoreTrainees = false;
            this.newChatCompanyResults = [];
            this.newChatCompanyPage = 1;
            this.newChatCompaniesHasMore = false;
            this.newChatLoadingMoreCompanies = false;
            this.newChatSelectedCompany = null;
            this.newChatCompanyTrainees = [];
            this.newChatCompanyTraineePage = 1;
            this.newChatCompanyTraineesHasMore = false;
            this.newChatLoadingCompanyTrainees = false;
            this.newChatLoadingMoreCompanyTrainees = false;
            this.newChatCompanyTraineeSearch = '';
            this.newChatSelectedTrainee = null;
            this.newChatBulkCompanySearch = '';
            this.newChatBulkSearching = false;
            this.newChatBulkCompanyResults = [];
            this.newChatBulkCompany = null;
            this.newChatBulkActiveTrainees = [];
            this.newChatBulkActiveCount = 0;
            this.newChatBulkLoadingTrainees = false;
            this.newChatBulkPendingInvoiceMonth = '';
            this.newChatBulkPendingMonths = [];
            this.newChatBulkPendingMonthsError = '';
            this.newChatBulkLoadingPendingMonths = false;
            this.newChatSuccess = '';
            if (this.newChatSearchDebounce) {
                clearTimeout(this.newChatSearchDebounce);
                this.newChatSearchDebounce = null;
            }
            if (this.newChatCompanyTraineeSearchDebounce) {
                clearTimeout(this.newChatCompanyTraineeSearchDebounce);
                this.newChatCompanyTraineeSearchDebounce = null;
            }
            if (this.newChatBulkCompanySearchDebounce) {
                clearTimeout(this.newChatBulkCompanySearchDebounce);
                this.newChatBulkCompanySearchDebounce = null;
            }
        },
        setNewChatRecipientMode(mode) {
            this.newChatRecipientMode = mode;
            this.newChatError = '';
            this.newChatSuccess = '';
            if (mode === 'custom') {
                this.newChatSelectedTrainee = null;
                this.newChatSelectedCompany = null;
                this.newChatBulkCompany = null;
            } else if (mode === 'company') {
                this.newChatSelectedTrainee = null;
                this.newChatSelectedCompany = null;
                this.newChatPhone = '';
            } else if (!this.newChatSelectedTrainee) {
                this.newChatPhone = '';
                this.newChatBulkCompany = null;
            }
        },
        onNewChatSearchInput() {
            if (this.newChatSearchDebounce) {
                clearTimeout(this.newChatSearchDebounce);
            }
            this.newChatSearchDebounce = setTimeout(() => {
                this.runNewChatSearch(true);
            }, 300);
        },
        async runNewChatSearch(reset = true) {
            const q = String(this.newChatSearch || '').trim();
            if (q.length < 2) {
                this.newChatTraineeResults = [];
                this.newChatCompanyResults = [];
                this.newChatTraineesHasMore = false;
                this.newChatCompaniesHasMore = false;
                this.newChatSearching = false;
                return;
            }

            if (reset) {
                this.newChatTraineePage = 1;
                this.newChatCompanyPage = 1;
                this.newChatSearching = true;
            }

            try {
                const [traineesRes, companiesRes] = await Promise.all([
                    axios.get(route('back.chat.trainees'), {
                        params: { search: q, page: this.newChatTraineePage, limit: 10 },
                    }),
                    axios.get(route('back.chat.companies'), {
                        params: { search: q, page: this.newChatCompanyPage, limit: 10 },
                    }),
                ]);

                const trainees = traineesRes.data.trainees || [];
                const companies = companiesRes.data.companies || [];
                this.newChatTraineeResults = reset ? trainees : [...this.newChatTraineeResults, ...trainees];
                this.newChatCompanyResults = reset ? companies : [...this.newChatCompanyResults, ...companies];
                this.newChatTraineesHasMore = !!traineesRes.data.has_more;
                this.newChatCompaniesHasMore = !!companiesRes.data.has_more;
            } catch (e) {
                if (reset) {
                    this.newChatTraineeResults = [];
                    this.newChatCompanyResults = [];
                }
            } finally {
                this.newChatSearching = false;
                this.newChatLoadingMoreTrainees = false;
                this.newChatLoadingMoreCompanies = false;
            }
        },
        async loadMoreNewChatTrainees() {
            if (!this.newChatTraineesHasMore || this.newChatLoadingMoreTrainees) {
                return;
            }
            this.newChatLoadingMoreTrainees = true;
            this.newChatTraineePage += 1;
            try {
                const { data } = await axios.get(route('back.chat.trainees'), {
                    params: {
                        search: String(this.newChatSearch || '').trim(),
                        page: this.newChatTraineePage,
                        limit: 10,
                    },
                });
                this.newChatTraineeResults = [...this.newChatTraineeResults, ...(data.trainees || [])];
                this.newChatTraineesHasMore = !!data.has_more;
            } catch (e) {
                this.newChatTraineePage = Math.max(1, this.newChatTraineePage - 1);
            } finally {
                this.newChatLoadingMoreTrainees = false;
            }
        },
        async loadMoreNewChatCompanies() {
            if (!this.newChatCompaniesHasMore || this.newChatLoadingMoreCompanies) {
                return;
            }
            this.newChatLoadingMoreCompanies = true;
            this.newChatCompanyPage += 1;
            try {
                const { data } = await axios.get(route('back.chat.companies'), {
                    params: {
                        search: String(this.newChatSearch || '').trim(),
                        page: this.newChatCompanyPage,
                        limit: 10,
                    },
                });
                this.newChatCompanyResults = [...this.newChatCompanyResults, ...(data.companies || [])];
                this.newChatCompaniesHasMore = !!data.has_more;
            } catch (e) {
                this.newChatCompanyPage = Math.max(1, this.newChatCompanyPage - 1);
            } finally {
                this.newChatLoadingMoreCompanies = false;
            }
        },
        async selectNewChatCompany(company) {
            this.newChatSelectedCompany = company;
            this.newChatCompanyTraineeSearch = '';
            this.newChatCompanyTraineePage = 1;
            this.newChatCompanyTrainees = [];
            await this.loadNewChatCompanyTrainees(true);
        },
        clearNewChatCompany() {
            this.newChatSelectedCompany = null;
            this.newChatCompanyTrainees = [];
            this.newChatCompanyTraineePage = 1;
            this.newChatCompanyTraineesHasMore = false;
            this.newChatCompanyTraineeSearch = '';
        },
        onNewChatCompanyTraineeSearchInput() {
            if (this.newChatCompanyTraineeSearchDebounce) {
                clearTimeout(this.newChatCompanyTraineeSearchDebounce);
            }
            this.newChatCompanyTraineeSearchDebounce = setTimeout(() => {
                this.loadNewChatCompanyTrainees(true);
            }, 300);
        },
        async loadNewChatCompanyTrainees(reset = true) {
            if (!this.newChatSelectedCompany) {
                return;
            }
            if (reset) {
                this.newChatCompanyTraineePage = 1;
                this.newChatLoadingCompanyTrainees = true;
            }
            try {
                const { data } = await axios.get(
                    route('back.chat.companies.trainees', this.newChatSelectedCompany.id),
                    {
                        params: {
                            page: this.newChatCompanyTraineePage,
                            limit: 10,
                            search: String(this.newChatCompanyTraineeSearch || '').trim() || undefined,
                        },
                    }
                );
                const trainees = data.trainees || [];
                this.newChatCompanyTrainees = reset ? trainees : [...this.newChatCompanyTrainees, ...trainees];
                this.newChatCompanyTraineesHasMore = !!data.has_more;
            } catch (e) {
                if (reset) {
                    this.newChatCompanyTrainees = [];
                    this.newChatCompanyTraineesHasMore = false;
                }
            } finally {
                this.newChatLoadingCompanyTrainees = false;
                this.newChatLoadingMoreCompanyTrainees = false;
            }
        },
        async loadMoreNewChatCompanyTrainees() {
            if (!this.newChatCompanyTraineesHasMore || this.newChatLoadingMoreCompanyTrainees) {
                return;
            }
            this.newChatLoadingMoreCompanyTrainees = true;
            this.newChatCompanyTraineePage += 1;
            await this.loadNewChatCompanyTrainees(false);
        },
        selectNewChatTrainee(trainee) {
            this.newChatSelectedTrainee = trainee;
            this.newChatPhone = trainee.phone || '';
            this.newChatRecipientMode = 'search';
            if (this.newChatTemplate) {
                this.applyTemplateVariableDefaults(
                    this.newChatTemplate,
                    this.newChatTemplateVariables,
                    this.newChatSelectedTrainee
                );
            }
        },
        async onNewChatTemplateChange() {
            this.newChatTemplateVariables = {};
            this.newChatTemplate = null;
            if (!this.newChatTemplateSid) return;
            try {
                const { data } = await axios.get(route('back.chat.templates.show', this.newChatTemplateSid));
                this.newChatTemplate = data.template;
                this.applyTemplateVariableDefaults(
                    this.newChatTemplate,
                    this.newChatTemplateVariables,
                    this.newChatSelectedTrainee
                );
            } catch (e) {
                this.newChatError = 'Failed to load template details.';
            }
        },
        templateVariableLabel(variableKey, template = null) {
            const current = template || this.selectedTemplate;
            const bindings = (current && current.variable_bindings) || {};
            const tag = bindings[variableKey];
            if (tag) {
                return tag;
            }
            return this.$t('words.template-variable') + ' ' + variableKey;
        },
        currentTraineeContext() {
            return (this.selectedConversation && this.selectedConversation.trainee) || null;
        },
        traineeFirstName(name) {
            const full = String(name || '').trim();
            if (!full) {
                return '';
            }
            const space = full.indexOf(' ');
            return space === -1 ? full : full.slice(0, space).trim();
        },
        autoValueForTag(tag, trainee = null) {
            const person = trainee || this.currentTraineeContext();
            if (!person) {
                return '';
            }
            switch (tag) {
                case 'trainee_name':
                    return person.name || '';
                case 'trainee_first_name':
                    return this.traineeFirstName(person.name);
                case 'trainee_english_name':
                    return person.english_name || '';
                case 'trainee_phone':
                    return person.phone || '';
                case 'trainee_identity':
                    return person.identity_number || '';
                case 'company_name':
                    return person.company_name || '';
                default:
                    return '';
            }
        },
        applyTemplateVariableDefaults(template, targetObject, trainee = null) {
            const bindings = template.variable_bindings || {};
            const autoVariables = template.auto_variables || {};
            (template.variables || []).forEach((key) => {
                const tag = autoVariables[key] || bindings[key] || '';
                const autoValue = tag ? this.autoValueForTag(tag, trainee) : '';
                this.$set(targetObject, key, autoValue);
            });
        },
        previewVariableValues(template, manualValues) {
            const values = { ...(manualValues || {}) };
            const bindings = template.variable_bindings || {};
            const autoVariables = template.auto_variables || {};
            const traineeOverride = this.newChatSelectedTrainee || null;
            Object.keys(autoVariables).forEach((key) => {
                const tag = autoVariables[key];
                const autoValue = this.autoValueForTag(tag, traineeOverride);
                if (autoValue) {
                    values[key] = autoValue;
                    values[tag] = autoValue;
                }
            });
            Object.keys(bindings).forEach((key) => {
                const tag = bindings[key];
                if (values[key]) {
                    values[tag] = values[key];
                }
            });
            return values;
        },
        async sendNewChatTemplate() {
            if (!this.newChatPhone.trim() || !this.newChatTemplateSid) return;
            this.sendingNewChat = true;
            this.newChatError = '';
            this.newChatSuccess = '';

            try {
                await axios.post(route('back.chat.send-template'), {
                    phone: this.newChatPhone.trim(),
                    content_sid: this.newChatTemplateSid,
                    content_variables: this.newChatTemplateVariables,
                    trainee_id: this.newChatSelectedTrainee ? this.newChatSelectedTrainee.id : null,
                });

                this.$modal.hide('newChatModal');
                await this.reloadConversationsFromStart();

                const normalized = this.normalizePhone(this.newChatPhone.trim());
                let conv = this.conversations.find((c) => this.normalizePhone(c.phone) === normalized);
                if (conv) {
                    this.selectConversation(conv);
                }
            } catch (error) {
                this.newChatError = error.response?.data?.message || this.$t('words.whatsapp-template-send-failed');
            } finally {
                this.sendingNewChat = false;
            }
        },
        onNewChatBulkCompanySearchInput() {
            if (this.newChatBulkCompanySearchDebounce) {
                clearTimeout(this.newChatBulkCompanySearchDebounce);
            }
            this.newChatBulkCompanySearchDebounce = setTimeout(() => {
                this.runNewChatBulkCompanySearch();
            }, 300);
        },
        async runNewChatBulkCompanySearch() {
            const q = String(this.newChatBulkCompanySearch || '').trim();
            if (q.length < 2) {
                this.newChatBulkCompanyResults = [];
                this.newChatBulkSearching = false;
                return;
            }

            this.newChatBulkSearching = true;
            try {
                const { data } = await axios.get(route('back.chat.companies'), {
                    params: { search: q, page: 1, limit: 20 },
                });
                this.newChatBulkCompanyResults = data.companies || [];
            } catch (e) {
                this.newChatBulkCompanyResults = [];
            } finally {
                this.newChatBulkSearching = false;
            }
        },
        async selectNewChatBulkCompany(company) {
            this.newChatBulkCompany = company;
            this.newChatBulkPendingInvoiceMonth = '';
            this.newChatBulkPendingMonths = [];
            this.newChatBulkPendingMonthsError = '';
            this.newChatError = '';
            this.newChatSuccess = '';
            await Promise.all([
                this.loadNewChatBulkPendingMonths(),
                this.loadNewChatBulkActiveTrainees(),
            ]);
        },
        clearNewChatBulkCompany() {
            this.newChatBulkCompany = null;
            this.newChatBulkActiveTrainees = [];
            this.newChatBulkActiveCount = 0;
            this.newChatBulkPendingInvoiceMonth = '';
            this.newChatBulkPendingMonths = [];
            this.newChatBulkPendingMonthsError = '';
            this.newChatBulkLoadingPendingMonths = false;
            this.newChatError = '';
            this.newChatSuccess = '';
        },
        async loadNewChatBulkPendingMonths() {
            if (!this.newChatBulkCompany || !this.newChatBulkCompany.id) {
                this.newChatBulkPendingMonths = [];
                this.newChatBulkPendingMonthsError = '';
                return;
            }

            this.newChatBulkLoadingPendingMonths = true;
            this.newChatBulkPendingMonthsError = '';
            try {
                const { data } = await axios.get(
                    route('back.chat.companies.pending-invoice-months', this.newChatBulkCompany.id)
                );
                this.newChatBulkPendingMonths = data.months || [];
            } catch (error) {
                this.newChatBulkPendingMonths = [];
                this.newChatBulkPendingMonthsError = error.response?.data?.message
                    || this.$t('words.whatsapp-pending-invoice-months-load-failed');
            } finally {
                this.newChatBulkLoadingPendingMonths = false;
            }
        },
        async loadNewChatBulkActiveTrainees() {
            if (!this.newChatBulkCompany || !this.newChatBulkCompany.id) {
                this.newChatBulkActiveTrainees = [];
                this.newChatBulkActiveCount = 0;
                return;
            }

            this.newChatBulkLoadingTrainees = true;
            try {
                const params = {};
                if (this.newChatBulkPendingInvoiceMonth) {
                    params.pending_invoice_month = this.newChatBulkPendingInvoiceMonth;
                }

                const { data } = await axios.get(
                    route('back.chat.companies.active-trainees', this.newChatBulkCompany.id),
                    { params }
                );
                this.newChatBulkActiveTrainees = data.trainees || [];
                this.newChatBulkActiveCount = data.count || this.newChatBulkActiveTrainees.length;
                this.$set(this.newChatBulkCompany, 'active_trainees_count', this.newChatBulkActiveCount);
            } catch (error) {
                this.newChatBulkActiveTrainees = [];
                this.newChatBulkActiveCount = 0;
                this.newChatError = error.response?.data?.message || this.$t('words.whatsapp-send-failed');
            } finally {
                this.newChatBulkLoadingTrainees = false;
            }
        },
        async sendNewChatCompanyTemplate() {
            if (!this.newChatBulkCompany || !this.newChatTemplateSid || !this.newChatBulkActiveCount) {
                return;
            }

            const confirmed = window.confirm(
                this.$t('words.whatsapp-company-send-confirm', {
                    count: this.newChatBulkActiveCount,
                    company: this.newChatBulkCompany.name,
                })
            );
            if (!confirmed) {
                return;
            }

            this.sendingNewChat = true;
            this.newChatError = '';
            this.newChatSuccess = '';

            try {
                const payload = {
                    company_id: this.newChatBulkCompany.id,
                    content_sid: this.newChatTemplateSid,
                    content_variables: this.newChatTemplateVariables,
                };
                if (this.newChatBulkPendingInvoiceMonth) {
                    payload.pending_invoice_month = this.newChatBulkPendingInvoiceMonth;
                }

                const { data } = await axios.post(route('back.chat.send-template-to-company'), payload);

                this.newChatSuccess = data.message
                    || this.$t('words.whatsapp-company-template-sent', {
                        sent: data.sent,
                        total: data.total,
                    });

                if (data.failed_count > 0) {
                    const failedNames = (data.failed || []).slice(0, 5).map((row) => row.name).join(', ');
                    this.newChatError = this.$t('words.whatsapp-company-template-partial-fail', {
                        failed: data.failed_count,
                        names: failedNames,
                    });
                } else {
                    this.$modal.hide('newChatModal');
                    await this.reloadConversationsFromStart();
                }
            } catch (error) {
                this.newChatError = error.response?.data?.message || this.$t('words.whatsapp-send-failed');
            } finally {
                this.sendingNewChat = false;
            }
        },
        toggleNoteMode() {
            this.setComposerMode(this.composerMode === 'note' ? 'freeform' : 'note');
        },
        setComposerMode(mode) {
            this.composerMode = mode;
            this.sendMode = mode === 'template' ? 'template' : 'freeform';
            this.isNoteMode = mode === 'note';
            this.showEmojiPicker = false;
            this.errorMessage = '';
            this.successMessage = '';
        },
        openQuickRepliesModal() {
            this.quickReplySearch = '';
            this.showNewQuickReply = false;
            this.newQuickReplyTitle = '';
            this.newQuickReplyBody = '';
            this.loadQuickReplies();
            this.$modal.show('quickRepliesModal');
        },
        closeQuickRepliesModal() {
            this.$modal.hide('quickRepliesModal');
            this.showNewQuickReply = false;
        },
        initThreadHeight() {
            const isMobile = window.innerWidth < 768;
            const stored = Number(localStorage.getItem('chat.threadHeight'));
            const viewportCap = Math.floor(window.innerHeight * (isMobile ? 0.28 : 0.5));
            const fallback = isMobile
                ? Math.min(220, Math.max(160, viewportCap || 200))
                : Math.min(420, Math.max(200, viewportCap || 360));
            if (Number.isFinite(stored) && stored >= 160) {
                const mobileCap = Math.max(220, viewportCap + 40);
                const desktopCap = Math.max(420, viewportCap + 120);
                this.threadHeight = Math.min(stored, isMobile ? mobileCap : desktopCap);
            } else {
                this.threadHeight = fallback;
            }
        },
        startThreadResize(event) {
            this.threadResizing = true;
            this.threadResizeStartY = event.clientY;
            this.threadResizeStartHeight = this.threadHeight;
            document.addEventListener('mousemove', this.onThreadResize);
            document.addEventListener('mouseup', this.stopThreadResize);
        },
        onThreadResize(event) {
            if (!this.threadResizing) {
                return;
            }
            const delta = event.clientY - this.threadResizeStartY;
            const maxHeight = Math.max(280, window.innerHeight - 280);
            this.threadHeight = Math.min(maxHeight, Math.max(200, this.threadResizeStartHeight + delta));
        },
        stopThreadResize() {
            if (!this.threadResizing) {
                document.removeEventListener('mousemove', this.onThreadResize);
                document.removeEventListener('mouseup', this.stopThreadResize);
                return;
            }
            this.threadResizing = false;
            document.removeEventListener('mousemove', this.onThreadResize);
            document.removeEventListener('mouseup', this.stopThreadResize);
            try {
                localStorage.setItem('chat.threadHeight', String(this.threadHeight));
            } catch (e) {}
        },
        handleGlobalClick() {
            this.showEmojiPicker = false;
            this.showAssignDropdown = false;
        },
        handleGlobalKeydown(event) {
            if (event.key === 'Escape') {
                this.showEmojiPicker = false;
                this.showAssignDropdown = false;
            }
        },
        positionEmojiPicker() {
            const button = this.$refs.emojiButton;
            if (!button || !button.getBoundingClientRect) {
                return;
            }
            const rect = button.getBoundingClientRect();
            const panelWidth = 224;
            const panelHeight = 160;
            const gap = 6;
            let left = rect.left;
            let top = rect.bottom + gap;

            if (left + panelWidth > window.innerWidth - 8) {
                left = Math.max(8, rect.right - panelWidth);
            }
            if (left < 8) {
                left = 8;
            }
            if (top + panelHeight > window.innerHeight - 8) {
                top = Math.max(8, rect.top - panelHeight - gap);
            }

            this.emojiPickerStyle = {
                top: `${Math.round(top)}px`,
                left: `${Math.round(left)}px`,
                zIndex: 9999,
            };
        },
        toggleEmojiPicker() {
            if (this.showEmojiPicker) {
                this.showEmojiPicker = false;
                return;
            }
            this.positionEmojiPicker();
            this.showEmojiPicker = true;
            this.$nextTick(() => this.positionEmojiPicker());
        },
        insertEmoji(emoji) {
            const textarea = this.$refs.messageTextarea;
            const value = this.messageBody || '';
            if (textarea && typeof textarea.selectionStart === 'number') {
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                this.messageBody = value.slice(0, start) + emoji + value.slice(end);
                this.$nextTick(() => {
                    const pos = start + emoji.length;
                    textarea.focus();
                    textarea.setSelectionRange(pos, pos);
                });
            } else {
                this.messageBody = value + emoji;
            }
            this.showEmojiPicker = false;
        },
        async loadQuickReplies() {
            this.loadingQuickReplies = true;
            try {
                const { data } = await axios.get(route('back.chat.quick-replies'));
                this.quickReplies = data.quick_replies || [];
            } catch (e) {
                this.quickReplies = [];
            } finally {
                this.loadingQuickReplies = false;
            }
        },
        async saveQuickReply() {
            if (!this.newQuickReplyTitle.trim() || !this.newQuickReplyBody.trim() || this.savingQuickReply) {
                return;
            }
            this.savingQuickReply = true;
            try {
                const { data } = await axios.post(route('back.chat.quick-replies.store'), {
                    title: this.newQuickReplyTitle.trim(),
                    body: this.newQuickReplyBody.trim(),
                });
                if (data.quick_reply) {
                    this.quickReplies = [data.quick_reply, ...this.quickReplies];
                }
                this.newQuickReplyTitle = '';
                this.newQuickReplyBody = '';
                this.showNewQuickReply = false;
            } catch (error) {
                this.errorMessage = (error.response && error.response.data && error.response.data.message)
                    || this.$t('words.could-not-load-trainee-details');
            } finally {
                this.savingQuickReply = false;
            }
        },
        async deleteQuickReply(reply) {
            if (!reply || !reply.id) {
                return;
            }
            if (!window.confirm(this.$t('words.delete') + '?')) {
                return;
            }
            this.deletingQuickReplyId = reply.id;
            try {
                await axios.delete(route('back.chat.quick-replies.destroy', reply.id));
                this.quickReplies = this.quickReplies.filter((item) => item.id !== reply.id);
            } catch (error) {
                this.errorMessage = (error.response && error.response.data && error.response.data.message)
                    || this.$t('words.could-not-load-trainee-details');
            } finally {
                this.deletingQuickReplyId = null;
            }
        },
        useQuickReply(reply) {
            if (!reply) {
                return;
            }
            this.messageBody = reply.body || '';
            if (this.composerMode === 'template') {
                this.setComposerMode('freeform');
            } else if (this.composerMode !== 'note' && this.composerMode !== 'freeform') {
                this.setComposerMode('freeform');
            }
            this.closeQuickRepliesModal();
            this.$nextTick(() => {
                if (this.$refs.messageTextarea) {
                    this.$refs.messageTextarea.focus();
                }
            });
        },
        loadPressEnterToSendPreference() {
            try {
                this.pressEnterToSend = window.localStorage.getItem('chat.pressEnterToSend') === '1';
            } catch (error) {
                this.pressEnterToSend = false;
            }
        },
        persistPressEnterToSend() {
            try {
                window.localStorage.setItem('chat.pressEnterToSend', this.pressEnterToSend ? '1' : '0');
            } catch (error) {
                // Ignore storage failures (private mode, quota, etc).
            }
        },
        loadGroupConversationsPreference() {
            try {
                const storedMode = window.localStorage.getItem('chat.conversationGroupMode');
                if (storedMode === 'latest' || storedMode === 'company' || storedMode === 'agent') {
                    this.conversationGroupMode = storedMode;
                    return;
                }

                const legacyStored = window.localStorage.getItem('chat.groupConversationsByCompany');
                if (legacyStored === null) {
                    this.conversationGroupMode = 'latest';
                    return;
                }

                this.conversationGroupMode = legacyStored === '1' ? 'company' : 'latest';
            } catch (error) {
                this.conversationGroupMode = 'latest';
            }
        },
        setConversationGroupMode(mode) {
            const allowed = ['latest', 'company', 'agent'];
            const previousMode = this.conversationGroupMode;
            const previousActive = this.companyFilterIsActive;
            this.conversationGroupMode = allowed.includes(mode) ? mode : 'latest';
            try {
                window.localStorage.setItem('chat.conversationGroupMode', this.conversationGroupMode);
            } catch (error) {
                // Ignore storage failures (private mode, quota, etc).
            }
            if (previousActive !== this.companyFilterIsActive || previousMode !== this.conversationGroupMode) {
                // Company filter is server-side; reload when membership of the filtered set may change.
                if (previousActive || this.companyFilterIsActive) {
                    this.reloadConversationsFromStart();
                }
            }
        },
        formatCompanyFilterCount(count) {
            const locale = (this.$page && this.$page.props && this.$page.props.locale === 'ar')
                ? 'ar-EG'
                : undefined;
            return Number(count || 0).toLocaleString(locale);
        },
        async loadAgentCompanyFilters() {
            try {
                const { data } = await axios.get('/back/chat/company-filters');
                this.agentCompanyFilters = data.companies || [];
            } catch (error) {
                this.agentCompanyFilters = [];
            }
        },
        openCompanyFilterModal() {
            this.companyFilterDraft = (this.agentCompanyFilters || []).map((company) => ({
                id: company.id,
                name: company.name,
            }));
            this.companyFilterSearch = '';
            this.companyFilterResults = [];
            this.companyFilterSearching = false;
            this.$modal.show('companyFilterModal');
        },
        closeCompanyFilterModal() {
            this.$modal.hide('companyFilterModal');
            this.companyFilterSearch = '';
            this.companyFilterResults = [];
            this.companyFilterSearching = false;
        },
        onCompanyFilterSearchInput() {
            if (this.companyFilterSearchDebounce) {
                clearTimeout(this.companyFilterSearchDebounce);
            }
            this.companyFilterSearchDebounce = setTimeout(() => {
                this.runCompanyFilterSearch();
            }, 300);
        },
        async runCompanyFilterSearch() {
            const q = String(this.companyFilterSearch || '').trim();
            if (q.length < 2) {
                this.companyFilterResults = [];
                this.companyFilterSearching = false;
                return;
            }

            this.companyFilterSearching = true;
            try {
                const { data } = await axios.get(route('back.chat.companies'), {
                    params: { search: q, page: 1, limit: 20 },
                });
                this.companyFilterResults = data.companies || [];
            } catch (error) {
                this.companyFilterResults = [];
            } finally {
                this.companyFilterSearching = false;
            }
        },
        isCompanyInFilterDraft(companyId) {
            return (this.companyFilterDraft || []).some((company) => String(company.id) === String(companyId));
        },
        addCompanyToFilterDraft(company) {
            if (!company || !company.id || this.isCompanyInFilterDraft(company.id)) {
                return;
            }
            this.companyFilterDraft.push({
                id: company.id,
                name: company.name || company.name_ar || company.name_en || company.id,
            });
        },
        removeCompanyFromFilterDraft(companyId) {
            this.companyFilterDraft = (this.companyFilterDraft || []).filter(
                (company) => String(company.id) !== String(companyId)
            );
        },
        async confirmCompanyFilterModal() {
            if (this.savingCompanyFilters) {
                return;
            }
            this.savingCompanyFilters = true;
            this.errorMessage = '';
            try {
                const { data } = await axios.put('/back/chat/company-filters', {
                    company_ids: (this.companyFilterDraft || []).map((company) => company.id),
                });
                this.agentCompanyFilters = data.companies || [];
                this.companyFilterPaused = false;
                this.closeCompanyFilterModal();
                this.reloadConversationsFromStart();
            } catch (error) {
                this.errorMessage = (error.response && error.response.data && error.response.data.message)
                    || (error.message || this.$t('words.error'));
            } finally {
                this.savingCompanyFilters = false;
            }
        },
        async clearCompanyFilters() {
            if (this.savingCompanyFilters) {
                return;
            }
            this.savingCompanyFilters = true;
            this.errorMessage = '';
            try {
                await axios.delete('/back/chat/company-filters');
                this.agentCompanyFilters = [];
                this.companyFilterPaused = false;
                this.reloadConversationsFromStart();
            } catch (error) {
                this.errorMessage = (error.response && error.response.data && error.response.data.message)
                    || (error.message || this.$t('words.error'));
            } finally {
                this.savingCompanyFilters = false;
            }
        },
        showAllCompanies() {
            if (this.companyFilterPaused) {
                return;
            }
            this.companyFilterPaused = true;
            this.reloadConversationsFromStart();
        },
        onMessageKeydown(event) {
            if (!this.pressEnterToSend) {
                return;
            }
            if (event.key !== 'Enter' || event.shiftKey || event.ctrlKey || event.altKey || event.metaKey) {
                return;
            }
            if (event.isComposing) {
                return;
            }
            event.preventDefault();
            if (this.sending || !this.messageBody.trim()) {
                return;
            }
            if (this.composerMode === 'freeform' && this.messagingWindowIsOpen === false) {
                return;
            }
            this.sendMessageOrNote();
        },
        async sendMessageOrNote() {
            if (!this.selectedConversation || !this.messageBody.trim()) return;
            this.sending = true;
            this.errorMessage = '';
            this.successMessage = '';

            const isNote = this.composerMode === 'note';

            try {
                let endpoint = route('back.chat.send-message');
                let payload = {
                    phone: this.selectedConversation.phone,
                    body: this.messageBody.trim(),
                    trainee_id: this.selectedConversation.trainee?.id || null,
                };

                if (isNote) {
                    endpoint = route('back.chat.send-note');
                }

                const { data } = await axios.post(endpoint, payload);
                this.mergeIncomingMessage(data.message);
                if (data.conversation) {
                    this.patchConversation(data.conversation);
                    await this.refreshConversationCounts();
                }
                this.messageBody = '';
                this.successMessage = isNote
                    ? this.$t('words.whatsapp-note-added')
                    : this.$t('words.whatsapp-sent-successfully');
                this.$nextTick(() => this.scrollToBottom());
                this.loadConversations();
                if (!isNote) {
                    await this.loadBotStatus();
                }
            } catch (error) {
                this.errorMessage = error.response?.data?.message || this.$t('words.whatsapp-send-failed');
            } finally {
                this.sending = false;
            }
        },
        async sendTemplate() {
            if (!this.selectedConversation || !this.selectedTemplateSid) return;
            this.sending = true;
            this.errorMessage = '';
            this.successMessage = '';

            try {
                const { data } = await axios.post(route('back.chat.send-template'), {
                    phone: this.selectedConversation.phone,
                    content_sid: this.selectedTemplateSid,
                    content_variables: this.templateVariables,
                    trainee_id: this.selectedConversation.trainee?.id || null,
                });
                this.mergeIncomingMessage(data.message);
                if (data.conversation) {
                    this.patchConversation(data.conversation);
                    await this.refreshConversationCounts();
                }
                this.successMessage = this.$t('words.whatsapp-sent-successfully');
                this.$nextTick(() => this.scrollToBottom());
                this.loadConversations();
                await this.loadBotStatus();
            } catch (error) {
                this.errorMessage = error.response?.data?.message || this.$t('words.whatsapp-template-send-failed');
            } finally {
                this.sending = false;
            }
        },
        startPollingFallback() {
            this.stopPolling();
            // Slow fallback only when Echo/Pusher is down — avoid lock storms with many agents.
            this.pollInterval = setInterval(() => {
                this.loadConversations();
                if (this.selectedConversation) {
                    this.loadMessagesSilently();
                }
            }, 20000);
        },
        stopPolling() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
            }
        },
        scheduleOpenConversationRefresh() {
            if (this.messagesRefreshTimer) {
                clearTimeout(this.messagesRefreshTimer);
            }
            this.messagesRefreshTimer = setTimeout(() => {
                this.messagesRefreshTimer = null;
                if (this.selectedConversation) {
                    this.loadMessagesSilently(true);
                    this.loadBotStatus();
                }
            }, 1200);
        },
        async loadMessagesSilently(force = false) {
            if (!this.selectedConversation) return;
            try {
                const { data } = await axios.get(route('back.chat.messages'), {
                    params: {
                        phone: this.selectedConversation.phone,
                        limit: Math.max(5, this.messages.length || 5),
                    },
                });
                const incoming = data.messages || [];
                if (force || incoming.length !== this.messages.length) {
                    this.messages = incoming;
                    this.hasMoreMessages = !!data.has_more;
                    this.nextBefore = data.next_before || null;
                    this.nextBeforeId = data.next_before_id || null;
                    this.$nextTick(() => this.scrollToBottom());
                    return;
                }

                const lastIncoming = incoming.length ? incoming[incoming.length - 1] : null;
                const lastCurrent = this.messages.length ? this.messages[this.messages.length - 1] : null;
                const incomingKey = lastIncoming ? String(lastIncoming.id || lastIncoming.sid || '') : '';
                const currentKey = lastCurrent ? String(lastCurrent.id || lastCurrent.sid || '') : '';
                if (incomingKey && incomingKey !== currentKey) {
                    this.messages = incoming;
                    this.hasMoreMessages = !!data.has_more;
                    this.nextBefore = data.next_before || null;
                    this.nextBeforeId = data.next_before_id || null;
                    this.$nextTick(() => this.scrollToBottom());
                }
            } catch (e) {}
        },
        isOutboundMessage(message) {
            return ['outbound-api', 'outbound-reply', 'outbound'].includes(message.direction);
        },
        playNewMessageSound() {
            try {
                if (!this._newMessageAudio) {
                    this._newMessageAudio = new Audio('/notification_sound_whatsapp.mp3');
                    this._newMessageAudio.preload = 'auto';
                }
                this._newMessageAudio.currentTime = 0;
                const playPromise = this._newMessageAudio.play();
                if (playPromise && typeof playPromise.catch === 'function') {
                    playPromise.catch(() => {
                        // Browsers may block autoplay until the user interacts with the page.
                    });
                }
            } catch (e) {
                // Ignore audio failures so chat updates still work.
            }
        },
        isBotMessage(message) {
            if (!message) {
                return false;
            }
            if (message.is_bot) {
                return true;
            }
            if (message.author && message.author.is_bot) {
                return true;
            }
            return !!(message.metadata && message.metadata.is_bot);
        },
        messageAttachments(message) {
            if (!message) {
                return [];
            }

            if (message.saved_media && message.saved_media.length) {
                return message.saved_media.map((media) => ({
                    id: media.id,
                    url: media.url,
                    name: media.name,
                    content_type: media.content_type || this.guessMediaType(media.name || media.url),
                    kind: this.guessAttachmentKind(media.name || media.url, media.content_type),
                }));
            }

            const raw = (message.metadata && message.metadata.media) || [];
            return raw
                .filter((media) => media && (media.url || media.kind === 'sticker' || media.id))
                .map((media, index) => ({
                    id: media.id || `meta-${index}`,
                    url: media.url || null,
                    name: media.name || null,
                    content_type: media.content_type || media.mime_type || this.guessMediaType(media.url),
                    kind: media.kind || this.guessAttachmentKind(media.url, media.content_type || media.mime_type),
                    animated: !!media.animated,
                }));
        },
        messageBodyVisible(message) {
            if (!message || !message.body) {
                return false;
            }

            return !['[Media Attachment]', '[Sticker]'].includes(String(message.body).trim());
        },
        guessAttachmentKind(value, contentType) {
            if (String(contentType || '').toLowerCase().includes('sticker')) {
                return 'sticker';
            }
            const source = String(value || '').toLowerCase();
            if (source.includes('sticker')) {
                return 'sticker';
            }
            return null;
        },
        guessMediaType(value) {
            const source = String(value || '').toLowerCase();
            if (/\.(jpe?g|png|gif|webp|bmp|svg)(\?|$)/.test(source)) {
                return 'image/*';
            }
            if (/\.(mp4|webm|mov|m4v)(\?|$)/.test(source)) {
                return 'video/*';
            }
            if (/\.(mp3|ogg|wav|m4a|aac)(\?|$)/.test(source)) {
                return 'audio/*';
            }
            return '';
        },
        isStickerAttachment(media) {
            if (!media) {
                return false;
            }
            if (media.kind === 'sticker') {
                return true;
            }
            const type = String(media.content_type || '').toLowerCase();
            return type.includes('sticker');
        },
        isImageAttachment(media) {
            if (this.isStickerAttachment(media)) {
                return false;
            }
            const type = String((media && media.content_type) || '').toLowerCase();
            return type.startsWith('image/') || this.guessMediaType(media && (media.url || media.name)).startsWith('image/');
        },
        isVideoAttachment(media) {
            const type = String((media && media.content_type) || '').toLowerCase();
            return type.startsWith('video/') || this.guessMediaType(media && (media.url || media.name)).startsWith('video/');
        },
        isAudioAttachment(media) {
            const type = String((media && media.content_type) || '').toLowerCase();
            return type.startsWith('audio/') || this.guessMediaType(media && (media.url || media.name)).startsWith('audio/');
        },
        isRtl() {
            return document.documentElement.dir === 'rtl' || (this.$page && this.$page.props && this.$page.props.locale === 'ar');
        },
        messageAlignmentClass(message) {
            if (message.is_note) {
                return 'justify-center';
            }
            const isOutbound = this.isOutboundMessage(message);
            const isRTL = this.isRtl();
            if (isOutbound) {
                return isRTL ? 'justify-start' : 'justify-end';
            } else {
                return isRTL ? 'justify-end' : 'justify-start';
            }
        },
        scrollToBottom() {
            const container = this.$refs.messagesContainer;
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        },
        formatMessageTime(dateString) {
            if (!dateString) return '';
            return new Date(dateString).toLocaleString();
        },
        formatTimeShort(dateString) {
            if (!dateString) return '';
            // Keep labels fresh while the messaging-window ticker runs.
            void this.windowNowMs;
            const locale = (this.$page && this.$page.props && this.$page.props.locale === 'ar')
                ? 'ar'
                : 'en';
            return moment(dateString).locale(locale).fromNow();
        },
        isConversationMessagingWindowLocked(conv) {
            void this.windowNowMs;
            if (!conv) {
                return true;
            }

            const window = conv.messaging_window;
            if (!window) {
                return true;
            }

            let expiresMs = null;
            if (window.expires_at) {
                const parsed = Date.parse(window.expires_at);
                if (!Number.isNaN(parsed)) {
                    expiresMs = parsed;
                }
            }
            if (expiresMs === null && window.last_inbound_at) {
                const lastMs = Date.parse(window.last_inbound_at);
                if (!Number.isNaN(lastMs)) {
                    expiresMs = lastMs + (24 * 60 * 60 * 1000);
                }
            }
            if (expiresMs === null) {
                return true;
            }

            return expiresMs <= this.windowNowMs;
        },
        isConversationBotPaused(conv) {
            void this.windowNowMs;
            if (!conv) {
                return false;
            }

            if (conv.bot_paused_until) {
                const untilMs = Date.parse(conv.bot_paused_until);
                if (!Number.isNaN(untilMs)) {
                    return untilMs > this.windowNowMs;
                }
            }

            return !!conv.bot_is_paused;
        },
        patchSelectedConversationBotState(isPaused, pausedUntil) {
            if (!this.selectedConversation) {
                return;
            }

            const patch = {
                bot_is_paused: !!isPaused,
                bot_paused_until: isPaused ? (pausedUntil || null) : null,
            };

            this.selectedConversation = { ...this.selectedConversation, ...patch };

            const index = this.conversations.findIndex((c) => (
                c.id === this.selectedConversation.id || c.phone === this.selectedConversation.phone
            ));
            if (index !== -1) {
                this.$set(this.conversations, index, { ...this.conversations[index], ...patch });
            }
        },
        translateStatus(status) {
            if (!status) return '';
            const key = status.toLowerCase().trim();
            if (key === 'delivered') return this.$t('words.delivered');
            if (key === 'queued') return this.$t('words.queued');
            if (key === 'received') return this.$t('words.received');
            if (key === 'sent') return this.$t('words.sent');
            return status;
        },
    },
};
</script>

<style scoped>
.overflow-y-auto::-webkit-scrollbar {
    width: 6px;
}
.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}
.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
.trainee-sidebar-accent {
    border-left: 2px solid #16a34a;
}
[dir="rtl"] .trainee-sidebar-accent {
    border-left: none;
    border-right: 2px solid #16a34a;
}
.trainee-details-drawer {
    width: 85vw;
    max-width: 24rem;
    right: 0;
    left: auto;
}
[dir="rtl"] .trainee-details-drawer {
    right: auto;
    left: 0;
}
.conversation-list-scroll {
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* IE/Edge */
}
.conversation-list-scroll::-webkit-scrollbar {
    display: none; /* Chrome/Safari */
    width: 0;
    height: 0;
}
.conv-sidebar-enter-active {
    transition: opacity 0.35s ease;
}
.conv-sidebar-leave-active {
    transition: opacity 0.2s ease;
    position: absolute;
    width: 100%;
    pointer-events: none;
}
.conv-sidebar-enter,
.conv-sidebar-leave-to {
    opacity: 0;
}
.conv-sidebar-move {
    transition: transform 0.3s ease;
}
</style>
