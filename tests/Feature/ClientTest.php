<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_super_admin_can_manage_clients_in_the_admin_panel(): void
    {
        $this->actingAs($this->userWithRole('Super Admin'));

        $this->get('/admin/organisation/clients')->assertOk()->assertSee('Add client');

        $this->post('/admin/organisation/clients', [
            'name' => 'Acme Corp', 'code' => 'ACME', 'email' => 'ops@acme.test',
            'status' => 'active', 'notes' => 'Key account',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $client = Client::where('code', 'ACME')->firstOrFail();
        $this->assertSame('Key account', $client->notes);
        $this->get('/admin/organisation/clients')->assertSee('Acme Corp');
        $this->get("/admin/organisation/clients/{$client->id}/edit")->assertOk()->assertSee('Key account');

        $this->put("/admin/organisation/clients/{$client->id}", [
            'name' => 'Acme Corporation', 'code' => 'ACME', 'status' => 'inactive',
        ])->assertRedirect(route('admin.org.index', 'clients'));
        $this->assertSame('inactive', $client->fresh()->status);

        $this->delete("/admin/organisation/clients/{$client->id}")->assertRedirect();
        $this->assertModelMissing($client);
        $this->assertDatabaseHas('audit_logs', ['action' => 'clients.created']);
    }

    public function test_sidebar_has_a_clients_link_separate_from_organisation(): void
    {
        $this->actingAs($this->userWithRole('Super Admin'));

        $this->get('/admin')->assertOk()
            ->assertSee(route('admin.org.index', 'clients'), false);

        // On the clients page only "Clients" is highlighted, and it has no Organisation tabs.
        $this->get('/admin/organisation/clients')->assertOk()
            ->assertSee('<h1>Clients</h1>', false)
            ->assertDontSee('<h1>Organisation</h1>', false);

        // The Organisation tabs no longer list clients: the only link left is the sidebar one.
        $html = $this->get('/admin/organisation/branches')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'href="'.route('admin.org.index', 'clients').'"'));
    }

    public function test_client_code_must_be_unique(): void
    {
        $this->actingAs($this->userWithRole('Super Admin'));
        Client::create(['name' => 'Acme', 'code' => 'ACME']);

        $this->post('/admin/organisation/clients', ['name' => 'Other', 'code' => 'ACME', 'status' => 'active'])
            ->assertSessionHasErrors('code');

        // The add-client pop-up opens again by itself so the error is visible.
        $html = $this->from('/admin/organisation/clients')->followingRedirects()
            ->post('/admin/organisation/clients', ['name' => 'Other', 'code' => 'ACME', 'status' => 'active'])
            ->assertSee('The code has already been taken.')
            ->getContent();
        $this->assertMatchesRegularExpression('/<dialog id="add-record-dialog"[^>]*data-open-on-load/', $html);
    }

    public function test_add_client_form_is_a_modal_but_other_org_pages_keep_the_inline_form(): void
    {
        $this->actingAs($this->userWithRole('Super Admin'));

        $html = $this->get('/admin/organisation/clients')->assertOk()
            ->assertSee('<dialog id="add-record-dialog"', false)
            ->assertSee('data-open-dialog="add-record-dialog"', false)
            ->getContent();
        $this->assertDoesNotMatchRegularExpression('/<dialog id="add-record-dialog"[^>]*data-open-on-load/', $html);

        $this->get('/admin/organisation/branches')->assertOk()
            ->assertDontSee('id="add-record-dialog"', false)
            ->assertSee('+ Add branch');
    }

    public function test_hr_admin_can_create_update_and_delete_clients_via_api(): void
    {
        Sanctum::actingAs($this->userWithRole('HR Admin'));

        $id = $this->postJson('/api/v1/clients', ['name' => 'Globex', 'code' => 'GLX', 'city' => 'Kolkata'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->json('data.id');

        $this->patchJson("/api/v1/clients/{$id}", ['phone' => '+91 90000 00000'])
            ->assertOk()->assertJsonPath('data.phone', '+91 90000 00000');

        $this->getJson('/api/v1/clients?q=glo')->assertOk()->assertJsonPath('data.data.0.code', 'GLX');

        $this->deleteJson("/api/v1/clients/{$id}")->assertOk();
        $this->assertDatabaseMissing('clients', ['id' => $id]);
    }

    public function test_managers_can_view_but_not_change_clients(): void
    {
        $client = Client::create(['name' => 'Initech', 'code' => 'INI']);
        Sanctum::actingAs($this->userWithRole('Manager'));

        $this->getJson("/api/v1/clients/{$client->id}")->assertOk()->assertJsonPath('data.name', 'Initech');
        $this->postJson('/api/v1/clients', ['name' => 'X', 'code' => 'X'])->assertForbidden();
        $this->deleteJson("/api/v1/clients/{$client->id}")->assertForbidden();
    }

    public function test_employees_cannot_see_clients(): void
    {
        Sanctum::actingAs($this->userWithRole('Employee'));

        $this->getJson('/api/v1/clients')->assertForbidden();
    }
}
