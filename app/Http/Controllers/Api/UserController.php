<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with(['role', 'gender'])
            ->where('is_deleted', false)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'users' => $users
        ], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'role_id' => ['required', 'exists:roles,id'],
            'employee_id' => ['required', 'unique:tbl_users,employee_id'],
            'first_name' => ['required', 'string', 'max:55'],
            'middle_name' => ['nullable', 'string', 'max:55'],
            'last_name' => ['required', 'string', 'max:55'],
            'suffix_name' => ['nullable', 'string', 'max:55'],
            'birth_date' => ['required', 'date'],
            'gender_id' => ['required', 'exists:tbl_genders,gender_id'],
            'address' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'max:55'],
            'phone' => ['nullable', 'string', 'max:55'],
            'email' => ['required', 'email', 'unique:tbl_users,email'],
            'password' => ['required', 'min:8', 'confirmed'],
            'hire_date' => ['nullable', 'date'],
            'salary' => ['nullable', 'numeric', 'min:0'],
        ]);

        $age = now()->diffInYears($validated['birth_date']);

        $user = User::create([
            'role_id' => $validated['role_id'],
            'employee_id' => $validated['employee_id'],
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'],
            'last_name' => $validated['last_name'],
            'suffix_name' => $validated['suffix_name'],
            'age' => $age,
            'birth_date' => $validated['birth_date'],
            'gender_id' => $validated['gender_id'],
            'address' => $validated['address'],
            'contact_number' => $validated['contact_number'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'hire_date' => $validated['hire_date'],
            'salary' => $validated['salary'],
            'is_active' => true,
        ]);

        // Send notification to Make.com
        $this->sendUserCreatedNotification($user);

        return response()->json([
            'message' => 'User created successfully.',
            'user' => $user->load(['role', 'gender'])
        ], 201);
    }

    public function show(User $user)
    {
        return response()->json([
            'user' => $user->load(['role', 'gender'])
        ], 200);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'role_id' => ['required', 'exists:roles,id'],
            'employee_id' => ['required', Rule::unique('tbl_users', 'employee_id')->ignore($user, 'user_id')],
            'first_name' => ['required', 'string', 'max:55'],
            'middle_name' => ['nullable', 'string', 'max:55'],
            'last_name' => ['required', 'string', 'max:55'],
            'suffix_name' => ['nullable', 'string', 'max:55'],
            'birth_date' => ['required', 'date'],
            'gender_id' => ['required', 'exists:tbl_genders,gender_id'],
            'address' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'max:55'],
            'phone' => ['nullable', 'string', 'max:55'],
            'email' => ['required', 'email', Rule::unique('tbl_users', 'email')->ignore($user, 'user_id')],
            'hire_date' => ['nullable', 'date'],
            'salary' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $age = now()->diffInYears($validated['birth_date']);
        $validated['age'] = $age;

        $user->update($validated);

        return response()->json([
            'message' => 'User updated successfully.',
            'user' => $user->load(['role', 'gender'])
        ], 200);
    }

    public function destroy(User $user)
    {
        $user->update(['is_deleted' => true, 'is_active' => false]);

        return response()->json([
            'message' => 'User deleted successfully.'
        ], 200);
    }

    public function getRoles()
    {
        $roles = Role::all();
        return response()->json(['roles' => $roles], 200);
    }

    private function sendUserCreatedNotification($user)
    {
        $webhookUrl = config('app.make_webhook_url');
        
        if ($webhookUrl) {
            try {
                $data = [
                    'event' => 'user_created',
                    'user' => [
                        'id' => $user->user_id,
                        'name' => $user->full_name,
                        'email' => $user->email,
                        'role' => $user->role->display_name,
                        'employee_id' => $user->employee_id,
                        'created_at' => $user->created_at->toISOString(),
                    ]
                ];

                $ch = curl_init($webhookUrl);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_exec($ch);
                curl_close($ch);
            } catch (\Exception $e) {
                \Log::error('Failed to send user created notification: ' . $e->getMessage());
            }
        }
    }
}