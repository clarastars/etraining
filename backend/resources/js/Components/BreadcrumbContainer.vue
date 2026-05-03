<!--
  - Copyright (c) 2020 - Clarastars, LLC  - All Rights Reserved.
  -
  - Unauthorized copying of this file via any medium is strictly prohibited.
  - This file is a proprietary of Clarastars LLC and is confidential / educational purpose only.
  -
  - https://clarastars.com - info@clarastars.com
  - @author Shafiq al-Shaar <shafiqalshaar@gmail.com>
  -->

<template>
    <nav class="text-black font-bold my-8" aria-label="Breadcrumb">
        <ol class="list-none p-0 inline-flex">
            <span v-for="(crumb, index) in crumbs" :key="index">
                <li class="flex items-center" v-if="index+1 != crumbs.length">
                    <inertia-link v-if="crumb.link" :href="crumb.link">
                        <span v-if="crumb.title_raw">{{ crumb.title_raw }}</span>
                        <span v-else>{{ $t('words.'+crumb.title) }}</span>
                    </inertia-link>
                    <a v-else href="#">
                        <span v-if="crumb.title_raw">{{ crumb.title_raw }}</span>
                        <span v-else>{{ $t('words.'+crumb.title) }}</span>
                    </a>
                    <svg class="rotate-x fill-current w-3 h-3 mx-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512"><path d="M285.476 272.971L91.132 467.314c-9.373 9.373-24.569 9.373-33.941 0l-22.667-22.667c-9.357-9.357-9.375-24.522-.04-33.901L188.505 256 34.484 101.255c-9.335-9.379-9.317-24.544.04-33.901l22.667-22.667c9.373-9.373 24.569-9.373 33.941 0L285.475 239.03c9.373 9.372 9.373 24.568.001 33.941z"/></svg>
                </li>
                <li v-else>
                    <a href="#" class="text-gray-500" aria-current="page">
                        <span v-if="crumb.title_raw">{{ crumb.title_raw }}</span>
                        <span v-else>{{ $t('words.'+crumb.title) }}</span>
                    </a>
                </li>
            </span>
        </ol>
    </nav>
</template>

<script>
    export default {
        props: ['crumbs'],
    }
</script>
