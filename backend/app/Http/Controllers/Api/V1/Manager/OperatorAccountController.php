<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Enum\Role;
use App\Enum\StageStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\ProjectStage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class OperatorAccountController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return JsonResource::collection(User::query()->operators()->orderBy('name')->get()->map(fn (User $operator): array => [
            'id' => $operator->id,
            'name' => $operator->name,
            'email' => $operator->email,
            'phone' => $operator->phone,
            'is_active' => $operator->is_active,
        ]));
    }

    public function store(Request $request): UserResource
    {
        $this->prepare($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['required', 'regex:/^628\d{8,11}$/'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        unset($data['password_confirmation']);
        $operator = new User($data);
        $operator->role = Role::Operator;
        $operator->is_active = true;
        $operator->save();

        return new UserResource($operator);
    }

    public function update(Request $request, User $operator): UserResource
    {
        abort_unless($operator->isOperator(), 404);
        $this->prepare($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($operator->id)],
            'phone' => ['required', 'regex:/^628\d{8,11}$/'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'is_active' => ['required', 'boolean'],
        ]);
        if (! $data['is_active'] && ProjectStage::query()->where('operator_id', $operator->id)
            ->whereIn('status', [StageStatus::Ready->value, StageStatus::InProgress->value])->exists()) {
            throw ValidationException::withMessages(['is_active' => 'Operator masih menangani tahap aktif. Selesaikan tugasnya sebelum menonaktifkan akun.']);
        }
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $operator->fill($data);
        $operator->is_active = (bool) $data['is_active'];
        $operator->save();

        return new UserResource($operator->refresh());
    }

    private function prepare(Request $request): void
    {
        $digits = preg_replace('/\D+/', '', (string) $request->input('phone'));
        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }
        $request->merge([
            'name' => trim((string) $request->input('name')),
            'email' => Str::lower(trim((string) $request->input('email'))),
            'phone' => $digits,
        ]);
    }
}
