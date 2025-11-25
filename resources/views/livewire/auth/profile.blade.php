<?php

use Livewire\Volt\Component;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Mary\Traits\Toast;
use Livewire\WithFileUploads;
use Livewire\Attributes\Rule;

new class extends Component {
    use Toast, WithFileUploads;

    #[Rule('required|string|max:255')]
    public string $name = '';

    #[Rule('required|email')]
    public string $email = '';

    #[Rule('nullable|integer|exists:roles,id')]
    public ?int $role_id = null;

    #[Rule('required_with:new_password|string')]
    public string $current_password = '';

    #[Rule('nullable|min:8|same:new_password_confirmation')]
    public string $new_password = '';

    #[Rule('nullable')]
    public string $new_password_confirmation = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->fill($user->only('name', 'email', 'role_id'));
    }

    public function with(): array
    {
        return [
            'roles' => Role::all(),
        ];
    }

    public function updateProfile(): void
    {
        $user = Auth::user();

        $validated = $this->validate();

        $user->update($validated);

        $this->success('Profil berhasil diperbarui.');
    }

    public function changePassword(): void
    {
        $this->validate();

        $user = Auth::user();

        if (!Hash::check($this->current_password, $user->password)) {
            $this->addError('current_password', 'Password saat ini salah.');
            return;
        }

        $user->update([
            'password' => Hash::make($this->new_password),
        ]);

        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);

        $this->success('Password berhasil diubah.');
    }
};
?>

<div>
    <x-header title="Edit Profil & Password" separator />

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Kolom 1 - Edit Profil --}}
        <x-card>
            <x-form wire:submit="updateProfile" card>
                <x-slot name="title">Edit Profil</x-slot>
                <x-input label="Nama" wire:model.defer="name" />
                <x-input label="Email" wire:model.defer="email" />
                <x-select label="Peran" wire:model.defer="role_id" :options="$roles" option-label="name"
                    option-value="id" placeholder="Pilih Peran" />

                <x-slot name="actions">
                    <x-button label="Simpan Profil" spinner="updateProfile" class="btn-primary" type="submit" />
                </x-slot>
            </x-form>
        </x-card>

        {{-- Kolom 2 - Ganti Password --}}
        <x-card>
            <x-form wire:submit="changePassword">
                <x-slot name="title">Ubah Password</x-slot>

                <x-password label="Password Saat Ini" type="password" wire:model.defer="current_password" right />
                <x-password label="Password Baru" type="password" wire:model.defer="new_password" right />
                <x-password label="Konfirmasi Password Baru" type="password"
                    wire:model.defer="new_password_confirmation" right />

                <x-slot name="actions">
                    <x-button label="Ubah Password" spinner="changePassword" type="submit" class="btn-primary" />
                </x-slot>
            </x-form>
        </x-card>
    </div>
</div>
