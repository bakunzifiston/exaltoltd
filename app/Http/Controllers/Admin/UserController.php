<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Employee;
use App\Models\User;
use App\Support\TablePageSize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = (string) $request->string('search', '');
        $allowedSorts = ['name', 'email', 'role', 'last_login_at', 'created_at', 'updated_at'];
        $sort = in_array($request->query('sort'), $allowedSorts, true) ? $request->query('sort') : 'name';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        $users = User::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->orderBy($sort, $direction)
            ->paginate(TablePageSize::resolve($request, 15))
            ->withQueryString();

        return view('admin.users.index', compact('users', 'search', 'sort', 'direction'));
    }

    public function create(): View
    {
        return view('admin.users.create', $this->formData());
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::create($request->validated());

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'User created successfully.');
    }

    public function show(User $user): View
    {
        $user->load('employee');

        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', array_merge($this->formData(), compact('user')));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        // Only change the password when a new one was actually entered.
        if (empty($data['password'])) {
            unset($data['password']);
        }

        // Don't let the last active owner lock everyone out of user management.
        if ($user->isOwner() && $data['role'] !== User::ROLE_OWNER && $this->isLastActiveOwner($user)) {
            return back()
                ->withErrors(['role' => 'You cannot change the role of the last active owner.'])
                ->withInput();
        }

        $user->update($data);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === request()->user()->id) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }

        if ($user->isOwner() && $this->isLastActiveOwner($user)) {
            return back()->withErrors(['user' => 'You cannot delete the last active owner.']);
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'User deleted successfully.');
    }

    private function isLastActiveOwner(User $user): bool
    {
        return User::where('role', User::ROLE_OWNER)
            ->where('is_active', true)
            ->where('id', '!=', $user->id)
            ->doesntExist();
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'roles' => User::ROLES,
            'employees' => Employee::orderBy('name')->get(['id', 'name', 'position']),
        ];
    }
}
