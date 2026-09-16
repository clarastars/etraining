<template>
    <app-layout>
        <div class="container px-6 mx-auto grid pt-6 pb-6">
            <breadcrumb-container
                :crumbs="[
                    {title: 'dashboard', link: route('dashboard')},
                    {title: 'caller'},
                ]"
            ></breadcrumb-container>

            <div class="mt-4">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-900">
                            {{ $t('words.caller') }}
                        </h1>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ $t('words.caller-popup-help') }}
                        </p>
                    </div>

                    <div v-if="configured" class="flex flex-wrap gap-2">
                        <jet-button type="button" @click="connectDialer" :disabled="connecting">
                            {{ connecting ? $t('words.caller-connecting') : $t('words.caller-open-in-window') }}
                        </jet-button>
                    </div>
                </div>

                <div
                    v-if="!configured"
                    class="mt-6 rounded-md border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800"
                >
                    {{ $t('words.caller-maqsam-not-configured') }}
                    <inertia-link
                        :href="route('back.settings.maqsam-system.index')"
                        class="ml-1 font-medium underline"
                    >
                        {{ $t('words.maqsam-system') }}
                    </inertia-link>
                </div>

                <template v-else>
                    <div
                        v-if="errorMessage"
                        class="mt-6 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-700"
                    >
                        {{ errorMessage }}
                    </div>

                    <div
                        v-if="successMessage"
                        class="mt-6 rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-700"
                    >
                        {{ successMessage }}
                    </div>

                    <div class="mt-6 grid gap-6 lg:grid-cols-3">
                        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm lg:col-span-1">
                            <div class="space-y-4">
                                <div>
                                    <jet-label for="agent_email" :value="$t('words.caller-agent-email')" />
                                    <jet-input
                                        id="agent_email"
                                        type="email"
                                        dir="ltr"
                                        class="mt-1 block w-full"
                                        v-model="agentEmail"
                                        autocomplete="off"
                                    />
                                    <p class="mt-1 text-sm text-gray-500">
                                        {{ $t('words.caller-agent-email-help') }}
                                    </p>
                                </div>

                                <div>
                                    <jet-label for="phone" :value="$t('words.caller-phone-number')" />
                                    <jet-input
                                        id="phone"
                                        type="tel"
                                        dir="ltr"
                                        class="mt-1 block w-full"
                                        v-model="phone"
                                        autocomplete="off"
                                        @keyup.enter="dial"
                                    />
                                </div>

                                <jet-button
                                    type="button"
                                    class="w-full justify-center"
                                    :class="{ 'opacity-25': dialing }"
                                    :disabled="dialing || !phone"
                                    @click="dial"
                                >
                                    {{ $t('words.caller-call') }}
                                </jet-button>
                            </div>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-white shadow-sm lg:col-span-2">
                            <div class="border-b border-gray-200 px-4 py-3">
                                <h2 class="text-sm font-medium text-gray-900">
                                    {{ $t('words.caller-dialer') }}
                                </h2>
                            </div>

                            <div class="min-h-[320px] bg-gray-50 flex flex-col items-center justify-center text-center p-8 space-y-3">
                                <p class="text-sm text-gray-600 max-w-md">
                                    {{ $t('words.caller-popup-help') }}
                                </p>
                                <jet-button type="button" @click="connectDialer" :disabled="connecting">
                                    {{ connecting
                                        ? $t('words.caller-connecting')
                                        : $t('words.caller-open-in-window') }}
                                </jet-button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout'
import JetInput from '@/Jetstream/Input'
import JetLabel from '@/Jetstream/Label'
import JetButton from '@/Jetstream/Button'
import BreadcrumbContainer from '@/Components/BreadcrumbContainer'
import {
    isNativeMaqsamDialerAvailable,
    nativeDialerFocus,
    nativeDialerIsOpen,
    nativeDialerOpen,
} from '@/maqsamNativeDialer'

let dialerPopup = null
let nativeDialerConnected = false

function liveDialerPopup() {
    try {
        if (dialerPopup && !dialerPopup.closed) {
            return dialerPopup
        }
    } catch (error) {
        // ignore
    }

    return null
}

export default {
    props: {
        configured: {
            type: Boolean,
            default: false,
        },
        agent_email: {
            type: String,
            default: '',
        },
    },

    components: {
        AppLayout,
        JetInput,
        JetLabel,
        JetButton,
        BreadcrumbContainer,
    },

    data() {
        return {
            agentEmail: this.agent_email || '',
            phone: '',
            connecting: false,
            dialing: false,
            errorMessage: '',
            successMessage: '',
            useNativeDialer: isNativeMaqsamDialerAvailable(),
        }
    },

    methods: {
        isPopupAlreadyOnMaqsam(popup) {
            if (!popup) {
                return false
            }

            try {
                if (popup.closed) {
                    return false
                }

                const href = popup.location.href || ''

                return href !== '' && href !== 'about:blank'
            } catch (error) {
                return true
            }
        },
        async connectDialerNative() {
            this.connecting = true
            this.errorMessage = ''
            this.successMessage = ''

            try {
                if (await nativeDialerIsOpen()) {
                    await nativeDialerFocus()
                    nativeDialerConnected = true
                    return true
                }

                const response = await axios.post(route('caller.connect'), {
                    email: this.agentEmail,
                })

                await nativeDialerOpen(response.data.url)
                nativeDialerConnected = true
                return true
            } catch (error) {
                nativeDialerConnected = false
                this.errorMessage = error.response?.data?.message
                    || error.message
                    || this.$t('words.caller-maqsam-login-failed')
                return false
            } finally {
                this.connecting = false
            }
        },
        async connectDialerPopup() {
            const existing = liveDialerPopup() || window.open('', 'maqsam-dialer', 'toolbar=no,menubar=no,width=420,height=720')

            if (!existing) {
                this.errorMessage = this.$t('words.caller-popup-blocked')
                return false
            }

            dialerPopup = existing

            try {
                existing.focus()
            } catch (error) {
                // ignore
            }

            if (this.isPopupAlreadyOnMaqsam(existing)) {
                this.errorMessage = ''
                return true
            }

            this.connecting = true
            this.errorMessage = ''
            this.successMessage = ''

            try {
                const response = await axios.post(route('caller.connect'), {
                    email: this.agentEmail,
                })

                try {
                    if (existing.closed) {
                        this.errorMessage = this.$t('words.caller-popup-blocked')
                        return false
                    }

                    existing.location.href = response.data.url
                } catch (error) {
                    this.errorMessage = this.$t('words.caller-popup-blocked')
                    return false
                }

                return true
            } catch (error) {
                this.errorMessage = error.response?.data?.message || this.$t('words.caller-maqsam-login-failed')
                return false
            } finally {
                this.connecting = false
            }
        },
        async connectDialer() {
            if (this.useNativeDialer) {
                return this.connectDialerNative()
            }

            return this.connectDialerPopup()
        },

        async dial() {
            if (!this.phone) {
                return
            }

            this.dialing = true
            this.errorMessage = ''
            this.successMessage = ''

            try {
                const connected = await this.connectDialer()
                if (!connected) {
                    return
                }

                const response = await axios.post(route('caller.dial'), {
                    phone: this.phone,
                    email: this.agentEmail,
                })

                this.successMessage = response.data.message
                this.phone = ''
            } catch (error) {
                this.errorMessage = error.response?.data?.message || this.$t('words.caller-dial-failed')
            } finally {
                this.dialing = false
            }
        },
    },
}
</script>
