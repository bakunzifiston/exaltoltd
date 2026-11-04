<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEmployeeRequest;
use App\Http\Requests\Admin\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Support\RwandaLocations;
use App\Support\TablePageSize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $search = (string) $request->string('search', '');
        $allowedSorts = ['id', 'name', 'position', 'phone', 'email', 'province', 'district', 'created_at', 'updated_at'];
        $sort = in_array($request->query('sort'), $allowedSorts, true) ? $request->query('sort') : 'name';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        $employees = Employee::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('id', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%')
                        ->orWhere('position', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->orderBy($sort, $direction)
            ->paginate(TablePageSize::resolve($request, 15))
            ->withQueryString();

        return view('admin.employees.index', compact('employees', 'search', 'sort', 'direction'));
    }

    public function create(): View
    {
        $provinces = RwandaLocations::provinces();
        $districtMap = RwandaLocations::districtsByProvince();

        return view('admin.employees.create', compact('provinces', 'districtMap'));
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('employee-photos', 'public');
        }

        Employee::create($data);

        return redirect()
            ->route('admin.employees.index')
            ->with('status', 'Employee created successfully.');
    }

    public function show(Employee $employee): View
    {
        return view('admin.employees.show', compact('employee'));
    }

    public function edit(Employee $employee): View
    {
        $provinces = RwandaLocations::provinces();
        $districtMap = RwandaLocations::districtsByProvince();

        return view('admin.employees.edit', compact('employee', 'provinces', 'districtMap'));
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('photo')) {
            if ($employee->photo) {
                Storage::disk('public')->delete($employee->photo);
            }
            $data['photo'] = $request->file('photo')->store('employee-photos', 'public');
        }

        $employee->update($data);

        return redirect()
            ->route('admin.employees.show', $employee)
            ->with('status', 'Employee updated successfully.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        if ($employee->photo) {
            Storage::disk('public')->delete($employee->photo);
        }

        $employee->delete();

        return redirect()
            ->route('admin.employees.index')
            ->with('status', 'Employee deleted successfully.');
    }
}
