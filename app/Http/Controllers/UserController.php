<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    private array $roles = [
        'admin' => 'Administrador',
        'agent' => 'Agente',
        'viewer' => 'Solo visualización',
    ];

    public function index()
    {
        $users = User::orderBy('name')->paginate(15);
        return view('users.index', compact('users'));
    }

    public function create()
    {
        $roles = $this->roles;
        return view('users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'  => ['required','string','max:255'],
            'email' => ['required','email','max:255','unique:users,email'],
            'role'  => ['required', Rule::in(array_keys($this->roles))],
            'password' => ['nullable','string','min:8'],
        ]);

        $password = $data['password'] ?? Str::password(12);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'password' => Hash::make($password),
        ]);

        return redirect()->route('users.index')->with('success','Usuario creado.');
    }

    public function edit(User $user)
    {
        $roles = $this->roles;
        return view('users.edit', compact('user','roles'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'  => ['required','string','max:255'],
            'email' => ['required','email','max:255', Rule::unique('users','email')->ignore($user->id)],
            'role'  => ['required', Rule::in(array_keys($this->roles))],
            'password' => ['nullable','string','min:8'],
        ]);

        if ($user->role === 'admin' && $data['role'] !== 'admin') {
            $adminCount = User::where('role','admin')->count();
            if ($adminCount <= 1) {
                return back()->withErrors(['role' => 'No puedes degradar al último Administrador.']);
            }
        }

        $user->fill(collect($data)->except('password')->toArray());
        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        return redirect()->route('users.index')->with('success','Usuario actualizado.');
    }

    public function destroy(User $user)
    {
        if (auth()->id() === $user->id) {
            return back()->withErrors(['delete' => 'No puedes eliminar tu propia cuenta.']);
        }
        if ($user->role === 'admin' && User::where('role','admin')->count() <= 1) {
            return back()->withErrors(['delete' => 'No puedes eliminar al último Administrador.']);
        }

        $user->delete();
        return redirect()->route('users.index')->with('success','Usuario eliminado.');
    }
}
