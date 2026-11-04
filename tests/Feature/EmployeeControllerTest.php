<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);
    }

    public function test_employee_can_be_created_without_a_separate_employee_id_column(): void
    {
        $response = $this->actingAs($this->owner)->post(route('admin.employees.store'), [
            'employee_id' => '2',
            'name' => 'Janon Dukuzumuremyi',
            'position' => 'Finance Manager',
            'phone' => '78999999',
            'email' => 'janon3030@gmail.com',
            'province' => 'Kigali City',
            'district' => 'Gasabo',
        ]);

        $response->assertRedirect(route('admin.employees.index'));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('employees', [
            'name' => 'Janon Dukuzumuremyi',
            'email' => 'janon3030@gmail.com',
        ]);
    }

    public function test_employee_can_be_updated_without_a_separate_employee_id_column(): void
    {
        $employee = Employee::query()->create([
            'name' => 'Janon Dukuzumuremyi',
            'province' => 'Kigali City',
            'district' => 'Gasabo',
        ]);

        $response = $this->actingAs($this->owner)->put(route('admin.employees.update', $employee), [
            'name' => 'Janon D.',
            'position' => 'Finance Manager',
            'province' => 'Kigali City',
            'district' => 'Gasabo',
        ]);

        $response->assertRedirect(route('admin.employees.show', $employee));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'name' => 'Janon D.']);
    }

    public function test_employee_index_searches_sorts_and_displays_the_primary_key(): void
    {
        $employee = Employee::query()->create([
            'name' => 'Janon Dukuzumuremyi',
            'province' => 'Kigali City',
            'district' => 'Gasabo',
        ]);

        $response = $this->actingAs($this->owner)->get(route('admin.employees.index', [
            'search' => (string) $employee->id,
            'sort' => 'id',
            'direction' => 'desc',
        ]));

        $response->assertOk();
        $response->assertSee('Janon Dukuzumuremyi');
        $response->assertSee((string) $employee->id);
    }

    public function test_production_create_form_loads_employee_options_from_existing_columns(): void
    {
        Employee::query()->create([
            'name' => 'Janon Dukuzumuremyi',
            'position' => 'Finance Manager',
            'province' => 'Kigali City',
            'district' => 'Gasabo',
        ]);

        $response = $this->actingAs($this->owner)->get(route('admin.productions.create'));

        $response->assertOk();
        $response->assertSee('Janon Dukuzumuremyi');
    }
}
