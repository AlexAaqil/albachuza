<script setup lang="ts">
import IntlTelInput from '@intl-tel-input/vue';
import 'intl-tel-input/styles';

const modelValue = defineModel<string>({ default: '' });

const props = defineProps<{
    id?: string;
    name?: string;
    placeholder?: string;
    required?: boolean;
    autofocus?: boolean;
}>();
</script>

<template>
    <IntlTelInput
        v-model="modelValue"
        initial-country="ke"
        :load-utils="() => import('intl-tel-input/utils')"
        :input-props="{
            id: props.id,
            name: props.name,
            placeholder: props.placeholder,
            required: props.required,
            autofocus: props.autofocus,
            class: '...'
        }"
    />
</template>