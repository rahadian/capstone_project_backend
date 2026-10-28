<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function notification(User $user, array $data = [], bool $read = false): string
    {
        $id = (string) Str::uuid();

        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'App\\Notifications\\PaymentDueReminder',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(array_merge([
                'type' => 'payment_due',
                'message' => 'Tagihan Oktober 2026 jatuh tempo besok.',
                'month' => '2026-10',
                'amount' => 1_500_000,
                'contract_id' => 7,
            ], $data)),
            'read_at' => $read ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    public function test_user_sees_latest_notifications_with_unread_count()
    {
        $user = User::factory()->create(['role' => 'tenant']);
        $this->notification($user);
        $this->notification($user, ['month' => '2026-11']);
        $this->notification($user, ['month' => '2026-12'], true);

        $response = $this->actingAs($user, 'api')->getJson('/api/notifications');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('unread', 2)
            ->assertJsonPath('data.0.type', 'payment_due')
            ->assertJsonPath('data.0.contract_id', 7);
    }

    public function test_user_marks_one_notification_read()
    {
        $user = User::factory()->create(['role' => 'tenant']);
        $id = $this->notification($user);

        $this->actingAs($user, 'api')
            ->postJson("/api/notifications/{$id}/read")
            ->assertStatus(204);

        $this->assertNotNull(DB::table('notifications')->where('id', $id)->value('read_at'));
    }

    public function test_user_cannot_mark_another_users_notification()
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $id = $this->notification($owner);

        $this->actingAs(User::factory()->create(['role' => 'tenant']), 'api')
            ->postJson("/api/notifications/{$id}/read")
            ->assertStatus(404);
        $this->assertNull(DB::table('notifications')->where('id', $id)->value('read_at'));
    }

    public function test_user_marks_all_read()
    {
        $user = User::factory()->create(['role' => 'tenant']);
        $this->notification($user);
        $this->notification($user);

        $this->actingAs($user, 'api')
            ->postJson('/api/notifications/read-all')
            ->assertStatus(204);

        $this->assertSame(0, DB::table('notifications')
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->count());
    }

    public function test_guest_is_rejected()
    {
        $this->getJson('/api/notifications')->assertStatus(401);
    }
}
