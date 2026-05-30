<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Destination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('destination')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($u) {
                $u->roles = $u->getRoleNames();
                return $u;
            });
        return response()->json(['data' => $users]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|unique:users',
            'password'       => 'required|min:8',
            'role'           => 'nullable|in:user,admin,super_admin',
            'destination_id' => 'nullable|exists:destinations,id',
        ]);

        $user = User::create([
            'name'           => $request->name,
            'email'          => $request->email,
            'password'       => Hash::make($request->password),
            'destination_id' => $request->destination_id,
        ]);

        $user->assignRole($request->input('role', 'user'));
        $user->roles = $user->getRoleNames();
        $user->load('destination');

        return response()->json(['data' => $user], 201);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name'           => 'sometimes|string|max:255',
            'email'          => 'sometimes|email|unique:users,email,' . $id,
            'destination_id' => 'nullable|exists:destinations,id',
        ]);

        $data = $request->only(['name', 'email', 'destination_id']);
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);
        $user->roles = $user->getRoleNames();
        $user->load('destination');

        return response()->json(['data' => $user]);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        if ($user->hasRole('super_admin')) {
            return response()->json(['message' => 'Tidak bisa menghapus super admin.'], 403);
        }
        $user->delete();
        return response()->json(['message' => 'Pengguna berhasil dihapus']);
    }

    public function assignRole(Request $request, $id)
    {
        $request->validate([
            'role'           => 'required|in:user,admin,super_admin',
            'destination_id' => 'nullable|exists:destinations,id',
        ]);

        $user = User::findOrFail($id);
        $user->syncRoles([$request->role]);

        // Update destination_id jika role = admin
        if ($request->role === 'admin') {
            $user->update(['destination_id' => $request->destination_id]);
        } else {
            $user->update(['destination_id' => null]);
        }

        $user->load('destination');

        return response()->json([
            'message'     => 'Role berhasil diupdate',
            'roles'       => $user->getRoleNames(),
            'destination' => $user->destination,
        ]);
    }

    // GET /api/v1/super-admin/destinations-list — untuk dropdown assign
    public function destinationsList()
    {
        $destinations = Destination::select('id', 'nama_wisata')->orderBy('nama_wisata')->get();
        return response()->json(['data' => $destinations]);
    }
}
