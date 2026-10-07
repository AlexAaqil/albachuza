<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3'; // ← remove Form
import FormHeader from '@/components/custom/FormHeader.vue';
import PhoneInput from '@/components/custom/PhoneInput.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import userRoutes from '@/routes/users';

interface Props {
    user: {
        data: {
            id: number;
            uuid: string;
            name: string;
            email: string;
            phone: string | null;
            role: number;
            status: number;
            is_active: boolean;
            role_label: string;
        };
    };
    role_options: Record<string, string>;
    status_options: Record<string, string>;
}

const props = defineProps<Props>();
const userData = props.user.data;

const statusOptions = computed(() =>
    Object.entries(props.status_options).map(([value, label]) => ({
        value: Number(value),
        label,
    })),
);

const roleOptions = computed(() => {
    const entries = Object.entries(props.role_options).map(
        ([value, label]) => ({
            value: Number(value),
            label,
        }),
    );
    if (!entries.some((o) => o.value === userData.role)) {
        entries.push({ value: userData.role, label: userData.role_label });
    }
    return entries;
});

const selectedRole = computed({
    get: () => String(form.role),
    set: (val: string) => {
        form.role = Number(val);
    },
});

const selectedStatus = computed({
    get: () => String(form.status),
    set: (val: string) => {
        form.status = Number(val);
    },
});

const form = useForm({
    name: userData.name,
    email: userData.email,
    phone: userData.phone || '',
    phone_country: 'ke',
    role: userData.role,
    status: userData.status,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.put(userRoutes.update(userData.uuid).url, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Edit User" />

    <div class="form edit-user">
        <FormHeader :backUrl="userRoutes.index().url" title="Edit user" />

        <form @submit.prevent="submit">
            <!-- ← closing must be </form> -->
            <div class="section-title">Basic Information</div>

            <div class="inputs-group-wrapper">
                <div class="inputs-group">
                    <Label for="name" class="required">Name</Label>
                    <Input
                        id="name"
                        type="text"
                        autofocus
                        autocomplete="name"
                        v-model="form.name"
                        placeholder="Full name"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="inputs-group">
                    <Label for="email" class="required">Email Address</Label>
                    <Input
                        id="email"
                        type="email"
                        autocomplete="email"
                        v-model="form.email"
                        placeholder="Email address"
                    />
                    <InputError :message="form.errors.email" />
                </div>
            </div>

            <div class="inputs-group-wrapper">
                <div class="inputs-group">
                    <Label for="phone">Phone Number</Label>
                    <PhoneInput
                        id="phone"
                        name="phone"
                        v-model="form.phone"
                        placeholder="Enter phone number"
                    />
                    <InputError :message="form.errors.phone" />
                </div>
            </div>

            <div class="inputs-group-wrapper">
                <div class="inputs-group">
                    <Label for="role" class="required">User Role</Label>
                    <Select v-model="selectedRole">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Select user role" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem
                                    v-for="option in roleOptions"
                                    :key="option.value"
                                    :value="String(option.value)"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.role" />
                </div>

                <div class="inputs-group">
                    <Label for="status" class="required">Account Status</Label>
                    <Select v-model="selectedStatus">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Select account status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem
                                    v-for="option in statusOptions"
                                    :key="option.value"
                                    :value="String(option.value)"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.status" />
                </div>
            </div>

            <div class="inputs-group-wrapper">
                <div class="inputs-group">
                    <Label for="password"
                        >Password (leave blank to keep current)</Label
                    >
                    <PasswordInput
                        id="password"
                        autocomplete="new-password"
                        v-model="form.password"
                        placeholder="New password"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="inputs-group">
                    <Label for="password_confirmation">Confirm Password</Label>
                    <PasswordInput
                        id="password_confirmation"
                        autocomplete="new-password"
                        v-model="form.password_confirmation"
                        placeholder="Confirm new password"
                    />
                    <InputError :message="form.errors.password_confirmation" />
                </div>
            </div>

            <div class="submit-buttons">
                <Button type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    Update User
                </Button>

                <Link :href="userRoutes.index().url">
                    <Button type="button" variant="outline">
                        Cancel and return to users
                    </Button>
                </Link>
            </div>
        </form>
        <!-- ← correct closing tag -->
    </div>
</template>
