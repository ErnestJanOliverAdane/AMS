<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Shared CRUD for one kind of account.
 * A subclass says which role it manages and which route name prefix it uses,
 * so a controller can only ever read or change accounts of its own role.
 */
abstract class AccountController extends Controller
{
    /** The only role this controller can list, create, edit or delete. */
    abstract protected function role(): Role;

    /** Route name prefix, for example "manage.users". */
    abstract protected function routeBase(): string;

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $accounts = User::withRole($this->role())
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('accounts.index', $this->viewData(['accounts' => $accounts, 'search' => $search]));
    }

    public function create(): View
    {
        return view('accounts.create', $this->viewData(['account' => null]));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $this->role(),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route($this->routeBase().'.index')
            ->with('status', $this->singular().' created.');
    }

    public function edit(User $account): View
    {
        $this->ensureManaged($account);

        return view('accounts.edit', $this->viewData(['account' => $account]));
    }

    public function update(Request $request, User $account): RedirectResponse
    {
        $this->ensureManaged($account);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($account->id)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        $account->name = $data['name'];
        $account->email = $data['email'];
        $account->is_active = $request->boolean('is_active');

        if (! empty($data['password'])) {
            $account->password = $data['password'];
        }

        $account->save();

        return redirect()->route($this->routeBase().'.index')
            ->with('status', $this->singular().' updated.');
    }

    public function destroy(Request $request, User $account): RedirectResponse
    {
        $this->ensureManaged($account);

        if ($account->is($request->user())) {
            return back()->withErrors(['account' => 'You cannot delete your own account.']);
        }

        $account->delete();

        return redirect()->route($this->routeBase().'.index')
            ->with('status', $this->singular().' deleted.');
    }

    /** An account of any other role looks like it does not exist here. */
    private function ensureManaged(User $account): void
    {
        abort_unless($account->role === $this->role(), 404);
    }

    private function singular(): string
    {
        return $this->role()->label();
    }

    private function viewData(array $extra = []): array
    {
        return array_merge([
            'title' => $this->role()->label().'s',
            'singular' => Str::lower($this->role()->label()),
            'routeBase' => $this->routeBase(),
        ], $extra);
    }
}
